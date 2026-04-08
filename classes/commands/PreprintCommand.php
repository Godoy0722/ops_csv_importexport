<?php

/**
 * @file plugins/importexport/csv/classes/commands/PreprintCommand.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PreprintCommand
 *
 * @ingroup plugins_importexport_csv
 *
 * @brief Handles the preprint import when the user uses the preprints command
 */

namespace APP\plugins\importexport\csv\classes\commands;

use APP\core\Application;
use APP\facades\Repo;
use APP\file\PublicFileManager;
use APP\publication\Publication;
use APP\plugins\importexport\csv\classes\cachedAttributes\CachedEntities;
use APP\plugins\importexport\csv\classes\processors\PublicationProcessor;
use APP\plugins\importexport\csv\classes\processors\SectionsProcessor;
use APP\plugins\importexport\csv\classes\processors\SubmissionProcessor;
use APP\plugins\importexport\csv\classes\validations\InvalidRowValidations;
use APP\plugins\importexport\csv\classes\validations\RequiredPreprintHeaders;
use APP\plugins\importexport\csv\shared\exceptions\FileNotSavedException;
use APP\plugins\importexport\csv\shared\exceptions\RowValidationException;
use APP\plugins\importexport\csv\shared\handlers\CSVFileHandler;
use APP\plugins\importexport\csv\shared\handlers\DryModeReporter;
use APP\plugins\importexport\csv\shared\processors\AuthorsProcessor;
use APP\plugins\importexport\csv\shared\processors\CategoriesProcessor;
use APP\plugins\importexport\csv\shared\processors\FundersProcessor;
use APP\plugins\importexport\csv\shared\processors\GalleyProcessor;
use APP\plugins\importexport\csv\shared\processors\KeywordsProcessor;
use APP\plugins\importexport\csv\shared\processors\StatisticsProcessor;
use APP\plugins\importexport\csv\shared\processors\SubjectsProcessor;
use APP\plugins\importexport\csv\shared\processors\SubmissionFileProcessor;
use APP\server\ServerDAO;
use APP\submission\Submission;
use Illuminate\Support\Facades\DB;
use PKP\db\DAORegistry;
use PKP\file\FileManager;
use PKP\services\PKPFileService;
use PKP\user\User;

class PreprintCommand
{
    /** Expected row size for a CSV based on the command passed as argument */
    private int $expectedRowSize;

    private int $processedRows;

    private int $failedRows;

    private PublicFileManager $publicFileManager;

    private FileManager $fileManager;

    private PKPFileService $fileService;

    /**
     * The file directory array map used by the application.
     *
     * @var string[]
     */
    private array $dirNames;

    private string $format;

    /**
     * Array to track processed preprints by identifier, version, and locale
     * Structure: [
     *     'identifier' => [
     *         'version1' => [
     *             'locale1' => [
     *                 'data' => csv_row,
     *                 'publication' => Publication,
     *                 'submission' => Submission
     *             ],
     *             'locale2' => [
     *                 'data' => csv_row,
     *                 'publication' => Publication,
     *                 'submission' => Submission
     *             ]
     *         ],
     *         'version2' => [
     *             'locale1' => [
     *                 'data' => csv_row,
     *                 'publication' => Publication,
     *                 'submission' => Submission
     *             ]
     *         ]
     *     ]
     * ]
     *
     * @var array
     */
    private array $processedPreprints;
    private array $failedIdentifiers = [];

    public function __construct(private string $sourceDir, private User $user, private bool $dryMode = false)
    {
        $this->expectedRowSize = count(RequiredPreprintHeaders::$preprintHeaders);
        $this->processedPreprints = [];

        // Initialize static variables.
        $this->dirNames ??= Application::getFileDirectories();
        $this->format ??= trim($this->dirNames['context'], '/') . '/%d/' . trim($this->dirNames['submission'], '/') . '/%d';
        $this->fileManager ??= new FileManager();
        $this->publicFileManager ??= new PublicFileManager();
        $this->fileService ??= app()->get('file');
    }

