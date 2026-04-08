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
 * @brief OPS-specific publication processor for preprint CSV imports.
 * Extends the shared PublicationProcessor with preprint field names and OPS-only features
 * such as VOR DOI handling and cover image uploads.
 */

namespace APP\plugins\importexport\csv\classes\processors;

use APP\facades\Repo;
use \APP\plugins\importexport\csv\shared\processors\PublicationProcessor as SharedPublicationProcessor;
use APP\publication\Publication;
use APP\server\Server;
use APP\submission\Submission;

class PublicationProcessor extends SharedPublicationProcessor
{
    private const LOCALIZED_FIELDS = [
            'title' => 'preprintTitle',
            'subtitle' => 'preprintSubtitle',
            'abstract' => 'preprintAbstract',
            'prefix' => 'preprintPrefix',
            'coverage' => 'coverage',
            'copyrightHolder' => 'copyrightHolder',
        ];

    private const NON_LOCALIZED_FIELDS = ['copyrightYear', 'licenseUrl'];

    /** Extend the shared base with OPS-specific datePosted and preprintTitle. */
    public static function createInitialPublication(object $data): Publication
    {
        $publication = parent::createInitialPublication($data);

        $publication->setData('datePublished', $data->datePosted);
        $publication->setData('title', $data->preprintTitle, $data->locale);

        return $publication;
    }

    /** Add preprint-specific fields (subtitle, abstract, prefix) after shared processing. */
    public static function process(Submission $submission, object $data, Server $server, string $sourceDir): Publication
    {
        $submissionPublication = parent::processCommons($submission, $data, $server, $sourceDir);

        if (!empty($data->preprintSubtitle)) {
            $submissionPublication->setData('subtitle', $data->preprintSubtitle, $data->locale);
        }

        if (!empty($data->preprintAbstract)) {
            $submissionPublication->setData('abstract', static::normalizeAbstractToHtml($data->preprintAbstract), $data->locale);
        }

        if (!empty($data->preprintPrefix)) {
            $submissionPublication->setData('prefix', $data->preprintPrefix, $data->locale);
        }

        $oldPublication = Repo::publication()->get($submissionPublication->getId());
        Repo::publication()->dao->update($submissionPublication, $oldPublication);

        return Repo::publication()->get($submissionPublication->getId());
    }

    /** Apply preprint field map and OPS-specific datePosted to a versioned publication. */
    public static function processVersionedPublication(
        Publication $publication,
        object $data,
        Publication $basePublication,
        string $sourceDir
    ): Publication {
        parent::processVersionedPublicationCommons(
            $publication,
            $data,
            $basePublication,
            $sourceDir,
            static::LOCALIZED_FIELDS,
            static::NON_LOCALIZED_FIELDS
        );

        $datePosted = !empty($data->datePosted) ? $data->datePosted : $basePublication->getData('datePublished');
        $publication->setData('datePublished', $datePosted);

        $oldPublication = Repo::publication()->get($publication->getId());
        Repo::publication()->dao->update($publication, $oldPublication);

        return $publication;
    }

    /** Delegate to shared multi-locale logic with the preprint field map. */
    public static function processMultiLocalePublication(Publication $publication, object $data, Server $server): Publication
    {
        return parent::processMultiLocalePublicationCommons(
            $publication,
            $data,
            $server,
            static::LOCALIZED_FIELDS,
            static::NON_LOCALIZED_FIELDS
        );
    }
}
