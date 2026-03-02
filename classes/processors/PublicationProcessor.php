<?php

/**
 * @file plugins/importexport/csv/classes/processors/PublicationProcessor.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PublicationProcessor
 *
 * @ingroup plugins_importexport_csv
 *
 * @brief Processes the publication data into the database.
 */

namespace APP\plugins\importexport\csv\classes\processors;

use APP\facades\Repo;
use APP\plugins\importexport\csv\classes\validations\InvalidRowValidations;
use APP\publication\Publication;
use APP\server\Server;
use APP\server\ServerDAO;
use APP\submission\Submission;
use APP\file\PublicFileManager;
use PKP\file\FileManager;
use PKP\db\DAORegistry;
use Exception;

class PublicationProcessor
{
    /**
     * Create a temporary Publication without association with Submission.
     * This Publication will be used to create the Submission and then updated.
     */
    public static function createInitialPublication(object $data): Publication
    {
        $publication = Repo::publication()->newDataObject();

        $version = !empty($data->version) ? (int)$data->version : 1;
        $publication->setData('version', $version);
        $publication->setData('status', Submission::STATUS_PUBLISHED);
        $publication->setData('datePublished', $data->datePosted);
        $publication->setData('title', $data->preprintTitle, $data->locale);

        return $publication;
    }

    /** Update the Publication with all necessary data after the Submission is created. */
    public static function process(Submission $submission, object $data, Server $server, string $sourceDir): Publication
    {
        /** @var Publication */
        $submissionPublication = $submission->getCurrentPublication();

        $submissionPublication->setData('copyrightNotice', $server->getLocalizedData('copyrightNotice', $data->locale));

        if (!empty($data->preprintSubtitle)) {
            $submissionPublication->setData('subtitle', $data->preprintSubtitle, $data->locale);
        }

        if (!empty($data->preprintAbstract)) {
            $submissionPublication->setData('abstract', $data->preprintAbstract, $data->locale);
        }

        if (!empty($data->preprintPrefix)) {
            $submissionPublication->setData('prefix', $data->preprintPrefix, $data->locale);
        }

        if (!empty($data->references)) {
            $referencesString = static::getReferencesContent($data->references, $sourceDir);

            if (!empty($referencesString)) {
                $submissionPublication->setData('citationsRaw', $referencesString);
            }
        }

        $copyrightHolder = $data->copyrightHolder
            ?? $submission->_getContextLicenseFieldValue(null, Submission::PERMISSIONS_FIELD_COPYRIGHT_HOLDER, $submissionPublication);
        $copyrightYear = $data->copyrightYear ?? $submission->_getContextLicenseFieldValue(
            null,
            Submission::PERMISSIONS_FIELD_COPYRIGHT_YEAR,
            $submissionPublication
        );
        $licenseUrl = $data->licenseUrl ?? $submission->_getContextLicenseFieldValue(
            null,
            Submission::PERMISSIONS_FIELD_LICENSE_URL,
            $submissionPublication
        );

        $submissionPublication->setData('copyrightHolder', $copyrightHolder, $data->locale);
        $submissionPublication->setData('copyrightYear', $copyrightYear);
        $submissionPublication->setData('licenseUrl', $licenseUrl);

        if (!empty($data->doi)) {
            $submissionPublication->setStoredPubId('doi', $data->doi);
        }

        $oldPublication = Repo::publication()->get($submissionPublication->getId());
        Repo::publication()->dao->update($submissionPublication, $oldPublication);

        return Repo::publication()->get($submissionPublication->getId());
    }

    public static function updatePrimaryContactId(Publication $publication, int $authorId)
    {
        $publication->setData('primaryContactId', $authorId);
        Repo::publication()->dao->update($publication);
    }

    public static function updateCoverage(Publication $publication, string $coverage, string $locale)
    {
        $publication->setData('coverage', $coverage, $locale);
    }

    public static function setCoverImage(Publication $publication, array $coverImageData, string $locale): void
    {
        $publication->setData('coverImage', $coverImageData, $locale);
    }