    public function run(): array
    {
        $totalFiles = 0;
        $totalPassed = 0;
        $totalFailed = 0;
        $results = [
            'filesProcessed' => 0,
            'totalRows' => 0,
            'successfulRows' => 0,
            'failedRows' => 0,
            'perFile' => [],
        ];

        foreach (new \DirectoryIterator($this->sourceDir) as $fileInfo) {
            // Accept CSV files regardless of extension case (e.g., .csv, .CSV, .Csv)
            if (!$fileInfo->isFile() || strcasecmp($fileInfo->getExtension(), 'csv') !== 0) {
                continue;
            }

            // Skip invalid_*.csv files created by previous failed imports
            $basename = $fileInfo->getBasename();
            if (str_starts_with($basename, 'invalid_')) {
                echo __('plugins.importexport.csv.skippingInvalidFile', ['filename' => $basename]) . "\n";
                continue;
            }

            $filePath = $fileInfo->getPathname();
            $file = CSVFileHandler::createReadableCSVFile($filePath);

            if (is_null($file)) {
                continue;
            }

            $invalidCsvFile = null;

            $this->processedRows = 0;
            $this->failedRows = 0;
            $fileFailedRows = [];

            if ($this->dryMode) {
                DB::statement('SET FOREIGN_KEY_CHECKS=0');
                DB::beginTransaction();
            }

            foreach ($file as $index => $fields) {
                if (!$index || empty(array_filter($fields))) {
                    continue; // Skip headers or end of file
                }

                ++$this->processedRows;

                try {
                    InvalidRowValidations::validateRowContainAllFields($fields, $this->expectedRowSize);

                    $data = (object) array_combine(
                        RequiredPreprintHeaders::$preprintHeaders,
                        array_pad(array_map('trim', $fields), $this->expectedRowSize, null)
                    );

                    // Check for cascaded multi-locale/version failure before required fields check
                    if (
                        !empty($data->versionIdentifier)
                        && !empty($data->version)
                        && !isset($this->processedPreprints[$data->versionIdentifier])
                        && isset($this->failedIdentifiers[$data->versionIdentifier])
                    ) {
                        throw new RowValidationException(
                            __('plugins.importexport.csv.baseRowFailedForIdentifier', [
                                'identifier' => $data->versionIdentifier,
                            ])
                        );
                    }

                    InvalidRowValidations::validateRowHasAllRequiredFields(
                        $data,
                        fn($row) => RequiredPreprintHeaders::validateRowHasAllRequiredFields($row, $this->processedPreprints)
                    );
                    InvalidRowValidations::validateContextVersioningFields($data);
                    InvalidRowValidations::validateSectionFields($data);

                    if (!empty($data->versionIdentifier)) {
                        InvalidRowValidations::validateNoDuplicateVersion($data, $this->processedPreprints);
                    }

                    if ($data->galleyFilenames) {
                        InvalidRowValidations::validatePublicationGalleys(
                            $data->galleyFilenames,
                            $data->galleyLabels,
                            $this->sourceDir
                        );
                    }

                    if ($data->suppFilenames) {
                        InvalidRowValidations::validateSupplementaryFiles(
                            $data->suppFilenames,
                            $data->suppLabels,
                            $this->sourceDir
                        );
                    }

                    InvalidRowValidations::validatePublicationViews($data->preprintViews ?? null, 'preprintViews');
                    InvalidRowValidations::validateGalleyViews($data->galleyViews ?? null, $data->galleyLabels ?? null);

                    if ($data->suppFilenames && $data->suppLabels && !empty($data->suppDescriptions)) {
                        InvalidRowValidations::validateSupplementaryDescriptions(
                            $data->suppFilenames,
                            $data->suppLabels,
                            $data->suppDescriptions
                        );
                    }

                    if ($data->references) {
                        InvalidRowValidations::validateReferencesFile($data->references, $this->sourceDir);
                    }

                    if ($data->vorDoi) {
                        InvalidRowValidations::validateVorDoi($data->vorDoi);
                    }

                    if ($data->funders) {
                        InvalidRowValidations::validateFunders($data->funders);
                    }

                    if (!empty($data->authors)) {
                        $authorsString = array_map('trim', explode(';', $data->authors));
                        foreach ($authorsString as $authorString) {
                            $authorParts = array_map('trim', explode(',', $authorString));
                            $emailAddress = $authorParts[2] ?? '';

                            InvalidRowValidations::validateEmail($emailAddress);
                        }
                    }

                    $fileUploadUser = $this->user;
                    $usedDefaultUser = false;
                    $csvUser = null;
                    if (!empty($data->username)) {
                        $csvUser = CachedEntities::getCachedUserByUsername($data->username, true);
                        $csvUser ? $fileUploadUser = $csvUser : $usedDefaultUser = true;
                    }

                    $hasValidCsvUser = !empty($data->username) && !$usedDefaultUser && isset($csvUser);

                    $server = CachedEntities::getCachedServer($data->serverPath);

                    InvalidRowValidations::validateContextIsValid($server, $data->serverPath, 'Server');
                    InvalidRowValidations::validateContextLocale($server, $data->locale, 'Server');

                    // we need a Genre for the files.  Assume a key of SUBMISSION as a default.
                    $genreName = 'SUBMISSION';
                    $genreId = CachedEntities::getCachedGenreId($genreName, $server->getId());
                    InvalidRowValidations::validateGenreIdValid($genreId, $genreName);

                    $userGroupId = CachedEntities::getCachedAuthorUserGroupId($data->serverPath, $server->getId());
                    InvalidRowValidations::validateUserGroupId($userGroupId, $data->serverPath, 'Server');

                    // Validate Funding plugin is enabled if funders data is provided
                    if ($data->funders) {
                        InvalidRowValidations::validateFundingPluginEnabled($data->funders, $server->getId(), 'Server');
                        InvalidRowValidations::validateFundersCrossrefRegistry($data->funders, $server->getId());
                    }

                    $coverImageUploadName = null;
                    if (!$this->dryMode && $data->coverImageFilename) {
                        try {
                            $coverImageUploadName = PublicationProcessor::uploadCoverImage(
                                $data,
                                $server->getId(),
                                $this->sourceDir,
                                $this->publicFileManager,
                                $this->fileManager
                            );
                        } catch (\Exception $e) {
                            throw new RowValidationException($e->getMessage());
                        }
                    }

                    /** @var null|Submission */
                    $existingSubmission = null;
                    /** @var null|Publication */
                    $basePublication = null;
                    $isMultiLocaleImport = false;

                    if (!empty($data->versionIdentifier)) {
                        if (InvalidRowValidations::versionExistsInAnyLocale($data, $this->processedPreprints)) {
                            $version = (int)$data->version;
                            $versionData = $this->processedPreprints[$data->versionIdentifier][$version];

                            $firstLocaleData = reset($versionData);
                            $existingSubmission = $firstLocaleData['submission'];
                            $basePublication = $firstLocaleData['publication'];

                            $isMultiLocaleImport = !isset($versionData[$data->locale]);
                        } elseif (isset($this->processedPreprints[$data->versionIdentifier])) {
                            // Handle new version (not multi-locale)
                            $versions = $this->processedPreprints[$data->versionIdentifier];
                            $lastVersion = end($versions);
                            $lastVersionData = reset($lastVersion);
                            $existingSubmission = $lastVersionData['submission'];
                            $basePublication = $lastVersionData['publication'];
                        }
                    }

                    if ($isMultiLocaleImport) {
                        $submission = $existingSubmission;
                        $publication = $basePublication;

                        $publication = PublicationProcessor::processMultiLocalePublication($publication, $data, $server);
                    } elseif ($existingSubmission && $basePublication) {
                        // New version import
                        $submission = $existingSubmission;
                        $publication = PublicationProcessor::createPublicationVersionCommons($basePublication, $data, $server);
                        $publication = PublicationProcessor::processVersionedPublication($publication, $data, $basePublication, $this->sourceDir);
                    } else {
                        // New submission import
                        $initialPublication = PublicationProcessor::createInitialPublication($data);
                        $submission = SubmissionProcessor::process($data, $initialPublication, $server);
                        $publication = PublicationProcessor::process($submission, $data, $server, $this->sourceDir);
                    }

                    InvalidRowValidations::validatePublicationWasSuccessfullyCreated($publication);

                    if ($hasValidCsvUser) {
                        Repo::stageAssignment()->build(
                            $submission->getId(),
                            $userGroupId,
                            $csvUser->getId()
                        );
                    }

                    if (!$this->dryMode) {
                        $galleyMetadata = $this->processGalleys($data, $server->getId(), $submission, $genreId, $publication->getId(), $fileUploadUser);
                    } else {
                        $galleyMetadata = [];
                    }

                    if (!empty($data->preprintViews) && (int)$data->preprintViews > 0) {
                        StatisticsProcessor::insertSubmissionViews(
                            $submission->getId(),
                            $server->getId(),
                            (int)$data->preprintViews
                        );
                    }

                    if (!$this->dryMode && !empty($data->galleyViews) && !empty($galleyMetadata)) {
                        $galleyViewsArray = explode(';', $data->galleyViews);
                        foreach ($galleyViewsArray as $idx => $views) {
                            $views = trim($views);
                            if ($views === '' || (int)$views === 0) {
                                continue;
                            }
                            if (isset($galleyMetadata[$idx])) {
                                $meta = $galleyMetadata[$idx];
                                StatisticsProcessor::insertGalleyViews(
                                    $submission->getId(),
                                    $server->getId(),
                                    $meta['galleyId'],
                                    $meta['submissionFileId'],
                                    StatisticsProcessor::resolveFileType($meta['filename']),
                                    (int)$views
                                );
                            }
                        }
                    }

                    // Process supplementary files
                    if (!$this->dryMode && $data->suppFilenames) {
                        // Get supplementary genre for supplementary files
                        $suppGenreId = CachedEntities::getCachedSupplementaryGenreId($server->getId()) ?? $genreId;
                        $suppIds = [];

                        foreach (array_map('trim', explode(';', $data->suppFilenames)) as $suppFile) {
                            try {
                                $suppFileId = $this->saveSubmissionFile(
                                    $suppFile,
                                    $server->getId(),
                                    $submission,
                                    __('plugins.importexport.csv.errorWhileSavingSupplementaryFile', ['file' => $suppFile])
                                );
                            } catch (FileNotSavedException $e) {
                                // The submission is already deleted by saveSubmissionFile(), which cascades to delete
                                // the linked galley files (galleyIds). We only need to manually delete the
                                // supplementary files that were uploaded but not yet linked (suppIds).
                                foreach($suppIds as $suppItem) {
                                    $this->fileService->delete($suppItem['id']);
                                }

                                throw $e;
                            }

                            $suppIds[] = ['file' => $suppFile, 'id' => $suppFileId];
                        }

                        $suppLabelsArray = array_map('trim', explode(';', $data->suppLabels));
                        $suppDescriptionsArray = !empty($data->suppDescriptions)
                            ? array_map('trim', explode(';', $data->suppDescriptions))
                            : [];

                        for($i = 0; $i < count($suppLabelsArray); $i++) {
                            $suppItem = $suppIds[$i];
                            $suppLabel = $suppLabelsArray[$i];
                            $suppDescription = $suppDescriptionsArray[$i] ?? null;

                            $this->handleGalley(
                                $suppItem,
                                $data,
                                $submission->getId(),
                                $suppGenreId,
                                $suppLabel,
                                $publication->getId(),
                                $fileUploadUser,
                                $suppDescription
                            );
                        }
                    }

                    if ($isMultiLocaleImport) {
                        // For multi-locale imports, update existing publication with new locale data
                        if ($hasValidCsvUser) {
                            AuthorsProcessor::updateUsernameAuthorLocale($csvUser, $publication, $data->locale);
                        }
                        AuthorsProcessor::processMultiLocale($data, $server->getContactEmail(), $submission->getId(), $publication, $userGroupId);
                        KeywordsProcessor::processMultiLocale($data, $publication);
                        SubjectsProcessor::processMultiLocale($data, $publication);
                        FundersProcessor::processMultiLocale($data, $submission, $server->getId());
                        PublicationProcessor::processSupportingAgenciesMultiLocale($data, $publication);
                    } else {
                        $usernameAuthorAdded = false;
                        if ($hasValidCsvUser && (!empty($data->authors) || is_null($basePublication))) {
                            AuthorsProcessor::addAuthorFromUser($csvUser, $submission, $publication, $server, $userGroupId);
                            $usernameAuthorAdded = true;
                        }

                        // For new submissions or versions, use the regular process
                        AuthorsProcessor::process($data, $server->getContactEmail(), $submission->getId(), $publication, $userGroupId, $basePublication, $usernameAuthorAdded ? $csvUser : null);
                        KeywordsProcessor::process($data, $publication, $basePublication);
                        SubjectsProcessor::process($data, $publication, $basePublication);
                        FundersProcessor::process($data, $submission, $server->getId(), $basePublication);
                        PublicationProcessor::processSupportingAgencies($data, $publication, $basePublication);
                    }

                    if (!empty($data->vorDoi)) {
                        PublicationProcessor::updateVorDoi($publication, $data->vorDoi);
                    }

                    if ((empty($data->version) || (int)$data->version === 1) && !empty($data->coverage)) {
                        PublicationProcessor::updateCoverage($publication, $data->coverage, $data->locale);
                    }

                    !is_null($basePublication)
                        ? PublicationProcessor::updateSectionId($publication, $basePublication->getData('sectionId'))
                        : SectionsProcessor::process($data, $server, $publication);

                    if ($data->coverImageFilename && $coverImageUploadName !== null) {
                        PublicationProcessor::updateCoverImage($publication, $data, $coverImageUploadName);
                    } elseif ($basePublication && $basePublication->getLocalizedData('coverImage', $data->locale)) {
                        PublicationProcessor::setCoverImage(
                            $publication,
                            $basePublication->getLocalizedData('coverImage', $data->locale),
                            $data->locale
                        );
                    }

                    Repo::publication()->dao->update($publication);
                    $publication = Repo::publication()->get($publication->getId());

                    if ($data->categories || $basePublication) {
                        if ($isMultiLocaleImport) {
                            CategoriesProcessor::processMultiLocale($data->categories, $data->locale, $server->getId(), $publication->getId());
                        } elseif ($existingSubmission && $basePublication) {
                            CategoriesProcessor::processForVersion($data->categories, $data->locale, $server->getId(), $publication->getId(), $basePublication);
                        } else {
                            CategoriesProcessor::process($data->categories, $data->locale, $server->getId(), $publication->getId());
                        }

                        // Reload publication to populate categoryIds property after assignment
                        $publication = Repo::publication()->get($publication->getId());
                    }

                    if (!empty($data->versionIdentifier)) {
                        $this->trackProcessedPreprint($data, $submission, $publication);
                    }

                    if ($usedDefaultUser) {
                        echo __('plugins.importexport.csv.usernameNotFoundUsingDefault', [
                            'username' => $data->username,
                            'submissionId' => $submission->getId(),
                            'defaultUsername' => $this->user->getUsername()
                        ]) . "\n";
                    }

                } catch (RowValidationException | FileNotSavedException $e) {
                    // Track failed versionIdentifiers for cascaded failure detection
                    $failedIdentifier = $fields[2] ?? null;
                    if (!empty($failedIdentifier)) {
                        $this->failedIdentifiers[$failedIdentifier] = true;
                    }

                    if (is_null($invalidCsvFile)) {
                        $invalidCsvFile = CSVFileHandler::createCSVFileInvalidRows($this->sourceDir, "invalid_{$basename}", RequiredPreprintHeaders::$preprintHeaders);
                        if (is_null($invalidCsvFile)) {
                            continue 2;
                        }
                    }
                    CSVFileHandler::processFailedRow($invalidCsvFile, $fields, $this->expectedRowSize, $e->getMessage(), $this->failedRows);
                    if ($this->dryMode) {
                        $fileFailedRows[] = ['row' => $this->processedRows + 1, 'reason' => $e->getMessage()];
                    }
                    continue;
                }
            }

            if ($this->dryMode) {
                $passed = $this->processedRows - $this->failedRows;
                DryModeReporter::printFileHeader($basename);
                if (!empty($fileFailedRows)) {
                    DryModeReporter::printTableHeader();
                    foreach ($fileFailedRows as $failedRow) {
                        DryModeReporter::printFailedRow($failedRow['row'], $failedRow['reason']);
                    }
                }
                DryModeReporter::printFileSummary($passed, $this->failedRows, $this->processedRows);
                $totalFiles++;
                $totalPassed += $passed;
                $totalFailed += $this->failedRows;

                DB::rollBack();
                DB::statement('SET FOREIGN_KEY_CHECKS=1');
                CachedEntities::reset();
                $this->processedPreprints = [];
                $this->failedIdentifiers = [];
            }

            echo __('plugins.importexpot.csv.fileProcessFinished', [
                'filename' => $fileInfo->getFilename(),
                'processedRows' => $this->processedRows,
                'failedRows' => $this->failedRows,
            ]) . "\n";

            $fileResult = [
                'filename' => $basename,
                'rows' => $this->processedRows,
                'successful' => $this->processedRows - $this->failedRows,
                'failed' => $this->failedRows,
                'errors' => $fileFailedRows,
                'invalidFile' => $this->failedRows > 0 ? "invalid_{$basename}" : null,
            ];
            $results['perFile'][] = $fileResult;
            $results['filesProcessed']++;
            $results['totalRows'] += $this->processedRows;
            $results['successfulRows'] += $this->processedRows - $this->failedRows;
            $results['failedRows'] += $this->failedRows;
        }

        if ($this->dryMode) {
            DryModeReporter::printGrandTotal($totalFiles, $totalPassed, $totalFailed);
            $results['exitCode'] = $totalFailed > 0 ? 1 : 0;
            return $results;
        }

        $this->syncCoverImagesForProcessedPreprints();
        $this->setCurrentVersionsForProcessedPreprints();

        $results['exitCode'] = 0;
        return $results;
    }

