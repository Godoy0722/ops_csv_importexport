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
use APP\plugins\importexport\csv\classes\processors\FundersProcessor;
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
        $suppFilenamesArray = array_map('trim', explode(';', $suppFilenames));
        $suppLabelsArray = array_map('trim', explode(';', $suppLabels));

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
     * Validates the supplementary descriptions count. Returns the reason if an error occurred,
     * or null if everything is correct.
     */
    public static function validateSupplementaryDescriptions(string $suppFilenames, string $suppLabels, ?string $suppDescriptions): ?string
    {
        if (empty($suppDescriptions)) {
            return null; // descriptions are optional
        }

        $suppFilenamesArray = array_map('trim', explode(';', $suppFilenames));
        $suppLabelsArray = array_map('trim', explode(';', $suppLabels));
        $suppDescriptionsArray = array_map('trim', explode(';', $suppDescriptions));

        if (
            count($suppDescriptionsArray) !== count($suppFilenamesArray) ||
            count($suppDescriptionsArray) !== count($suppLabelsArray)
        ) {
            return __('plugins.importexport.csv.invalidNumberOfDescriptionsAndSupplementaryFiles');
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
     * Validates that no duplicate version exists for the same preprint identifier,
     * version, and locale combination in the current import session
     */
    public static function validateNoDuplicateVersion(object $data, array $processedPreprints): ?string
    {
        $identifier = $data->versionIdentifier;
        $version = (int)$data->version;
        $locale = $data->locale;

        if (isset($processedPreprints[$identifier][$version][$locale])) {
            return __('plugins.importexport.csv.duplicatePreprintVersionLocaleFound', [
                'identifier' => $identifier,
                'version' => $version,
                'locale' => $locale
            ]);
        }

        return null;
    }

    /**
     * Checks if a version exists in any locale (used for multi-locale imports)
     */
    public static function versionExistsInAnyLocale(object $data, array $processedPreprints): bool
    {
        $identifier = $data->versionIdentifier;
        $version = (int)$data->version;

        return isset($processedPreprints[$identifier][$version]) &&
               !empty($processedPreprints[$identifier][$version]);
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

    /**
     * Validates the ORCID value. Returns the reason if an error occurred,
     * or null if everything is correct.
     *
     * Accepts the following formats:
     * - Full URL: https://orcid.org/0000-0002-1825-0097 or https://sandbox.orcid.org/0000-0002-1825-0097
     * - Dashed format: 0000-0002-1825-0097
     * - Numeric format: 0000000218250097
     * - Can end with X (checksum character)
     */
    public static function validateOrcid(?string $orcid): ?string
    {
        if (empty($orcid)) {
            return null;
        }

        $normalizedOrcid = self::normalizeOrcid($orcid);

        if ($normalizedOrcid === null) {
            return __('plugins.importexport.csv.invalidOrcidFormat', ['orcid' => $orcid]);
        }

        $digits = preg_replace('/[^0-9X]/', '', $normalizedOrcid);

        if (strlen($digits) !== 16) {
            return __('plugins.importexport.csv.invalidOrcidFormat', ['orcid' => $orcid]);
        }

        if (!self::validateOrcidChecksum($digits)) {
            return __('plugins.importexport.csv.invalidOrcidChecksum', ['orcid' => $orcid]);
        }

        return null;
    }

    /**
     * Normalizes an ORCID value to the full URL format.
     */
    public static function normalizeOrcid(string $orcid): ?string
    {
        $orcid = trim($orcid);

        if (empty($orcid)) {
            return null;
        }

        if (preg_match('/^https:\/\/(sandbox\.)?orcid\.org\/(\d{4})-(\d{4})-(\d{4})-(\d{3}[0-9X])$/', $orcid)) {
            return $orcid;
        }

        if (preg_match('/^(\d{4})-(\d{4})-(\d{4})-(\d{3}[0-9X])$/', $orcid)) {
            return 'https://orcid.org/' . $orcid;
        }

        if (preg_match('/^(\d{15}[0-9X])$/', $orcid)) {
            $formatted = substr($orcid, 0, 4) . '-' .
                         substr($orcid, 4, 4) . '-' .
                         substr($orcid, 8, 4) . '-' .
                         substr($orcid, 12, 4);
            return 'https://orcid.org/' . $formatted;
        }

        return null;
    }

    /**
     * Validates the ORCID checksum using ISNI algorithm.
     */
    private static function validateOrcidChecksum(string $digits): bool
    {
        $total = 0;
        for ($i = 0; $i < 15; $i++) {
            $total = ($total + (int) $digits[$i]) * 2;
        }

        $remainder = $total % 11;
        $result = (12 - $remainder) % 11;
        $expectedCheckDigit = ($result === 10) ? 'X' : (string) $result;

        return $digits[15] === $expectedCheckDigit;
    }

    /**
     * Validates the VOR DOI field. Returns the reason if an error occurred,
     * or null if everything is correct.
     *
     * Accepted formats:
     * - Full URL: https://doi.org/10.1234/example
     * - DOI identifier: 10.1234/example
     * - With doi: prefix: doi:10.1234/example
     */
    public static function validateVorDoi(?string $vorDoi): ?string
    {
        if (empty($vorDoi)) {
            return null;
        }

        $normalizedDoi = self::normalizeVorDoi($vorDoi);

        if ($normalizedDoi === null) {
            return __('plugins.importexport.csv.invalidVorDoiFormat', ['vorDoi' => $vorDoi]);
        }

        return null;
    }

    /**
     * Normalizes a VOR DOI value to the full URL format.
     */
    public static function normalizeVorDoi(?string $vorDoi): ?string
    {
        if (empty($vorDoi)) {
            return null;
        }

        $vorDoi = trim($vorDoi);

        // Already a valid DOI URL (https://doi.org/... or http://doi.org/... or https://dx.doi.org/...)
        if (preg_match('/^https?:\/\/(dx\.)?doi\.org\/10\.\d{4,}(\.\d+)*\/\S+$/i', $vorDoi)) {
            // Normalize to https://doi.org format
            return preg_replace('/^https?:\/\/(dx\.)?doi\.org\//i', 'https://doi.org/', $vorDoi);
        }

        // DOI with doi: prefix (doi:10.1234/example)
        if (preg_match('/^doi:(10\.\d{4,}(\.\d+)*\/\S+)$/i', $vorDoi, $matches)) {
            return 'https://doi.org/' . $matches[1];
        }

        // Just the DOI identifier (10.1234/example)
        if (preg_match('/^10\.\d{4,}(\.\d+)*\/\S+$/', $vorDoi)) {
            return 'https://doi.org/' . $vorDoi;
        }

        return null;
    }

    /**
     * Validates the funders string format. Returns the reason if an error occurred,
     * or null if everything is correct.
     *
     * Funder format: "FunderName,FunderIdentification,Award1|Award2;FunderName2,FunderIdentification2,Award3"
     * - Each funder is separated by `;`
     * - Funder fields are separated by `,`
     * - Multiple awards for the same funder are separated by `|`
     */
    public static function validateFunders(?string $fundersString): ?string
    {
        if (empty($fundersString)) {
            return null;
        }

        $fundersArray = array_map('trim', explode(';', $fundersString));

        foreach ($fundersArray as $index => $funderString) {
            if (empty($funderString)) {
                continue;
            }

            $funderParts = array_map('trim', explode(',', $funderString));
            $funderName = $funderParts[0] ?? '';

            if (empty($funderName)) {
                return __('plugins.importexport.csv.invalidFunderFormat', ['index' => $index + 1]);
            }
        }

        return null;
    }

    /**
     * Validates that the Funding plugin is enabled when funders data is provided.
     * Returns an error message if funders data is present but the plugin is not enabled,
     * or null if everything is correct.
     */
    public static function validateFundingPluginEnabled(?string $fundersString, int $contextId): ?string
    {
        if (empty($fundersString)) {
            return null;
        }

        if (!FundersProcessor::isFundingPluginEnabled($contextId)) {
            return __('plugins.importexport.csv.fundingPluginNotEnabled');
        }

        return null;
    }

    /**
     * Validates that all funders have valid Crossref registry identifications.
     * This validation is only applied when the Funding plugin's 'enableGrantIdValidation'
     * setting is enabled for the context.
     *
     * Returns an error message if any funder lacks a valid Crossref DOI,
     * or null if everything is correct (or if validation is disabled).
     */
    public static function validateFundersCrossrefRegistry(?string $fundersString, int $contextId): ?string
    {
        if (empty($fundersString)) {
            return null;
        }

        if (!FundersProcessor::isCrossrefValidationEnabled($contextId)) {
            return null;
        }

        $fundersArray = array_map('trim', explode(';', $fundersString));

        foreach ($fundersArray as $index => $funderString) {
            if (empty($funderString)) {
                continue;
            }

            $funderParts = array_map('trim', explode(',', $funderString));
            $funderName = $funderParts[0] ?? '';
            $funderIdentification = $funderParts[1] ?? '';

            if (empty($funderName)) {
                continue;
            }

            // Check if the funder identification contains a valid Crossref Funder Registry DOI
            // Valid formats: https://doi.org/10.13039/... or http://dx.doi.org/10.13039/...
            if (!empty($funderIdentification)) {
                $hasCrossrefDoi = preg_match('/https?:\/\/(dx\.)?doi\.org\/10\.13039\//', $funderIdentification);
                if (!$hasCrossrefDoi) {
                    return __('plugins.importexport.csv.funderNotInCrossrefRegistry', [
                        'funderName' => $funderName,
                        'index' => $index + 1
                    ]);
                }
            } else {
                // Funder identification is required for Crossref registry validation
                return __('plugins.importexport.csv.funderMissingCrossrefId', [
                    'funderName' => $funderName,
                    'index' => $index + 1
                ]);
            }
        }

        return null;
    }
}
