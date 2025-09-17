<?php

/**
 * @file plugins/importexport/csv/classes/validations/InvalidRowValidations.php
 *
 * Copyright (c) 2014-2025 Simon Fraser University
 * Copyright (c) 2003-2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class InvalidRowValidations
 *
 * @ingroup plugins_importexport_csv
 *
 * @brief Class to validate all necessary requirements for a CSV row to be valid
 */

namespace APP\plugins\importexport\csv\classes\validations;

use APP\plugins\importexport\csv\classes\cachedAttributes\CachedEntities;
use APP\server\Server;

class InvalidRowValidations
{

    /** @var string[] */
    static array $coverImageAllowedTypes = ['gif', 'jpg', 'png', 'webp'];

    /**
     * Validates whether the CSV row contains all fields. Returns the reason if an error occurred,
     * or null if everything is correct.
     */
    public static function validateRowContainAllFields(array $fields, int $expectedSize): ?string
    {
        return count($fields) < $expectedSize
            ? __('plugins.importexport.csv.rowDoesntContainAllFields')
            : null;
    }

    /**
     * Validates whether the CSV row contains all required fields. Returns the reason if an error occurred,
     * or null if everything is correct.
     */
    public static function validateRowHasAllRequiredFields(object $data, callable $requiredFieldsValidation): ?string
    {
        return !$requiredFieldsValidation($data)
            ? __('plugins.importexport.csv.verifyRequiredFieldsForThisRow')
            : null;
    }


    /**
     * Validates whether the article file exists and is readable. Returns the reason if an error occurred,
     * or null if everything is correct.
     */
    public static function validateArticleFileIsValid(string $coverImageFilename, string $sourceDir): ?string
    {
        $articleCoverImagePath = "{$sourceDir}/{$coverImageFilename}";

        return !is_readable($articleCoverImagePath)
            ? __('plugins.importexport.csv.invalidArticleFile')
            : null;
    }

    /**
     * Validates the article cover image. Returns the reason if an error occurred,
     * or null if everything is correct.
     */
    public static function validateCoverImageIsValid(string $coverImageFilename, string $sourceDir): ?string
    {
        $articleCoverImagePath = "{$sourceDir}/{$coverImageFilename}";

        if (!is_readable($articleCoverImagePath)) {
            return __('plugins.importexport.csv.invalidBookCoverImage');
        }

        $coverImgExtension = pathinfo(mb_strtolower($coverImageFilename), PATHINFO_EXTENSION);

        if (!in_array($coverImgExtension, self::$coverImageAllowedTypes)) {
            return __('plugins.importexport.csv.invalidFileExtension');
        }

        return null;
    }

    /**
     * Perform all necessary validations for article galleys. Returns the reason if an error occurred,
     * or null if everything is correct.
     */
    public static function validateArticleGalleys(string $galleyFilenames, string $galleyLabels, string $sourceDir): ?string
    {
        $galleyFilenamesArray = explode(';', $galleyFilenames);
        $galleyLabelsArray = explode(';', $galleyLabels);

        if (count($galleyFilenamesArray) !== count($galleyLabelsArray)) {
            return __('plugins.importexport.csv.invalidNumberOfLabelsAndGalleys');
        }

        foreach($galleyFilenamesArray as $galleyFilename) {
            $galleyPath = "{$sourceDir}/{$galleyFilename}";
            if (!is_readable($galleyPath)) {
                return __('plugins.importexport.csv.invalidGalleyFile', ['filename' => $galleyFilename]);
            }
        }

        return null;
    }

    /**
     * Perform all necessary validations for supplementary files. Returns the reason if an error occurred,
     * or null if everything is correct.
     */
    public static function validateSupplementaryFiles(string $suppFilenames, string $suppLabels, string $sourceDir): ?string
    {
        $suppFilenamesArray = explode(';', $suppFilenames);
        $suppLabelsArray = explode(';', $suppLabels);

        if (count($suppFilenamesArray) !== count($suppLabelsArray)) {
            return __('plugins.importexport.csv.invalidNumberOfLabelsAndSupplementaryFiles');
        }

        foreach($suppFilenamesArray as $suppFilename) {
            $suppPath = "{$sourceDir}/{$suppFilename}";
            if (!is_readable($suppPath)) {
                return __('plugins.importexport.csv.invalidSupplementaryFile', ['filename' => $suppFilename]);
            }
        }

        return null;
    }


    /**
     * Validates whether the server is valid for the CSV row. Returns the reason if an error occurred,
     * or null if everything is correct.
     */
    public static function validateServerIsValid(?Server $server, string $serverPath): ?string
    {
        return !$server ? __('plugins.importexport.csv.unknownServer', ['serverPath' => $serverPath]) : null;
    }

    /**
     * Validates if the server supports the locale provided in the CSV row. Returns the reason if an error occurred
     * or null if everything is correct.
     */
    public static function validateServerLocation(Server $server, string $locale): ?string
    {
        $supportedLocales = $server->getSupportedSubmissionLocales();
        if (!is_array($supportedLocales) || count($supportedLocales) < 1) {
            $supportedLocales = [$server->getPrimaryLocale()];
        }

        return !in_array($locale, $supportedLocales)
            ? __('plugins.importexport.csv.unknownLocale', ['locale' => $locale])
            : null;
    }

    /**
     * Validates if a genre exists for the name provided in the CSV row. Returns the reason if an error occurred
     * or null if everything is correct.
     */
    public static function validateGenreIdValid(?int $genreId, string $genreName): ?string
    {
        return !$genreId ? __('plugins.importexport.csv.noGenre', ['genreName' => $genreName]) : null;
    }

    /**
     * Validates if the user group ID is valid. Returns the reason if an error occurred
     * or null if everything is correct.
     */
    public static function validateUserGroupId(?int $userGroupId, string $serverPath): ?string
    {
        return !$userGroupId ? __('plugins.importexport.csv.noAuthorGroup', ['server' => $serverPath]) : null;
    }

    /**
     * Validates if all user groups are valid. Returns the reason if an error occurred
     * or null if everything is correct.
     */
    public static function validateAllUserGroupsAreValid(array $roles, int $serverId, string $locale): ?string
    {
        $userGroups = CachedEntities::getCachedUserGroupsByServerId($serverId);

        $allDbRoles = 0;
        foreach ($roles as $role) {
            $matchingGroups = array_filter($userGroups, function($userGroup) use ($role, $locale) {
                return mb_strtolower($userGroup->getName($locale)) === mb_strtolower($role);
            });
            $allDbRoles += count($matchingGroups);
        }

        return $allDbRoles !== count($roles)
            ? __('plugins.importexport.csv.roleDoesntExist', ['role' => $role])
            : null;
    }

    /**
     * Validates if the subscription dates are valid. Returns the reason if an error occurred
     * or null if everything is correct.
     */
    public static function validateSubscriptionDates(string $startDate, string $endDate, ?string $dateFormat = 'Y-m-d'): ?string
    {
        $startDateObj = \DateTime::createFromFormat($dateFormat, $startDate);
        if (!$startDateObj) {
            return __('plugins.importexport.csv.invalidStartDate', ['date' => $startDate]);
        }

        $endDateObj = \DateTime::createFromFormat($dateFormat, $endDate);
        if (!$endDateObj) {
            return __('plugins.importexport.csv.invalidEndDate', ['date' => $endDate]);
        }

        if ($endDateObj <= $startDateObj) {
            return __('plugins.importexport.csv.endDateBeforeStartDate');
        }

        return null;
    }
}
