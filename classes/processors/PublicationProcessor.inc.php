<?php

/**
 * @file plugins/importexport/csv/classes/processors/PublicationProcessor.php
 *
 * Copyright (c) 2014-2025 Simon Fraser University
 * Copyright (c) 2003-2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PublicationProcessor
 *
 * @ingroup plugins_importexport_csv
 *
 * @brief Processes the publication data into the database.
 */

namespace PKP\Plugins\ImportExport\CSV\Classes\Processors;

use PKP\Plugins\ImportExport\CSV\Classes\CachedAttributes\CachedDaos;

class PublicationProcessor
{
    /**
     * Processes initial data for Publication
	 *
	 * @param \Submission $submission
	 * @param object $data
	 * @param \Journal $journal
	 *
	 * @return \Publication
	 */
    public static function process($submission, $data, $journal)
    {
		$publicationDao = CachedDaos::getPublicationDao();
		$sanitizedAbstract = \PKPString::stripUnsafeHtml($data->preprintAbstract);
		$locale = $data->locale;

		/** @var \Publication $publication */
		$publication = $publicationDao->newDataObject();
        $publication->stampModified();
		$publication->setData('submissionId', $submission->getId());
		$publication->setData('version', 1);
		$publication->setData('status', STATUS_PUBLISHED);
		$publication->setData('datePublished', $data->datePosted);
		$publication->setData('abstract', $sanitizedAbstract, $locale);
		$publication->setData('title', $data->preprintTitle, $locale);
		$publication->setData('copyrightNotice', $journal->getLocalizedData('copyrightNotice', $locale), $locale);

        if ($data->preprintSubtitle) {
            $publication->setData('subtitle', $data->preprintSubtitle, $locale);
        }

        if ($data->preprintPrefix) {
            $publication->setData('prefix', $data->preprintPrefix, $locale);
        }

        $publicationDao->insertObject($publication);

		self::setCopyrightFromSystem($submission, $publication, $data);

        SubmissionProcessor::updateCurrentPublicationId($submission, $publication->getId());

        return $publication;
    }

    /**
     * Updates the primary contact ID for the publication
	 *
	 * @param \Publication $publication
	 * @param int $authorId
	 *
	 * @return void
     */
    public static function updatePrimaryContactId($publication, $authorId)
    {
        self::updatePublicationAttribute($publication, 'primaryContactId', $authorId);
    }

    /**
     * Updates the coverage for the publication
	 *
	 * @param \Publication $publication
	 * @param string $coverage
	 * @param string $locale
	 *
	 * @return void
     */
    public static function updateCoverage($publication, $coverage, $locale)
    {
        self::updatePublicationAttribute($publication, 'coverage', $coverage, $locale);
    }

    /**
     * Updates the cover image for the publication
	 *
	 * @param \Publication $publication
	 * @param object $data
	 * @param string $uploadName
	 *
	 * @return void
     */
    public static function updateCoverImage($publication, $data, $uploadName)
    {
        $coverImage = [
			'uploadName' => $uploadName,
			'altText' => $data->coverImageAltText ?? '',
		];

        self::updatePublicationAttribute($publication, 'coverImage', [$data->locale => $coverImage]);
    }

    /**
     * Updates the section ID for the publication
	 *
	 * @param \Publication $publication
	 * @param int $sectionId
	 *
	 * @return void
     */
    public static function updateSectionId($publication, $sectionId)
    {
        self::updatePublicationAttribute($publication, 'sectionId', $sectionId);
    }

    /**
     * Updates a specific attribute of the publication
	 *
	 * @param \Publication $publication
	 * @param string $attribute
	 * @param mixed $data
	 * @param string $locale
	 *
	 * @return void
     */
    static function updatePublicationAttribute($publication, $attribute, $data, $locale = null)
    {
        $publication->setData($attribute, $data, $locale);

        $publicationDao = CachedDaos::getPublicationDao();
        $publicationDao->updateObject($publication);
    }

	/**
	 * Set copyright data for the publication
	 *
	 * @param \Submission $submission
	 * @param \Publication $publication
	 * @param object $data
	 *
	 * @return void
	 *
	 */
	private static function setCopyrightFromSystem($submission, &$publication, $data): void
    {
        $copyrightHolder = $data->copyrightHolder ?? $submission->_getContextLicenseFieldValue(
            null,
            PERMISSIONS_FIELD_COPYRIGHT_HOLDER,
            $publication
        );
		self::updatePublicationAttribute($publication, 'copyrightHolder', $copyrightHolder, $data->locale);

        $copyrightYear = $data->copyrightYear ?? $submission->_getContextLicenseFieldValue(
            null,
            PERMISSIONS_FIELD_COPYRIGHT_YEAR,
            $publication
        );
		self::updatePublicationAttribute($publication, 'copyrightYear', $copyrightYear);

        $licenseUrl =  $data->licenseUrl ?? $submission->_getContextLicenseFieldValue(
            null,
            PERMISSIONS_FIELD_LICENSE_URL,
            $publication
        );
		self::updatePublicationAttribute($publication, 'licenseUrl', $licenseUrl);
    }

