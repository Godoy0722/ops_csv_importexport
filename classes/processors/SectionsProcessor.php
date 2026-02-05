<?php

/**
 * @file plugins/importexport/csv/classes/processors/SectionsProcessor.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SectionsProcessor
 *
 * @ingroup plugins_importexport_csv
 *
 * @brief Processes the section data into the database.
 */

namespace APP\plugins\importexport\csv\classes\processors;

use APP\facades\Repo;
use APP\plugins\importexport\csv\classes\cachedAttributes\CachedEntities;
use APP\publication\Publication;
use APP\section\Section;
use APP\server\Server;

class SectionsProcessor
{

    private static $defaultSectionTitle = "Preprints";
    private static $defaultSectionAbbrev = "PRE";

    /**
     * Get the default section for a server (first active section, or first section if none active)
     */
    public static function getDefaultSection(int $serverId): ?Section
    {
        $sections = Repo::section()->getCollector()
            ->filterByContextIds([$serverId])
            ->getMany();

        // Prefer an active section
        foreach ($sections as $section) {
            if (!$section->getIsInactive()) {
                return $section;
            }
        }

        // Fall back to first section (even if inactive)
        return $sections->first();
    }

	public static function process(object $data, Server $server, Publication $publication): void
    {

        // Fallback section for empty CSV section fields - Preprints always exist
        if (!$data->sectionTitle) {
            /** @var Section */
            $section = CachedEntities::getCachedSection(
                static::$defaultSectionTitle,
                static::$defaultSectionAbbrev,
                $server->getPrimaryLocale(),
                $server->getId()
            );

            PublicationProcessor::updateSectionId($publication, $section->getId());
            return;
        }

        $section = CachedEntities::getCachedSection(
            $data->sectionTitle,
            $data->sectionAbbrev,
            $data->locale,
            $server->getId()
        );

        if (!is_null($section)) {
            PublicationProcessor::updateSectionId($publication, $section->getId());
            return;
        }

        $section = Repo::section()->newDataObject();

        $section->setContextId($server->getId());
        $section->setSequence(REALLY_BIG_NUMBER);
        $section->setEditorRestricted(false);
        $section->setMetaIndexed(true);
        $section->setMetaReviewed(true);
        $section->setAbstractsNotRequired(false);
        $section->setAbstractWordCount(REALLY_BIG_NUMBER);
        $section->setHideTitle(false);
        $section->setHideAuthor(false);
        $section->setIsInactive(false);
        $section->setTitle($data->sectionTitle, $data->locale);
        $section->setAbbrev(mb_strtoupper(trim($data->sectionAbbrev)), $data->locale);
        $section->setPath(mb_strtolower(trim($data->sectionAbbrev)));
        $section->setIdentifyType('', $data->locale);
        $section->setPolicy('', $data->locale);

        $sectionId = Repo::section()->add($section);

        $createdSection = Repo::section()->get($sectionId, $server->getId());
        $customSectionKey = $data->sectionTitle . '_' . mb_strtoupper(trim($data->sectionAbbrev));
        CachedEntities::$sections[$customSectionKey] = $createdSection;

        PublicationProcessor::updateSectionId($publication, $sectionId);
	}
}
