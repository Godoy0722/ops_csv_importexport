<?php

/**
 * @file plugins/importexport/csv/classes/validations/InvalidRowValidations.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
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
use APP\plugins\importexport\csv\classes\exceptions\RowValidationException;
use APP\plugins\importexport\csv\classes\processors\FundersProcessor;
use APP\server\Server;
use APP\core\Application;
use APP\publication\Publication;

class InvalidRowValidations
{

    /** @var string[] */
    static array $coverImageAllowedTypes = ['gif', 'jpg', 'png', 'webp'];

    /**
     * Validates whether the email is valid.
     *
     * @throws RowValidationException
     */
    public static function validateEmail(string $email): void
    {
        if (empty($email)) {
            return;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RowValidationException(__('plugins.importexport.csv.invalidEmail', ['email' => $email]));
        }
    }

    /**
     * Validates whether the CSV row contains all fields.
     *
     * @throws RowValidationException
     */
    public static function validateRowContainAllFields(array $fields, int $expectedSize): void
    {
        $fieldCount = count($fields);

        if ($fieldCount < $expectedSize) {
            throw new RowValidationException(__('plugins.importexport.csv.rowDoesntContainAllFields'));
        }

        if ($fieldCount > $expectedSize) {
            throw new RowValidationException(__('plugins.importexport.csv.rowContainsTooManyFields'));
        }
    }

    /**
     * Validates whether the CSV row contains all required fields.
     *
     * @throws RowValidationException
     */
    public static function validateRowHasAllRequiredFields(object $data, callable $requiredFieldsValidation): void
    {
        if (!$requiredFieldsValidation($data)) {
            throw new RowValidationException(__('plugins.importexport.csv.verifyRequiredFieldsForThisRow'));
        }
    }

    /**
     * Validates whether the preprint file exists and is readable.
     *
     * @throws RowValidationException
     */
    public static function validatePreprintFileIsValid(string $coverImageFilename, string $sourceDir): void
    {
        $preprintCoverImagePath = "{$sourceDir}/{$coverImageFilename}";

        if (!is_readable($preprintCoverImagePath)) {
            throw new RowValidationException(__('plugins.importexport.csv.invalidPreprintFile'));
        }
    }

    /**
     * Validates the preprint cover image.
     *
     * @throws RowValidationException
     */
    public static function validateCoverImageIsValid(string $coverImageFilename, string $sourceDir): void
    {
        $preprintCoverImagePath = "{$sourceDir}/{$coverImageFilename}";

        if (!is_readable($preprintCoverImagePath)) {
            throw new RowValidationException(__('plugins.importexport.csv.invalidPreprintCoverImage'));
        }

        $coverImgExtension = mb_strtolower(pathinfo($coverImageFilename, PATHINFO_EXTENSION));

        if (!in_array($coverImgExtension, static::$coverImageAllowedTypes)) {
            throw new RowValidationException(__('plugins.importexport.csv.invalidFileExtension'));
        }
    }

    /**
     * Perform all necessary validations for preprint galleys.
     *
     * @throws RowValidationException
     */
    public static function validatePreprintGalleys(string $galleyFilenames, string $galleyLabels, string $sourceDir): void
    {
        $galleyFilenamesArray = explode(';', $galleyFilenames);
        $galleyLabelsArray = explode(';', $galleyLabels);

        if (count($galleyFilenamesArray) !== count($galleyLabelsArray)) {
            throw new RowValidationException(__('plugins.importexport.csv.invalidNumberOfLabelsAndGalleys'));
        }

        foreach($galleyFilenamesArray as $galleyFilename) {
            $galleyPath = "{$sourceDir}/{$galleyFilename}";
            if (!is_readable($galleyPath)) {
                throw new RowValidationException(__('plugins.importexport.csv.invalidGalleyFile', ['filename' => $galleyFilename]));
            }
        }
    }

    /**
     * Perform all necessary validations for supplementary files.
     *
     * @throws RowValidationException
     */
    public static function validateSupplementaryFiles(string $suppFilenames, string $suppLabels, string $sourceDir): void
    {
        $suppFilenamesArray = array_map('trim', explode(';', $suppFilenames));
        $suppLabelsArray = array_map('trim', explode(';', $suppLabels));

        if (count($suppFilenamesArray) !== count($suppLabelsArray)) {
            throw new RowValidationException(__('plugins.importexport.csv.invalidNumberOfLabelsAndSupplementaryFiles'));
        }

        foreach($suppFilenamesArray as $suppFilename) {
            $suppPath = "{$sourceDir}/{$suppFilename}";
            if (!is_readable($suppPath)) {
                throw new RowValidationException(__('plugins.importexport.csv.invalidSupplementaryFile', ['filename' => $suppFilename]));
            }
        }
    }

    /**
     * Validates the supplementary descriptions count.
     *
     * @throws RowValidationException
     */
    public static function validateSupplementaryDescriptions(string $suppFilenames, string $suppLabels, ?string $suppDescriptions): void
    {
        if (empty($suppDescriptions)) {
            return; // descriptions are optional
        }

        $suppFilenamesArray = array_map('trim', explode(';', $suppFilenames));
        $suppLabelsArray = array_map('trim', explode(';', $suppLabels));
        $suppDescriptionsArray = array_map('trim', explode(';', $suppDescriptions));

        if (
            count($suppDescriptionsArray) !== count($suppFilenamesArray) ||
            count($suppDescriptionsArray) !== count($suppLabelsArray)
        ) {
            throw new RowValidationException(__('plugins.importexport.csv.invalidNumberOfDescriptionsAndSupplementaryFiles'));
        }
    }

    /**
     * Validates whether the server is valid for the CSV row.
     *
     * @throws RowValidationException
     */
    public static function validateServerIsValid(?Server $server, string $serverPath): void
    {
        if (!$server) {
            throw new RowValidationException(__('plugins.importexport.csv.unknownServer', ['serverPath' => $serverPath]));
        }
    }

    /**
     * Validates if the server supports the locale provided in the CSV row.
     *
     * @throws RowValidationException
     */
    public static function validateServerLocale(Server $server, string $locale): void
    {
        $supportedLocales = $server->getSupportedSubmissionLocales();
        if (!is_array($supportedLocales) || count($supportedLocales) < 1) {
            $supportedLocales = [$server->getPrimaryLocale()];
        }
        if (!in_array($locale, $supportedLocales)) {
            throw new RowValidationException(__('plugins.importexport.csv.unknownLocale', ['locale' => $locale, 'supportedLocales' => implode(', ', $supportedLocales)]));
        }
    }

    /**
     * Validates if a genre exists for the name provided in the CSV row.
     *
     * @throws RowValidationException
     */
    public static function validateGenreIdValid(?int $genreId, string $genreName): void
    {
        if (!$genreId) {
            throw new RowValidationException(__('plugins.importexport.csv.noGenre', ['genreName' => $genreName]));
        }
    }

    /**
     * Validates if the user group ID is valid.
     *
     * @throws RowValidationException
     */
    public static function validateUserGroupId(?int $userGroupId, string $serverPath): void
    {
        if (!$userGroupId) {
            throw new RowValidationException(__('plugins.importexport.csv.noAuthorGroup', ['server' => $serverPath]));
        }
    }

    /**
     * Validates if all user groups are valid.
     *
     * @throws RowValidationException
     */
    public static function validateAllUserGroupsAreValid(array $roles, int $serverId, string $locale): void
    {
        $userGroups = CachedEntities::getCachedUserGroupsByServerId($serverId);

        $allDbRoles = 0;
        foreach ($roles as $role) {
            $matchingGroups = array_filter($userGroups, fn($userGroup) => mb_strtolower($userGroup->name[$locale]) === mb_strtolower($role));
            $allDbRoles += count($matchingGroups);
        }

        if ($allDbRoles !== count($roles)) {
            throw new RowValidationException(__('plugins.importexport.csv.roleDoesntExist', ['role' => $role]));
        }
    }

    /**
     * Validates preprint versioning fields.
     *
     * @throws RowValidationException
     */
    public static function validatePreprintVersioningFields(object $data): void
    {
        if (!empty($data->versionIdentifier) && empty($data->version)) {
            throw new RowValidationException(__('plugins.importexport.csv.versionRequiredWhenIdentifierProvided'));
        }

        if (!empty($data->version)) {
            if (!is_numeric($data->version) || (int)$data->version < 1) {
                throw new RowValidationException(__('plugins.importexport.csv.versionMustBePositiveInteger'));
            }
        }
    }

     /**
     * Validates that no duplicate version exists for the same preprint identifier,
     * version, and locale combination in the current import session
     *
     * @throws RowValidationException
     */
    public static function validateNoDuplicateVersion(object $data, array $processedPreprints): void
    {
        $identifier = $data->versionIdentifier;
        $version = (int)$data->version;
        $locale = $data->locale;

        if (isset($processedPreprints[$identifier][$version][$locale])) {
            throw new RowValidationException(__('plugins.importexport.csv.duplicatePreprintVersionLocaleFound', [
                'identifier' => $identifier,
                'version' => $version,
                'locale' => $locale
            ]));
        }
    }

    /**
     * Checks if a version exists in any locale (used for multi-locale imports)
     */
    public static function versionExistsInAnyLocale(object $data, array $processedPreprints): bool
    {
        $identifier = $data->versionIdentifier;
        $version = (int)$data->version;

        return !empty($processedPreprints[$identifier][$version]);
    }

    /**
     * Validates the references file.
     *
     * @throws RowValidationException
     */
    public static function validateReferencesFile(?string $referencesFilename, string $sourceDir): void
    {
        if (empty($referencesFilename)) {
            return; // References file is optional
        }

        $referencesFilePath = "{$sourceDir}/{$referencesFilename}";

        if (!is_readable($referencesFilePath)) {
            throw new RowValidationException(__('plugins.importexport.csv.invalidReferencesFile', ['filename' => $referencesFilename]));
        }

        $extension = pathinfo(mb_strtolower($referencesFilename), PATHINFO_EXTENSION);
        if ($extension !== 'txt') {
            throw new RowValidationException(__('plugins.importexport.csv.invalidReferencesFileExtension'));
        }
    }

    /**
     * Validates the ORCID value.
     *
     * Accepts the following formats:
     * - Full URL: https://orcid.org/0000-0002-1825-0097 or https://sandbox.orcid.org/0000-0002-1825-0097
     * - Dashed format: 0000-0002-1825-0097
     * - Numeric format: 0000000218250097
     * - Can end with X (checksum character)
     *
     * @throws RowValidationException
     */
    public static function validateOrcid(?string $orcid): void
    {
        if (empty($orcid)) {
            return;
        }

        $normalizedOrcid = static::normalizeOrcid($orcid);

        if ($normalizedOrcid === null) {
            throw new RowValidationException(__('plugins.importexport.csv.invalidOrcidFormat', ['orcid' => $orcid]));
        }

        // Extract just the ORCID ID from the URL before digit validation
        // This prevents the 'x' in 'sandbox' from being counted as a digit
        $orcidId = preg_replace('/^https?:\/\/(sandbox\.)?orcid\.org\//', '', $normalizedOrcid);
        $digits = preg_replace('/[^0-9X]/i', '', $orcidId);

        if (strlen($digits) !== 16) {
            throw new RowValidationException(__('plugins.importexport.csv.invalidOrcidFormat', ['orcid' => $orcid]));
        }

        if (!static::validateOrcidExists($normalizedOrcid)) {
            throw new RowValidationException(__('plugins.importexport.csv.orcidNotFound', ['orcid' => $orcid]));
        }
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

        if (preg_match('/^https:\/\/(sandbox\.)?orcid\.org\/(\d{4})-(\d{4})-(\d{4})-(\d{3}[0-9X])$/i', $orcid)) {
            return $orcid;
        }

        if (preg_match('/^(\d{4})-(\d{4})-(\d{4})-(\d{3}[0-9X])$/i', $orcid)) {
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
     * Validates if the ORCID entry exists via web request.
     */
    private static function validateOrcidExists(string $orcid): bool
    {
        try {
            $client = Application::get()->getHttpClient();
            $response = $client->request('HEAD', $orcid, [
                'http_errors' => true,
                'connect_timeout' => 5,
                'headers' => [
                    'Accept' => 'application/json, application/xml, text/html'
                ]
            ]);

            return $response->getStatusCode() === 200;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Validates the VOR DOI field.
     *
     * Accepted formats:
     * - Full URL: https://doi.org/10.1234/example
     * - DOI identifier: 10.1234/example
     * - With doi: prefix: doi:10.1234/example
     *
     * @throws RowValidationException
     */
    public static function validateVorDoi(?string $vorDoi): void
    {
        if (empty($vorDoi)) {
            return;
        }

        $normalizedDoi = static::normalizeVorDoi($vorDoi);

        if ($normalizedDoi === null) {
            throw new RowValidationException(__('plugins.importexport.csv.invalidVorDoiFormat', ['vorDoi' => $vorDoi]));
        }
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
     * Validates the funders string format.
     *
     * Funder format: "FunderName,FunderIdentification,Award1|Award2;FunderName2,FunderIdentification2,Award3"
     * - Each funder is separated by `;`
     * - Funder fields are separated by `,`
     * - Multiple awards for the same funder are separated by `|`
     *
     * @throws RowValidationException
     */
    public static function validateFunders(?string $fundersString): void
    {
        if (empty($fundersString)) {
            return;
        }

        $fundersArray = array_map('trim', explode(';', $fundersString));

        foreach ($fundersArray as $index => $funderString) {
            if (empty($funderString)) {
                continue;
            }

            $funderParts = array_map('trim', explode(',', $funderString));
            $funderName = $funderParts[0] ?? '';

            if (empty($funderName)) {
                throw new RowValidationException(__('plugins.importexport.csv.invalidFunderFormat', ['index' => $index + 1]));
            }
        }
    }

    /**
     * Validates that the Funding plugin is enabled when funders data is provided.
     * Throws RowValidationException if funders data is present but the plugin is not enabled.
     *
     * @throws RowValidationException
     */
    public static function validateFundingPluginEnabled(?string $fundersString, int $contextId): void
    {
        if (empty($fundersString)) {
            return;
        }

        if (!FundersProcessor::isFundingPluginEnabled($contextId)) {
            throw new RowValidationException(__('plugins.importexport.csv.fundingPluginNotEnabled'));
        }
    }

    /**
     * Validates that all funders have valid Crossref registry identifications.
     * This validation is only applied when the Funding plugin's 'enableGrantIdValidation'
     * setting is enabled for the context.
     *
     * Throws RowValidationException if any funder lacks a valid Crossref DOI.
     *
     * @throws RowValidationException
     */
    public static function validateFundersCrossrefRegistry(?string $fundersString, int $contextId): void
    {
        if (empty($fundersString)) {
            return;
        }

        if (!FundersProcessor::isCrossrefValidationEnabled($contextId)) {
            return;
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
                $hasCrossrefDoi = preg_match('/https?:\/\/(dx\.)?doi\.org\/10\.13039\//i', $funderIdentification);
                if (!$hasCrossrefDoi) {
                    throw new RowValidationException(__('plugins.importexport.csv.funderNotInCrossrefRegistry', [
                        'funderName' => $funderName,
                        'index' => $index + 1
                    ]));
                }
            } else {
                // Funder identification is required for Crossref registry validation
                throw new RowValidationException(__('plugins.importexport.csv.funderMissingCrossrefId', [
                    'funderName' => $funderName,
                    'index' => $index + 1
                ]));
            }
        }
    }

    /**
     * Validates whether a user already exists with the given username.
     *
     * @throws RowValidationException
     */
    public static function validateUserAlreadyExistsWithThisUsername(string $username): void
    {
        $existingUserByUsername = CachedEntities::getCachedUserByUsername($username);
        if (!is_null($existingUserByUsername)) {
            throw new RowValidationException(__('plugins.importexport.csv.userAlreadyExistsWithUsername', ['username' => $username]));
        }
    }

    /**
     * Validates whether a user already exists with the given email.
     *
     * @throws RowValidationException
     */
    public static function validateUserAlreadyExistsWithThisEmail(string $email): void
    {
        $existingUserByEmail = CachedEntities::getCachedUserByEmail($email);
        if (!is_null($existingUserByEmail)) {
            throw new RowValidationException(__('plugins.importexport.csv.userAlreadyExistsWithEmail', ['email' => $email]));
        }
    }

    /**
     * Validates if the publication was successfully retrieved or created.
     *
     * @throws RowValidationException
     */
    public static function validatePublicationWasSuccessfullyCreated(?Publication $publication): void
    {
        if (!$publication) {
            throw new RowValidationException(__('plugins.importexport.csv.errorWhileCreatingPublication'));
        }
    }

    /**
     * Validates the preprintViews field.
     * Must be empty or a non-negative integer.
     *
     * @throws RowValidationException
     */
    public static function validatePreprintViews(?string $preprintViews): void
    {
        if (empty($preprintViews)) {
            return;
        }

        if (!ctype_digit($preprintViews)) {
            throw new RowValidationException(__('plugins.importexport.csv.invalidPreprintViews'));
        }
    }

    /**
     * Validates the galleyViews field.
     * If provided, must have the same count of semicolon-separated values as galleyLabels,
     * and each non-empty value must be a non-negative integer.
     *
     * @throws RowValidationException
     */
    public static function validateGalleyViews(?string $galleyViews, ?string $galleyLabels): void
    {
        if (empty($galleyViews)) {
            return;
        }

        if (empty($galleyLabels)) {
            throw new RowValidationException(__('plugins.importexport.csv.galleyViewsWithoutGalleys'));
        }

        $galleyViewsArray = explode(';', $galleyViews);
        $galleyLabelsArray = explode(';', $galleyLabels);

        if (count($galleyViewsArray) !== count($galleyLabelsArray)) {
            throw new RowValidationException(__('plugins.importexport.csv.invalidNumberOfGalleyViews'));
        }

        foreach ($galleyViewsArray as $value) {
            $value = trim($value);
            if ($value === '') {
                continue;
            }
            if (!ctype_digit($value)) {
                throw new RowValidationException(__('plugins.importexport.csv.invalidGalleyViewValue', ['value' => $value]));
            }
        }
    }

    /**
     * Validates that section fields are either both filled or both empty.
     * If one is provided, both must be provided.
     *
     * @throws RowValidationException
     */
    public static function validateSectionFields(object $data): void
    {
        if (empty($data->sectionTitle) xor empty($data->sectionAbbrev)) {
            throw new RowValidationException(__('plugins.importexport.csv.incompleteSectionFields'));
        }
    }
}