	/**
     * Create a new publication version manually to avoid CLI context dependency
	 *
	 * @param \Publication $basePublication
	 * @param object $data
	 *
	 * @return \Publication
     */
    public static function createPublicationVersion($basePublication, $data)
    {
        $newPublication = clone $basePublication;
        $newPublication->setData('id', null);
        $newPublication->setData('datePublished', null);
        $newPublication->setData('status', STATUS_PUBLISHED);
        $newPublication->setData('version', (int)$data->version);
        $newPublication->stampModified();

		$publicationDao = CachedDaos::getPublicationDao();
        $newPublicationId = $publicationDao->insertObject($newPublication);

        $authors = $basePublication->getData('authors');

        if (empty($authors)) {
            return $newPublication;
        }

        $newPublication->setData('authors', []);
        $newPublication->setData('primaryContactId', null);
		$publicationDao->updateObject($newPublication);

		$newPublication = $publicationDao->getById($newPublicationId);

        return $newPublication;
    }

	/**
     * Process a versioned publication with CSV data
     * This method processes a publication that was created through OJS versioning mechanism
     * OJS versioning already copied all data from base version, we only update what changed
	 *
	 * @param \Publication $publication
	 * @param object $data
	 * @param \Publication $basePublication
	 *
	 * @return \Publication
     */
    public static function processVersionedPublication($publication, $data, $basePublication)
    {
        self::updatePublicationAttribute($publication, 'version', (int)$data->version);
        self::updatePublicationAttribute($publication, 'status', STATUS_PUBLISHED);

		$localizedFields = [
			'title' => 'preprintTitle',
			'subtitle' => 'preprintSubtitle',
			'abstract' => 'preprintAbstract',
			'prefix' => 'preprintPrefix',
			'coverage' => 'coverage',
			'copyrightHolder' => 'copyrightHolder',
		];

		foreach($localizedFields as $field => $csvField) {
			if (!empty($data->{$csvField})) {
				self::updatePublicationAttribute($publication, $field, $data->{$csvField}, $data->locale);
			} elseif ($basePublication->getLocalizedData($field, $data->locale)) {
				self::updatePublicationAttribute($publication, $field, $basePublication->getLocalizedData($field, $data->locale), $data->locale);
			}
		}

		$nonLocaleFields = ['copyrightYear', 'licenseUrl', 'datePublished'];

		foreach($nonLocaleFields as $nonLocaleField) {
			if (!empty($data->{$nonLocaleField})) {
				self::updatePublicationAttribute($publication, $nonLocaleField, $data->{$nonLocaleField});
			} elseif ($basePublication->getData($nonLocaleField)) {
				self::updatePublicationAttribute($publication, $nonLocaleField, $basePublication->getData($nonLocaleField));
			}
		}

		if (!empty($data->doi)) {
            self::updatePublicationAttribute($publication, 'pub-id::doi', $data->doi);
        }

        return $publication;
    }

	/**
     * Copy galleys from a base publication to a new publication version
     * This mimics the behavior of OJS native versioning when creating new versions
     *
     * @param \Publication $newPublication The new publication version
     * @param \Publication $basePublication The base publication to copy from
     *
     * @return void
     */
    public static function copyGalleysFromBasePublication($newPublication, $basePublication)
    {
        $galleyDao = CachedDaos::getArticleGalleyDao();

        // Load galleys directly from the database to ensure we have the latest data
        $galleysResultFactory = $galleyDao->getByPublicationId($basePublication->getId());
        $galleys = $galleysResultFactory->toArray();

        if (empty($galleys)) {
            return;
        }

        foreach ($galleys as $galley) {
            $newGalley = clone $galley;
            $newGalley->setData('id', null);
            $newGalley->setData('publicationId', $newPublication->getId());
            $galleyDao->insertObject($newGalley);
        }

        // Refresh the publication with the new galleys
        $publicationDao = CachedDaos::getPublicationDao();
        $refreshedPublication = $publicationDao->getById($newPublication->getId());
		$newPublication->setData('galleys', $refreshedPublication->getData('galleys'));
	}
}