    /**
     * Process galley files and labels.
     */
    private function processGalleys(
        object $data,
        int $serverId,
        Submission $submission,
        int $genreId,
        int $publicationId,
        User $fileUploadUser
    ): array
    {
        // Array to store each galley ID to its respective galley file
        $galleyIds = [];
        $galleyMetadata = [];
        if ($data->galleyFilenames) {
            foreach (array_map('trim', explode(';', $data->galleyFilenames)) as $galleyFile) {
                try {
                    $galleyFileId = $this->saveSubmissionFile(
                        $galleyFile,
                        $serverId,
                        $submission,
                        __('plugins.importexport.csv.errorWhileSavingSubmissionGalley', ['galley' => $galleyFile])
                    );
                } catch (FileNotSavedException $e) {
                    foreach($galleyIds as $galleyItem) {
                        $this->fileService->delete($galleyItem['id']);
                    }

                    throw $e;
                }

                $galleyIds[] = ['file' => $galleyFile, 'id' => $galleyFileId];
            }

            $galleyLabelsArray = array_map('trim', explode(';', $data->galleyLabels));
            for($i = 0; $i < count($galleyLabelsArray); $i++) {
                $galleyItem = $galleyIds[$i];
                $galleyLabel = $galleyLabelsArray[$i];

                $galleyMetadata[] = $this->handleGalley(
                    $galleyItem,
                    $data,
                    $submission->getId(),
                    $genreId,
                    $galleyLabel,
                    $publicationId,
                    $fileUploadUser
                );
            }
        }

        return $galleyMetadata;
    }

