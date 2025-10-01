<?php

/**
 * @file plugins/importexport/csv/classes/commands/PreprintCommand.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
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
use APP\plugins\importexport\csv\classes\cachedAttributes\CachedDaos;
use APP\plugins\importexport\csv\classes\cachedAttributes\CachedEntities;
use APP\plugins\importexport\csv\classes\handlers\CSVFileHandler;
use APP\plugins\importexport\csv\classes\processors\AuthorsProcessor;
use APP\plugins\importexport\csv\classes\processors\CategoriesProcessor;
use APP\plugins\importexport\csv\classes\processors\GalleyProcessor;
use APP\plugins\importexport\csv\classes\processors\KeywordsProcessor;
use APP\plugins\importexport\csv\classes\processors\PublicationProcessor;
use APP\plugins\importexport\csv\classes\processors\SectionsProcessor;
use APP\plugins\importexport\csv\classes\processors\SubjectsProcessor;
use APP\plugins\importexport\csv\classes\processors\SubmissionFileProcessor;
use APP\plugins\importexport\csv\classes\processors\SubmissionProcessor;
use APP\plugins\importexport\csv\classes\validations\InvalidRowValidations;
use APP\plugins\importexport\csv\classes\validations\RequiredPreprintHeaders;
use APP\submission\Submission;
use PKP\file\FileManager;
use PKP\services\PKPFileService;
use PKP\user\User;

class PreprintCommand
{
    /** Expected row size for a CSV based on the command passed as argument */
    private int $expectedRowSize;

    /** The folder containing all CSV files that the command must go through */
    private string $sourceDir;

    private int $processedRows;

    private int $failedRows;

    private PublicFileManager $publicFileManager;

    private FileManager $fileManager;

    private PKPFileService $fileService;

    private User $user;

    /**
     * The file directory array map used by the application.
     *
     * @var string[]
     */
    private array $dirNames;

    private string $format;

    /**
     * Array to track processed preprints by identifier
     * Structure: [
     *     'identifier' => [
     *         'version1' => [
     *             'data' => csv_row,
     *             'publication' => Publication,
     *             'submission' => Submission
     *         ],
     *         'version2' => [
     *             'data' => csv_row,
     *             'publication' => Publication,
     *             'submission' => Submission
     *         ]
     *     ]
     * ]
     *
     * @var array
     */
    private array $processedPreprints;

    public function __construct(string $sourceDir, User $user)
    {
        $this->expectedRowSize = count(RequiredPreprintHeaders::$preprintHeaders);
        $this->sourceDir = $sourceDir;
        $this->user = $user;
        $this->processedPreprints = [];
    }

    public function run()
    {
        foreach (new \DirectoryIterator($this->sourceDir) as $fileInfo) {
            if (!$fileInfo->isFile() || $fileInfo->getExtension() !== 'csv') {
                continue;
            }

            $filePath = $fileInfo->getPathname();
            $file = CSVFileHandler::createReadableCSVFile($filePath);

            if (is_null($file)) {
                continue;
            }

            $basename = $fileInfo->getBasename();
            $invalidCsvFile = CSVFileHandler::createCSVFileInvalidRows($this->sourceDir, "invalid_{$basename}", RequiredPreprintHeaders::$preprintHeaders);

            if (is_null($invalidCsvFile)) {
                continue;
            }

            $this->processedRows = 0;
            $this->failedRows = 0;

            foreach ($file as $index => $fields) {
                if (!$index || empty(array_filter($fields))) {
                    continue; // Skip headers or end of file
                }

                ++$this->processedRows;

                $reason = InvalidRowValidations::validateRowContainAllFields($fields, $this->expectedRowSize);
                if (!is_null($reason)) {
                    CSVFileHandler::processFailedRow($invalidCsvFile, $fields, $this->expectedRowSize, $reason, $this->failedRows);
                    continue;
                }

                $data = (object) array_combine(
                    RequiredPreprintHeaders::$preprintHeaders,
                    array_pad(array_map('trim', $fields), $this->expectedRowSize, null)
                );

                $reason = InvalidRowValidations::validateRowHasAllRequiredFields($data, [RequiredPreprintHeaders::class, 'validateRowHasAllRequiredFields']);
                if (!is_null($reason)) {
                    CSVFileHandler::processFailedRow($invalidCsvFile, $fields, $this->expectedRowSize, $reason, $this->failedRows);
                    continue;
                }

                $reason = InvalidRowValidations::validatePreprintVersioningFields($data);
                if (!is_null($reason)) {
                    CSVFileHandler::processFailedRow($invalidCsvFile, $fields, $this->expectedRowSize, $reason, $this->failedRows);
                    continue;
                }

                if (!empty($data->versionIdentifier)) {
                    $reason = InvalidRowValidations::validateNoDuplicateVersion($data, $this->processedPreprints);
                    if (!is_null($reason)) {
                        CSVFileHandler::processFailedRow($invalidCsvFile, $fields, $this->expectedRowSize, $reason, $this->failedRows);
                        continue;
                    }
                }

                $fieldsList = array_pad($fields, $this->expectedRowSize, null);

                if ($data->galleyFilenames) {
                    $reason = InvalidRowValidations::validatePreprintGalleys(
                        $data->galleyFilenames,
                        $data->galleyLabels,
                        $this->sourceDir
                    );

                    if (!is_null($reason)) {
                        CSVFileHandler::processFailedRow($invalidCsvFile, $fields, $this->expectedRowSize, $reason, $this->failedRows);
                        continue;
                    }
                }

                if ($data->suppFilenames) {
                    $reason = InvalidRowValidations::validateSupplementaryFiles(
                        $data->suppFilenames,
                        $data->suppLabels,
                        $this->sourceDir
                    );

                    if (!is_null($reason)) {
                        CSVFileHandler::processFailedRow($invalidCsvFile, $fields, $this->expectedRowSize, $reason, $this->failedRows);
                        continue;
                    }
                }

                $server = CachedEntities::getCachedServer($data->serverPath);

                $reason = InvalidRowValidations::validateServerIsValid($server, $data->serverPath);
                if (!is_null($reason)) {
                    CSVFileHandler::processFailedRow($invalidCsvFile, $fields, $this->expectedRowSize, $reason, $this->failedRows);
                    continue;
                }

                $reason = InvalidRowValidations::validateServerLocale($server, $data->locale);
                if (!is_null($reason)) {
                    CSVFileHandler::processFailedRow($invalidCsvFile, $fields, $this->expectedRowSize, $reason, $this->failedRows);
                    continue;
                }

                // we need a Genre for the files.  Assume a key of SUBMISSION as a default.
                $genreName = 'SUBMISSION';
                $genreId = CachedEntities::getCachedGenreId($genreName, $server->getId());

                $reason = InvalidRowValidations::validateGenreIdValid($genreId, $genreName);
                if (!is_null($reason)) {
                    CSVFileHandler::processFailedRow($invalidCsvFile, $fields, $this->expectedRowSize, $reason, $this->failedRows);
                    continue;
                }

                $userGroupId = CachedEntities::getCachedUserGroupId($data->serverPath, $server->getId());

                $reason = InvalidRowValidations::validateUserGroupId($userGroupId, $data->serverPath);
                if (!is_null($reason)) {
                    CSVFileHandler::processFailedRow($invalidCsvFile, $fields, $this->expectedRowSize, $reason, $this->failedRows);
                    continue;
                }

                $this->initializeStaticVariables();

                $coverImageUploadName = null;
                if ($data->coverImageFilename) {
                    $reason = InvalidRowValidations::validateCoverImageIsValid($data->coverImageFilename, $this->sourceDir);
                    if (!is_null($reason)) {
                        CSVFileHandler::processFailedRow($invalidCsvFile, $fields, $this->expectedRowSize, $reason, $this->failedRows);
                        continue;
                    }

                    $sanitizedCoverImageName = str_replace([' ', '_', ':'], '-', mb_strtolower($data->coverImageFilename));
                    $sanitizedCoverImageName = preg_replace('/[^a-z0-9\.\-]+/', '', $sanitizedCoverImageName);
                    $coverImageUploadName = uniqid() . '-' . basename($sanitizedCoverImageName);

                    $destFilePath = $this->publicFileManager->getContextFilesPath($server->getId()) . '/' . $coverImageUploadName;
                    $srcFilePath = "{$this->sourceDir}/{$data->coverImageFilename}";
                    $bookCoverImageSaved = $this->fileManager->copyFile($srcFilePath, $destFilePath);

                    if (!$bookCoverImageSaved) {
                        $reason = __('plugin.importexport.csv.erroWhileSavingBookCoverImage');
                        CSVFileHandler::processFailedRow($invalidCsvFile, $fields, $this->expectedRowSize, $reason, $this->failedRows);

                        continue;
                    }
                }

                /** @var null|Submission */
                $existingSubmission = null;
                /** @var null|Publication */
                $basePublication = null;
                if (!empty($data->versionIdentifier) && isset($this->processedPreprints[$data->versionIdentifier])) {
                    $firstVersionData = reset($this->processedPreprints[$data->versionIdentifier]);
                    $existingSubmission = $firstVersionData['submission'];
                    $basePublication = $firstVersionData['publication'];
                }

                if ($existingSubmission && $basePublication) {
                    $submission = $existingSubmission;
                    $publication = PublicationProcessor::createPublicationVersion($basePublication, $data);

                    $publication = PublicationProcessor::processVersionedPublication($publication, $data, $basePublication);
                } else {
                    $initialPublication = PublicationProcessor::createInitialPublication($data);
                    $submission = SubmissionProcessor::process($data, $initialPublication, $server);
                    $publication = PublicationProcessor::process($submission, $data, $server);
                }

                if (!$publication) {
                    $reason = __('plugins.importexport.csv.errorWhileCreatingPublication');
                    CSVFileHandler::processFailedRow($invalidCsvFile, $fieldsList, $this->expectedRowSize, $reason, $this->failedRows);
                    continue;
                }

                // Array to store each galley ID to its respective galley file
                $galleyIds = [];
                if ($data->galleyFilenames) {
                    foreach (array_map('trim', explode(';', $data->galleyFilenames)) as $galleyFile) {
                        $galleyFileId = $this->saveSubmissionFile(
                            $galleyFile,
                            $server->getId(),
                            $submission,
                            $invalidCsvFile,
                            __('plugins.importexport.csv.errorWhileSavingSubmissionGalley', ['galley' => $galleyFile]),
                            $fieldsList
                        );

                        if (is_null($galleyFileId)) {
                            foreach($galleyIds as $galleyItem) {
                                $this->fileService->delete($galleyItem['id']);
                            }

                            continue;
                        }

                        $galleyIds[] = ['file' => $galleyFile, 'id' => $galleyFileId];
                    }

                    $galleyLabelsArray = array_map('trim', explode(';', $data->galleyLabels));
                    for($i = 0; $i < count($galleyLabelsArray); $i++) {
                        $galleyItem = $galleyIds[$i];
                        $galleyLabel = $galleyLabelsArray[$i];

                        $this->handleGalley(
                            $galleyItem,
                            $data,
                            $submission->getId(),
                            $genreId,
                            $galleyLabel,
                            $publication->getId()
                        );
                    }
                }

                // Process supplementary files
                if ($data->suppFilenames) {
                    // Get supplementary genre for supplementary files
                    $genreDao = CachedDaos::getGenreDao();
                    $supplementaryGenres = $genreDao->getBySupplementaryAndContextId(true, $server->getId())->toArray();
                    $suppGenreId = !empty($supplementaryGenres) ? $supplementaryGenres[0]->getId() : $genreId;
                    $suppIds = [];

                    foreach (array_map('trim', explode(';', $data->suppFilenames)) as $suppFile) {
                        $suppFileId = $this->saveSubmissionFile(
                            $suppFile,
                            $server->getId(),
                            $submission,
                            $invalidCsvFile,
                            __('plugins.importexport.csv.errorWhileSavingSupplementaryFile', ['file' => $suppFile]),
                            $fieldsList
                        );

                        if (is_null($suppFileId)) {
                            foreach($galleyIds as $galleyItem) {
                                $this->fileService->delete($galleyItem['id']);
                            }

                            foreach($suppIds as $suppItem) {
                                $this->fileService->delete($suppItem['id']);
                            }

                            continue;
                        }

                        $suppIds[] = ['file' => $suppFile, 'id' => $suppFileId];
                    }

                    $suppLabelsArray = array_map('trim', explode(';', $data->suppLabels));
                    for($i = 0; $i < count($suppLabelsArray); $i++) {
                        $suppItem = $suppIds[$i];
                        $suppLabel = $suppLabelsArray[$i];

                        $this->handleGalley(
                            $suppItem,
                            $data,
                            $submission->getId(),
                            $suppGenreId,
                            $suppLabel,
                            $publication->getId()
                        );
                    }
                }

                AuthorsProcessor::process($data, $server->getContactEmail(), $submission->getId(), $publication, $userGroupId, $basePublication);
                KeywordsProcessor::process($data, $publication->getId(), $basePublication);
                SubjectsProcessor::process($data, $publication->getId(), $basePublication);

                if ($data->coverage || ($basePublication && !$data->coverage)) {
                    if (!empty($data->coverage)) {
                        PublicationProcessor::updateCoverage($publication, $data->coverage, $data->locale);
                    } elseif ($basePublication && $basePublication->getLocalizedData('coverage', $data->locale)) {
                        PublicationProcessor::updateCoverage($publication, $basePublication->getLocalizedData('coverage', $data->locale), $data->locale);
                    }
                }

                $section = SectionsProcessor::process($data, $server->getId(), $basePublication);
                PublicationProcessor::updateSectionId($publication, $section->getId());

                if ($data->coverImageFilename) {
                    PublicationProcessor::updateCoverImage($publication, $data, $coverImageUploadName);
                } elseif ($basePublication && $basePublication->getLocalizedData('coverImage', $data->locale)) {
                    PublicationProcessor::updatePublicationAttribute($publication, 'coverImage', $basePublication->getData('coverImage'));
                }

                if ($data->categories || $basePublication) {
                    ($existingSubmission && $basePublication)
                        ? CategoriesProcessor::processForVersion($data->categories, $data->locale, $server->getId(), $publication->getId(), $basePublication)
                        : CategoriesProcessor::process($data->categories, $data->locale, $server->getId(), $publication->getId());
                }

                if (!empty($data->versionIdentifier)) {
                    $this->trackProcessedPreprint($data, $submission, $publication);
                }
            }

            echo __('plugins.importexpot.csv.fileProcessFinished', [
                'filename' => $fileInfo->getFilename(),
                'processedRows' => $this->processedRows,
                'failedRows' => $this->failedRows,
            ]) . "\n";

            if (!$this->failedRows) {
                unlink($this->sourceDir . '/' . "invalid_{$basename}");
            }
        }

        $this->setCurrentVersionsForProcessedPreprints();
    }

    /** Insert static data that will be used for the submission processing */
    private function initializeStaticVariables(): void
    {
        $this->dirNames ??= Application::getFileDirectories();
        $this->format ??= trim($this->dirNames['context'], '/') . '/%d/' . trim($this->dirNames['submission'], '/') . '/%d';
        $this->fileManager ??= new FileManager();
        $this->publicFileManager ??= new PublicFileManager();
        $this->fileService ??= app()->get('file');
    }

    /**
     * Save a submission file. If an error occurred, the method will delete the submission already saved
     * and return null.
     */
    private function saveSubmissionFile(
        string $filePath,
        int $serverId,
        Submission $submission,
        \SplFileObject $invalidCsvFile,
        string $reason,
        array $fieldsList
    ): ?int
    {
        try {
            $extension = $this->fileManager->parseFileExtension($filePath);
            $submissionDir = sprintf($this->format, $serverId, $submission->getId());
            $completePath = "{$this->sourceDir}/{$filePath}";

            return $this->fileService->add($completePath, $submissionDir . '/' . uniqid() . '.' . $extension);
        } catch (\Exception $e) {
            CSVFileHandler::processFailedRow($invalidCsvFile, $fieldsList, $this->expectedRowSize, $reason, $this->failedRows);

            Repo::submission()->delete($submission);

            return null;
        }
    }

    /** Process data for the galley submission file and galley into the database. */
    private function handleGalley(
        array $item,
        object $data,
        int $submissionId,
        int $genreId,
        string $label,
        int $publicationId
    ): void
    {
        $galleyCompletePath = "{$this->sourceDir}/{$item['file']}";
        $galleyExtension = $this->fileManager->parseFileExtension($galleyCompletePath);

        $submissionFile = SubmissionFileProcessor::process(
            $data->locale,
            $this->user->getId(),
            $submissionId,
            $galleyCompletePath,
            $genreId,
            $item['id'],
        );

        // Now that we have the submission file ID, it's time to process the galley itself.
        $galleyId = GalleyProcessor::process($submissionFile->getId(), $data, $label, $publicationId, $galleyExtension);
        SubmissionFileProcessor::updateAssocInfo($submissionFile, $galleyId);
    }

    /**
     * Tracks a processed preprint for version management
     */
    private function trackProcessedPreprint(object $data, Submission $submission, Publication $publication): void
    {
        $identifier = $data->versionIdentifier;
        $version = (int)$data->version;

        if (!isset($this->processedPreprints[$identifier])) {
            $this->processedPreprints[$identifier] = [];
        }

        $this->processedPreprints[$identifier][$version] = [
            'data' => $data,
            'submission' => $submission,
            'publication' => $publication
        ];
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

            foreach ($versions as $versionKey => $versionData) {
                $versionNumber = (int)$versionData['data']->version;
                if ($versionNumber > $highestVersion) {
                    $highestVersion = $versionNumber;
                    $currentVersionData = $versionData;
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
