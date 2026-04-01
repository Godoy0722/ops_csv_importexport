<?php

/**
 * @file plugins/importexport/csv/classes/commands/UserCommand.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UserCommand
 *
 * @ingroup plugins_importexport_csv
 *
 * @brief Handles the issue import when the user uses the issue command
 */

namespace APP\plugins\importexport\csv\classes\commands;

use APP\plugins\importexport\csv\classes\cachedAttributes\CachedEntities;
use APP\plugins\importexport\csv\classes\exceptions\RowValidationException;
use APP\plugins\importexport\csv\classes\handlers\CsvFileHandler;
use APP\plugins\importexport\csv\classes\handlers\DryModeReporter;
use APP\plugins\importexport\csv\classes\handlers\WelcomeEmailHandler;
use APP\plugins\importexport\csv\classes\processors\UserGroupsProcessor;
use APP\plugins\importexport\csv\classes\processors\UserInterestsProcessor;
use APP\plugins\importexport\csv\classes\processors\UsersProcessor;
use APP\plugins\importexport\csv\classes\validations\InvalidRowValidations;
use APP\plugins\importexport\csv\classes\validations\RequiredUserHeaders;
use Illuminate\Support\Facades\DB;
use PKP\security\Validation;
use PKP\user\User;

class UserCommand
{
    /** Expected row size for a CSV based on the command passed as argument */
    private int $expectedRowSize;

    private int $processedRows;

    private int $failedRows;

    public function __construct(
        private string $sourceDir,
        private User $senderEmailUser,
        private bool $sendWelcomeEmail,
        private bool $dryMode = false
    ) {
        $this->expectedRowSize = count(RequiredUserHeaders::$userHeaders);
    }

    public function run(): int
    {
        $totalFiles = 0;
        $totalPassed = 0;
        $totalFailed = 0;

        foreach (new \DirectoryIterator($this->sourceDir) as $fileInfo) {
            if (!$fileInfo->isFile() || mb_strtolower($fileInfo->getExtension()) !== 'csv') {
                continue;
            }

            // Skip invalid_*.csv files created by previous failed imports
            $basename = $fileInfo->getBasename();
            if (str_starts_with($basename, 'invalid_')) {
                echo __('plugins.importexport.csv.skippingInvalidFile', ['filename' => $basename]) . "\n";
                continue;
            }

            $filePath = $fileInfo->getPathname();
            $file = CsvFileHandler::createReadableCSVFile($filePath);
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

                    $fieldsList = array_pad(array_map('trim', $fields), $this->expectedRowSize, null);
                    $data = (object) array_combine(RequiredUserHeaders::$userHeaders, $fieldsList);

                    InvalidRowValidations::validateRowHasAllRequiredFields($data, [RequiredUserHeaders::class, 'validateRowHasAllRequiredFields']);

                    $server = CachedEntities::getCachedServer($data->serverPath);

                    InvalidRowValidations::validateServerIsValid($server, $data->serverPath);
                    InvalidRowValidations::validateUserAlreadyExistsWithThisEmail($data->email);

                    if ($data->username) {
                        InvalidRowValidations::validateUserAlreadyExistsWithThisUsername($data->username);
                    }

                    $roles = array_map('trim', explode(';', $data->roles ?? ''));

                    InvalidRowValidations::validateAllUserGroupsAreValid($roles, $server->getId(), $server->getPrimaryLocale());

                    if (!empty($data->orcid)) {
                        InvalidRowValidations::validateOrcid($data->orcid);
                    }

                    // Generate password if tempPassword column is empty
                    // User will need to use password reset function to receive a reset link
                    if (is_null($data->tempPassword)) {
                        $data->tempPassword = Validation::generatePassword();
                    }

                    $user = UsersProcessor::process($data, $server->getPrimaryLocale());
                    $userId = $user->getId();
                    $userInterests = array_map('trim', explode(';', $data->reviewInterests ?? ''));
                    UserInterestsProcessor::process($userInterests, $userId);
                    UserGroupsProcessor::process($roles, $userId, $server->getId(), $server->getPrimaryLocale());

                    if ($this->sendWelcomeEmail && !$this->dryMode) {
                        // @review There were some discussions about strategies for mail delivery
                        WelcomeEmailHandler::sendWelcomeEmail($server, $user, $this->senderEmailUser, $data->tempPassword);
                    }
                } catch (RowValidationException $e) {
                    if (is_null($invalidCsvFile)) {
                        $invalidCsvFile = CsvFileHandler::createCSVFileInvalidRows($this->sourceDir, "invalid_{$basename}", RequiredUserHeaders::$userHeaders);
                        if (is_null($invalidCsvFile)) {
                            continue 2;
                        }
                    }
                    CsvFileHandler::processFailedRow($invalidCsvFile, $fields, $this->expectedRowSize, $e->getMessage(), $this->failedRows);
                    if ($this->dryMode) {
                        $fileFailedRows[] = ['row' => $this->processedRows, 'reason' => $e->getMessage()];
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
            }

            echo __('plugins.importexpot.csv.fileProcessFinished', [
                'filename' => $fileInfo->getFilename(),
                'processedRows' => $this->processedRows,
                'failedRows' => $this->failedRows,
            ]) . "\n";
        }

        if ($this->dryMode) {
            DryModeReporter::printGrandTotal($totalFiles, $totalPassed, $totalFailed);
            return $totalFailed > 0 ? 1 : 0;
        }

        return 0;
    }
}
