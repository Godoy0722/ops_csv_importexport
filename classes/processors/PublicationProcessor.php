<?php

/**
 * @file plugins/importexport/csv/classes/processors/PublicationProcessor.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
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
use APP\publication\Publication;
use APP\server\Server;
use APP\submission\Submission;

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
            $referencesString = self::getReferencesContent($data->references, $sourceDir);

            if (!empty($referencesString)) {
                $submissionPublication->setData('citationsRaw', $referencesString);
            }
        }

        $oldPublication = Repo::publication()->get($submissionPublication->getId());
        Repo::publication()->dao->update($submissionPublication, $oldPublication);

        self::setCopyrightFromSystem($submission, $submissionPublication, $data);

        return $submissionPublication;
    }

    public static function updatePrimaryContactId(Publication $publication, int $authorId)
    {
        self::updatePublicationAttribute($publication, 'primaryContactId', $authorId);
    }

    public static function updateCoverage(Publication $publication, string $coverage, string $locale)
    {
        self::updatePublicationAttribute($publication, 'coverage', $coverage, $locale);
    }

    public static function updateCoverImage(Publication $publication, object $data, string $uploadName)
    {
        $coverImage = [
            'dateUploaded' => date('Y-m-d H:i:s'),
            'uploadName' => $uploadName,
            'altText' => $data->coverImageAltText ?? '',
        ];

        $localizedCoverImage = [];
        $localizedCoverImage['coverImage'] = [];
        $localizedCoverImage['coverImage'][$data->locale] = $coverImage;

        $newPublication = Repo::publication()->newDataObject(array_merge($publication->_data, $localizedCoverImage));
        $newPublication->stampModified();
        Repo::publication()->dao->update($newPublication, $publication);
    }

    public static function updateSectionId(Publication $publication, int $sectionId)
    {
        self::updatePublicationAttribute($publication, 'sectionId', $sectionId);
    }

    static function updatePublicationAttribute(Publication $publication, string $attribute, mixed $data, ?string $locale = null)
    {
        if (!is_null($locale)) {
            $publication->setData($attribute, $data, $locale);
            Repo::publication()->dao->update($publication);
            return;
        }

        $publication->setData($attribute, $data);
        Repo::publication()->dao->update($publication);
    }

    private static function setCopyrightFromSystem(
        Submission $submission,
        Publication &$publication,
        object $data
    ): void
    {
        $copyrightHolder = $data->copyrightHolder
            ?? $submission->_getContextLicenseFieldValue(null, Submission::PERMISSIONS_FIELD_COPYRIGHT_HOLDER, $publication
        );
        self::updatePublicationAttribute($publication, 'copyrightHolder', $copyrightHolder, $data->locale);

        $copyrightYear = $data->copyrightYear ?? $submission->_getContextLicenseFieldValue(
            null,
            Submission::PERMISSIONS_FIELD_COPYRIGHT_YEAR,
            $publication
        );
        self::updatePublicationAttribute($publication, 'copyrightYear', $copyrightYear);

        $licenseUrl = $data->licenseUrl ?? $submission->_getContextLicenseFieldValue(
            null,
            Submission::PERMISSIONS_FIELD_LICENSE_URL,
            $publication
        );
        self::updatePublicationAttribute($publication, 'licenseUrl', $licenseUrl);
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
        self::updatePublicationAttribute($publication, 'version', (int)$data->version);
        self::updatePublicationAttribute($publication, 'status', Submission::STATUS_PUBLISHED);

        $datePosted = !empty($data->datePosted) ? $data->datePosted : $basePublication->getData('datePublished');
        self::updatePublicationAttribute($publication, 'datePublished', $datePosted);

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
                self::updatePublicationAttribute($publication, $field, $data->{$csvField}, $data->locale);
            } elseif ($basePublication->getLocalizedData($field, $data->locale)) {
                self::updatePublicationAttribute($publication, $field, $basePublication->getLocalizedData($field, $data->locale), $data->locale);
            }
        }

        $nonLocalizedFields = ['copyrightYear', 'licenseUrl'];

        foreach($nonLocalizedFields as $field) {
            if (!empty($data->{$field})) {
                self::updatePublicationAttribute($publication, $field, $data->{$field});
            } elseif ($basePublication->getData($field)) {
                self::updatePublicationAttribute($publication, $field, $basePublication->getData($field));
            }
        }

        if (!empty($data->doi)) {
            self::updatePublicationAttribute($publication, 'pub-id::doi', $data->doi);
        }

        if (!empty($data->references)) {
            $referencesString = self::getReferencesContent($data->references, $sourceDir);

            if (!empty($referencesString)) {
                $publication->setData('citationsRaw', $referencesString);
            }
        } elseif (!empty($basePublication->getData('citationsRaw'))) {
            $citationsRaw = (string)$basePublication->getData('citationsRaw');
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
    public static function createPublicationVersion(Publication $basePublication, object $data): Publication
    {
        $newPublication = clone $basePublication;
        $newPublication->setData('id', null);
        $newPublication->setData('datePublished', null);
        $newPublication->setData('status', Submission::STATUS_PUBLISHED);
        $newPublication->setData('version', (int)$data->version);
        $newPublication->stampModified();

        $publicationId = Repo::publication()->dao->insert($newPublication);
        $newPublication = Repo::publication()->get($publicationId);

        $authors = $basePublication->getData('authors');

        if (empty($authors)) {
            return $newPublication;
        }

        $newPublication->setData('authors', []);
        $newPublication->setData('primaryContactId', null);
        Repo::publication()->dao->update($newPublication);

        $citationsRaw = $basePublication->getData('citationsRaw');
        if (!empty($citationsRaw)) {
            $newPublication->setData('citationsRaw', (string)$citationsRaw);
            $oldPublication = Repo::publication()->get($publicationId);

            Repo::publication()->dao->update($newPublication, $oldPublication);

            $newPublication = Repo::publication()->get($publicationId);
        }

        return $newPublication;
    }

    /**
     * Process references from a file and add them to the publication
     */
    public static function getReferencesContent(string $referencesFilename, string $sourceDir): string|false
    {
        $referencesFilePath = "{$sourceDir}/{$referencesFilename}";
        return file_get_contents($referencesFilePath);
    }
}