    /**
     * Process and upload the cover image
     *
     * @throws Exception
     */
    public static function uploadCoverImage(
        object $data,
        int $serverId,
        string $sourceDir,
        PublicFileManager $publicFileManager,
        FileManager $fileManager
    ): string {
        $reason = InvalidRowValidations::validateCoverImageIsValid($data->coverImageFilename, $sourceDir);
        if (!is_null($reason)) {
            throw new Exception($reason);
        }

        $sanitizedCoverImageName = str_replace([' ', '_', ':'], '-', mb_strtolower($data->coverImageFilename));
        $sanitizedCoverImageName = preg_replace('/[^a-z0-9\.\-]+/', '', $sanitizedCoverImageName);

        // Use a secure 48-character random alphanumeric string as a prefix to avoid overwriting user files
        $randomPrefix = bin2hex(random_bytes(24));
        $sanitizedFileName = basename($sanitizedCoverImageName);
        $coverImageUploadName = $randomPrefix . '-' . $sanitizedFileName;
        $destFilePath = $publicFileManager->getContextFilesPath($serverId) . '/' . $coverImageUploadName;
        $srcFilePath = "{$sourceDir}/{$data->coverImageFilename}";

        $bookCoverImageSaved = $fileManager->copyFile($srcFilePath, $destFilePath);

        if (!$bookCoverImageSaved) {
            throw new Exception(__('plugin.importexport.csv.erroWhileSavingBookCoverImage'));
        }

        return $coverImageUploadName;
    }

    public static function updateCoverImage(Publication $publication, object $data, string $uploadName)
    {
        $coverImage = [
            'dateUploaded' => date('Y-m-d H:i:s'),
            'uploadName' => $uploadName,
            'altText' => $data->coverImageAltText ?? '',
        ];

        $publication->setData('coverImage', $coverImage, $data->locale);
    }

    public static function updateSectionId(Publication $publication, int $sectionId)
    {
        $publication->setData('sectionId', $sectionId);
    }

    /**
     * Process a versioned publication with CSV data
     * This method processes a publication that was created through OPS versioning mechanism
     * OPS versioning already copied all data from base version, we only update what changed
     */
    public static function processVersionedPublication(
        Publication $publication,
        object $data,
        Publication $basePublication,
        string $sourceDir
    ): Publication {
        // Update version and status
        $publication->setData('version', (int) $data->version);
        $publication->setData('status', Submission::STATUS_PUBLISHED);

        $datePosted = !empty($data->datePosted) ? $data->datePosted : $basePublication->getData('datePublished');
        $publication->setData('datePublished', $datePosted);

        $localizedFields = [
            'title' => 'preprintTitle',
            'subtitle' => 'preprintSubtitle',
            'abstract' => 'preprintAbstract',
            'prefix' => 'preprintPrefix',
            'coverage' => 'coverage',
            'copyrightHolder' => 'copyrightHolder',
        ];

        foreach ($localizedFields as $field => $csvField) {
            if (!empty($data->{$csvField})) {
                $publication->setData($field, $data->{$csvField}, $data->locale);
            } elseif ($basePublication->getLocalizedData($field, $data->locale)) {
                $publication->setData($field, $basePublication->getLocalizedData($field, $data->locale), $data->locale);
            }
        }

        $nonLocalizedFields = ['copyrightYear', 'licenseUrl'];
        foreach($nonLocalizedFields as $field) {
            if (!empty($data->{$field})) {
                $publication->setData($field, $data->{$field});
            } elseif ($basePublication->getData($field)) {
                $publication->setData($field, $basePublication->getData($field));
            }
        }

        if (!empty($data->doi)) {
            $publication->setStoredPubId('doi', $data->doi);
        }

        if (!empty($data->references)) {
            $referencesString = static::getReferencesContent($data->references, $sourceDir);

            if (!empty($referencesString)) {
                $publication->setData('citationsRaw', $referencesString);
            }
        } elseif (!empty($basePublication->getData('citationsRaw'))) {
            $citationsRaw = (string) $basePublication->getData('citationsRaw');
            $publication->setData('citationsRaw', $citationsRaw);
        }

        $oldPublication = Repo::publication()->get($publication->getId());
        Repo::publication()->dao->update($publication, $oldPublication);

        return $publication;
    }

