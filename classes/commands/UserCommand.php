<?php

/**
 * @file plugins/importexport/csv/classes/commands/UserCommand.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
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
use APP\plugins\importexport\csv\classes\handlers\CSVFileHandler;
use APP\plugins\importexport\csv\classes\handlers\WelcomeEmailHandler;
use APP\plugins\importexport\csv\classes\processors\UserGroupsProcessor;
use APP\plugins\importexport\csv\classes\processors\UserInterestsProcessor;
use APP\plugins\importexport\csv\classes\processors\UsersProcessor;
use APP\plugins\importexport\csv\classes\validations\InvalidRowValidations;
use APP\plugins\importexport\csv\classes\validations\RequiredUserHeaders;
use PKP\security\Validation;
use PKP\user\User;

class UserCommand
{
    /** Expected row size for a CSV based on the command passed as argument */
    private int $expectedRowSize;

    /** The folder containing all CSV files that the command must go through */
    private string $sourceDir;

    private int $processedRows;

    private int $failedRows;

    private bool $sendWelcomeEmail;

    private User $senderEmailUser;

    public function __construct(string $sourceDir, User $user, bool $sendWelcomeEmail)
    {
        $this->expectedRowSize = count(RequiredUserHeaders::$userHeaders);
        $this->sourceDir = $sourceDir;
        $this->senderEmailUser = $user;
        $this->sendWelcomeEmail = $sendWelcomeEmail;
    }

    public function run(): void
    {
        foreach (new \DirectoryIterator($this->sourceDir) as $fileInfo) {
            if (!$fileInfo->isFile() || $fileInfo->getExtension() !== 'csv') {
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

            $invalidCsvFile = CSVFileHandler::createCSVFileInvalidRows($this->sourceDir, "invalid_{$basename}", RequiredUserHeaders::$userHeaders);
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

                $fieldsList = array_pad(array_map('trim', $fields), $this->expectedRowSize, null);
                $data = (object) array_combine(RequiredUserHeaders::$userHeaders, $fieldsList);

                $reason = InvalidRowValidations::validateRowHasAllRequiredFields($data, [RequiredUserHeaders::class, 'validateRowHasAllRequiredFields']);
                if (!is_null($reason)) {
                    CSVFileHandler::processFailedRow($invalidCsvFile, $fields, $this->expectedRowSize, $reason, $this->failedRows);
                    continue;
                }

                $server = CachedEntities::getCachedServer($data->serverPath);

                $reason = InvalidRowValidations::validateServerIsValid($server, $data->serverPath);
                if (!is_null($reason)) {
                    CSVFileHandler::processFailedRow($invalidCsvFile, $fields, $this->expectedRowSize, $reason, $this->failedRows);
                    continue;
                }

                $existingUserByEmail = CachedEntities::getCachedUserByEmail($data->email);
                if (!is_null($existingUserByEmail)) {
                    CSVFileHandler::processFailedRow($invalidCsvFile, $fields, $this->expectedRowSize, __('plugins.importexport.csv.userAlreadyExistsWithEmail', ['email' => $data->email]), $this->failedRows);
                    continue;
                }

                if ($data->username) {
                    $existingUserByUsername = CachedEntities::getCachedUserByUsername($data->username);
                    if (!is_null($existingUserByUsername)) {
                        CSVFileHandler::processFailedRow($invalidCsvFile, $fields, $this->expectedRowSize, __('plugins.importexport.csv.userAlreadyExistsWithUsername', ['username' => $data->username]), $this->failedRows);
                        continue;
                    }
                }

                $roles = array_map('trim', explode(';', $data->roles));

                $reason = InvalidRowValidations::validateAllUserGroupsAreValid($roles, $server->getId(), $server->getPrimaryLocale());
                if (!is_null($reason)) {
                    CSVFileHandler::processFailedRow($invalidCsvFile, $fields, $this->expectedRowSize, $reason, $this->failedRows);
                    continue;
                }

                if (!empty($data->orcid)) {
                    $reason = InvalidRowValidations::validateOrcid($data->orcid);
                    if (!is_null($reason)) {
                        CSVFileHandler::processFailedRow($invalidCsvFile, $fields, $this->expectedRowSize, $reason, $this->failedRows);
                        continue;
                    }
                }

                // Generate password if tempPassword column is empty
                // User will need to use password reset function to receive a reset link
                if (is_null($data->tempPassword)) {
                    $data->tempPassword = Validation::generatePassword();
                }

                $user = UsersProcessor::process($data, $server->getPrimaryLocale());
                $userId = $user->getId();
                $userInterests = array_map('trim', explode(';', $data->reviewInterests));
                UserInterestsProcessor::process($userInterests, $userId);
                UserGroupsProcessor::process($roles, $userId, $server->getId(), $server->getPrimaryLocale());

                // Only send welcome email if explicitly requested via CLI flag
                if ($this->sendWelcomeEmail) {
                    WelcomeEmailHandler::sendWelcomeEmail($server, $user, $this->senderEmailUser, $data->tempPassword);
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
    }
}