    /**
     * Save a submission file. If an error occurred, the method will delete the submission already saved.
     *
     * @throws FileNotSavedException
     */
    private function saveSubmissionFile(
        string $filePath,
        int $serverId,
        Submission $submission,
        string $errorMessage
    ): int
    {
        try {
            $extension = $this->fileManager->parseFileExtension($filePath);
            $submissionDir = sprintf($this->format, $serverId, $submission->getId());
            $completePath = "{$this->sourceDir}/{$filePath}";

            return $this->fileService->add($completePath, $submissionDir . '/' . uniqid() . '.' . $extension);
        } catch (\Exception $e) {
            Repo::submission()->delete($submission);

            throw new FileNotSavedException($errorMessage . ' (' . $e->getMessage() . ')');
        }
    }

    /** Process data for the galley submission file and galley into the database. */
    private function handleGalley(
        array $item,
        object $data,
        int $submissionId,
        int $genreId,
        string $label,
        int $publicationId,
        User $fileUploadUser,
        ?string $description = null
    ): array
    {
        $galleyCompletePath = "{$this->sourceDir}/{$item['file']}";
        $galleyExtension = $this->fileManager->parseFileExtension($galleyCompletePath);

        $submissionFile = SubmissionFileProcessor::process(
            $data->locale,
            $fileUploadUser->getId(),
            $submissionId,
            $galleyCompletePath,
            $genreId,
            $item['id'],
            $description
        );

        // Now that we have the submission file ID, it's time to process the galley itself.
        $galleyId = GalleyProcessor::process($submissionFile->getId(), $data, $label, $publicationId, $galleyExtension);
        SubmissionFileProcessor::updateAssocInfo($submissionFile, $galleyId);

        return [
            'galleyId' => $galleyId,
            'submissionFileId' => $submissionFile->getId(),
            'filename' => $item['file'],
        ];
    }