    /**
     * Create a new publication version manually to avoid CLI context dependency
     * This is a simplified version of Repo::publication()->version() without context dependencies
     */
    public static function createPublicationVersion(Publication $basePublication, object $data, Server $server): Publication
    {
        $newPublication = Repo::publication()->newDataObject();
        $newPublication->setData('submissionId', $basePublication->getData('submissionId'));
        $newPublication->setData('version', (int)$data->version);
        $newPublication->setData('status', Submission::STATUS_PUBLISHED);
        $newPublication->setData('datePublished', null);
        $newPublication->setData('copyrightNotice', $server->getLocalizedData('copyrightNotice', $data->locale));
        $newPublication->setData('authors', []);
        $newPublication->setData('primaryContactId', null);
        $newPublication->stampModified();

        $localeFields = ['title', 'subtitle', 'abstract', 'prefix', 'copyrightHolder'];
        foreach ($localeFields as $localeField) {
            if ($basePubValue = $basePublication->getData($localeField, $data->locale)) {
                $newPublication->setData($localeField, $basePubValue, $data->locale);
            }
        }

        $nonLocaleFields = ['copyrightYear', 'licenseUrl'];
        foreach ($nonLocaleFields as $nonLocaleField) {
            if ($basePubValue = $basePublication->getData($nonLocaleField)) {
                $newPublication->setData($nonLocaleField, $basePubValue);
            }
        }

        if ($citationsRaw = $basePublication->getData('citationsRaw')) {
            $newPublication->setData('citationsRaw', (string) $citationsRaw);
        }

        $publicationId = Repo::publication()->dao->insert($newPublication);
        return Repo::publication()->get($publicationId);
    }

    /**
     * Process multi-locale publication data (adds new locale to existing publication)
     * This method updates an existing publication with data in a new locale
     */
    public static function processMultiLocalePublication(Publication $publication, object $data, Server $server): Publication
    {
        $localizedFields = [
            'title' => 'preprintTitle',
            'subtitle' => 'preprintSubtitle',
            'abstract' => 'preprintAbstract',
            'prefix' => 'preprintPrefix',
            'coverage' => 'coverage',
            'copyrightHolder' => 'copyrightHolder',
        ];

        foreach ($localizedFields as $field => $csvField) {
            if (!empty($data->{$csvField})) {
                $publication->setData($field, $data->{$csvField}, $data->locale);
            }
        }

        $publication->setData('copyrightNotice', $server->getLocalizedData('copyrightNotice', $data->locale));

        Repo::publication()->dao->update($publication);

        return $publication;
    }

    /**
     * Process references from a file and add them to the publication
     */
    public static function getReferencesContent(string $referencesFilename, string $sourceDir): string|false
    {
        $referencesFilePath = "{$sourceDir}/{$referencesFilename}";
        return file_get_contents($referencesFilePath);
    }

    /**
     * Update the VOR DOI for a publication.
     * When a VOR DOI is provided, it automatically sets the relationStatus to PUBLISHED (3).
     * The DOI is normalized to URL format (https://doi.org/...) before storing.
     */
    public static function updateVorDoi(Publication $publication, ?string $vorDoi): void
    {
        $normalizedDoi = InvalidRowValidations::normalizeVorDoi($vorDoi);

        $publication->setData('vorDoi', $normalizedDoi);
        $publication->setData('relationStatus', Publication::PUBLICATION_RELATION_PUBLISHED);
        Repo::publication()->dao->update($publication);
    }

    /**
     * Process supporting agencies for a new publication or new version
     */
    public static function processSupportingAgencies(object $data, Publication $publication, ?Publication $basePublication = null): void
    {
        if (empty($data->supportingAgencies) && !is_null($basePublication)) {
            $baseSupportingAgencies = $basePublication->getData('supportingAgencies');
            if (empty($baseSupportingAgencies)) {
                return;
            }

            Repo::publication()->edit($publication, ['supportingAgencies' => $baseSupportingAgencies]);
            return;
        }

        if (empty($data->supportingAgencies)) {
            return;
        }

        $agenciesList = [$data->locale => array_map('trim', explode(';', $data->supportingAgencies))];
        if (empty($agenciesList[$data->locale])) {
            return;
        }

        Repo::publication()->edit($publication, ['supportingAgencies' => $agenciesList]);
    }

    /**
     * Process supporting agencies for multi-locale import (adds agencies in new locale)
     */
    public static function processSupportingAgenciesMultiLocale(object $data, Publication $publication): void
    {
        if (empty($data->supportingAgencies)) {
            return;
        }

        $existingAgencies = $publication->getData('supportingAgencies') ?? [];

        $newAgencies = array_map('trim', explode(';', $data->supportingAgencies));
        $existingAgencies[$data->locale] = $newAgencies;

        Repo::publication()->edit($publication, ['supportingAgencies' => $existingAgencies]);
    }
}
