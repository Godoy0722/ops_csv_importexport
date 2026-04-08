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

use APP\plugins\importexport\csv\classes\cachedAttributes\CachedEntities;
use APP\plugins\importexport\csv\shared\processors\SectionsProcessor as SharedSectionsProcessor;
use APP\publication\Publication;
use APP\section\Section;
use APP\server\Server;

class SectionsProcessor extends SharedSectionsProcessor
{

    private static $defaultSectionTitle = "Preprints";
    private static $defaultSectionAbbrev = "PRE";

	public static function process(object $data, Server $server, Publication $publication): void
    {
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

        parent::newSectionToPublication($data, $server->getId(), $publication);
	}
}