    /**
     * Tracks a processed preprint for version and locale management
     */
    private function trackProcessedPreprint(object $data, Submission $submission, Publication $publication): void
    {
        $identifier = $data->versionIdentifier;
        $version = (int)$data->version;
        $locale = $data->locale;

        if (!isset($this->processedPreprints[$identifier])) {
            $this->processedPreprints[$identifier] = [];
        }

        if (!isset($this->processedPreprints[$identifier][$version])) {
            $this->processedPreprints[$identifier][$version] = [];
        }

        $this->processedPreprints[$identifier][$version][$locale] = [
            'data' => $data,
            'submission' => $submission,
            'publication' => $publication
        ];
    }

    private function syncCoverImagesForProcessedPreprints(): void
    {
        foreach ($this->processedPreprints as $identifier => $versions) {
            foreach ($versions as $versionNumber => $localeData) {
                $firstLocaleData = reset($localeData);
                $publication = $firstLocaleData['publication'];
                $publicationId = $publication->getId();

                $serverId = Repo::submission()->get($publication->getData('submissionId'))->getData('contextId');
                $serverDao = DAORegistry::getDAO('ServerDAO'); /** @var ServerDAO $serverDao */
                $server = $serverDao->getById($serverId);
                if (!$server) {
                    continue;
                }
                $defaultLocale = $server->getPrimaryLocale();

                $coverImageSettings = \Illuminate\Support\Facades\DB::table('publication_settings')
                    ->where('publication_id', $publicationId)
                    ->where('setting_name', 'coverImage')
                    ->get();

                if ($coverImageSettings->isEmpty()) {
                    continue;
                }

                $coverImagesByLocale = [];
                foreach ($coverImageSettings as $setting) {
                    if (empty($setting->setting_value)) {
                        continue;
                    }

                    $coverImageData = json_decode($setting->setting_value, true);
                    if (!empty($coverImageData)) {
                        $coverImagesByLocale[$setting->locale] = $coverImageData;
                    }
                }

                if (empty($coverImagesByLocale)) {
                    continue;
                }

                $sourceCoverImage = isset($coverImagesByLocale[$defaultLocale])
                    ? $coverImagesByLocale[$defaultLocale]
                    : reset($coverImagesByLocale);

                $allPublicationLocales = DB::table('publication_settings')
                    ->where('publication_id', $publicationId)
                    ->whereNotNull('locale')
                    ->whereNot('locale', '')
                    ->distinct()
                    ->pluck('locale')
                    ->toArray();

                foreach ($allPublicationLocales as $locale) {
                    if (!(!isset($coverImagesByLocale[$locale]) && $sourceCoverImage)) {
                        continue;
                    }

                    $reloadedPublication = Repo::publication()->get($publicationId);
                    if ($reloadedPublication) {
                        PublicationProcessor::setCoverImage($reloadedPublication, $sourceCoverImage, $locale);
                        Repo::publication()->dao->update($publication);
                    }
                }
            }
        }
    }

    /**
     * Set the highest version as current for each processed preprint identifier
     */
    private function setCurrentVersionsForProcessedPreprints(): void
    {
        foreach ($this->processedPreprints as $identifier => $versions) {
            if (count($versions) <= 1) {
                continue; // Skip if only one version exists
            }

            $highestVersion = 0;
            $currentVersionData = null;

            foreach ($versions as $versionKey => $localeData) {
                // Get the first locale for this version (all locales share the same submission/publication)
                $firstLocaleData = reset($localeData);
                $versionNumber = (int)$firstLocaleData['data']->version;

                if ($versionNumber > $highestVersion) {
                    $highestVersion = $versionNumber;
                    $currentVersionData = $firstLocaleData;
                }
            }

            if ($currentVersionData) {
                /** @var Submission */
                $submission = $currentVersionData['submission'];
                /** @var Publication */
                $publication = $currentVersionData['publication'];

                SubmissionProcessor::setCurrentPublicationId($submission, $publication->getId());
            }
        }
    }
}
