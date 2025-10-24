<?php

/**
 * @file plugins/importexport/csv/classes/validations/InvalidRowValidations.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
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
     * Validates whether the preprint file exists and is readable. Returns the reason if an error occurred,
     * or null if everything is correct.
     */
    public static function validatePreprintFileIsValid(string $coverImageFilename, string $sourceDir): ?string
    {
        $preprintCoverImagePath = "{$sourceDir}/{$coverImageFilename}";

        return !is_readable($preprintCoverImagePath)
            ? __('plugins.importexport.csv.invalidPreprintFile')
            : null;
    }

    /**
     * Validates the preprint cover image. Returns the reason if an error occurred,
     * or null if everything is correct.
     */
    public static function validateCoverImageIsValid(string $coverImageFilename, string $sourceDir): ?string
    {
        $preprintCoverImagePath = "{$sourceDir}/{$coverImageFilename}";

        if (!is_readable($preprintCoverImagePath)) {
            return __('plugins.importexport.csv.invalidBookCoverImage');
        }

        $coverImgExtension = pathinfo(mb_strtolower($coverImageFilename), PATHINFO_EXTENSION);

        if (!in_array($coverImgExtension, self::$coverImageAllowedTypes)) {
            return __('plugins.importexport.csv.invalidFileExtension');
        }

        return null;
    }

    /**
     * Perform all necessary validations for preprint galleys. Returns the reason if an error occurred,
     * or null if everything is correct.
     */
    public static function validatePreprintGalleys(string $galleyFilenames, string $galleyLabels, string $sourceDir): ?string
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
    public static function validateServerLocale(Server $server, string $locale): ?string
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
        return !$userGroupId
            ? __('plugins.importexport.csv.noAuthorGroup', ['journal' => $serverPath])
            : null;
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
                return mb_strtolower($userGroup->name[$locale]) === mb_strtolower($role);
            });
            $allDbRoles += count($matchingGroups);
        }

        return $allDbRoles !== count($roles)
            ? __('plugins.importexport.csv.roleDoesntExist', ['role' => $role])
            : null;
    }

    public static function validatePreprintVersioningFields(object $data): ?string
    {
        if (!empty($data->versionIdentifier) && empty($data->version)) {
            return __('plugins.importexport.csv.versionRequiredWhenIdentifierProvided');
        }

        if (!empty($data->version)) {
            if (!is_numeric($data->version) || (int)$data->version < 1) {
                return __('plugins.importexport.csv.versionMustBePositiveInteger');
            }
        }

        return null;
    }

     /**
     * Validates that no duplicate version exists for the same preprint identifier
     * in the current import session
     */
    public static function validateNoDuplicateVersion(object $data, array $processedPreprints): ?string
    {
        $identifier = $data->versionIdentifier;
        $version = (int)$data->version;

        if (isset($processedPreprints[$identifier][$version])) {
            return __('plugins.importexport.csv.duplicatePreprintVersionFound', [
                'identifier' => $identifier,
                'version' => $version
            ]);
        }

        return null;
    }

    /**
     * Validates the references file. Returns the reason if an error occurred,
     * or null if everything is correct.
     */
    public static function validateReferencesFile(?string $referencesFilename, string $sourceDir): ?string
    {
        if (empty($referencesFilename)) {
            return null; // References file is optional
        }

        $referencesFilePath = "{$sourceDir}/{$referencesFilename}";

        if (!is_readable($referencesFilePath)) {
            return __('plugins.importexport.csv.invalidReferencesFile', ['filename' => $referencesFilename]);
        }

        $extension = pathinfo(mb_strtolower($referencesFilename), PATHINFO_EXTENSION);
        if ($extension !== 'txt') {
            return __('plugins.importexport.csv.invalidReferencesFileExtension');
        }

        return null;
    }
}
