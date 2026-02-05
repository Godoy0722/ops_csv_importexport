<?php

/**
 * @file plugins/importexport/csv/classes/processors/KeywordsProcessor.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class KeywordsProcessor
 *
 * @ingroup plugins_importexport_csv
 *
 * @brief Processes the keywords data into the database.
 */

namespace APP\plugins\importexport\csv\classes\processors;

use APP\facades\Repo;
use APP\publication\Publication;

class KeywordsProcessor
{
    public static function process(object $data, Publication $publication, ?Publication $basePublication = null)
    {
        if (empty($data->keywords) && !is_null($basePublication)) {
            $baseKeywords = $basePublication->getData('keywords', $data->locale);

            // Filter out null/empty values
            if (is_array($baseKeywords)) {
                $baseKeywords = array_filter($baseKeywords, fn($keyword) => !is_null($keyword) && $keyword !== '');
            }

            if (empty($baseKeywords)) {
                return;
            }

            // Wrap in locale array to match expected structure
            Repo::publication()->edit($publication, ['keywords' => [$data->locale => array_values($baseKeywords)]]);
            return;
        }

		$keywords = array_filter(array_map('trim', explode(';', $data->keywords)), fn($keyword) => $keyword !== '');

        if (empty($keywords)) {
            return;
        }

        $keywordsList = [$data->locale => array_values($keywords)];

        Repo::publication()->edit($publication, ['keywords' => $keywordsList]);
	}

    /**
     * Process keywords for multi-locale import (adds keywords in new locale)
     */
    public static function processMultiLocale(object $data, Publication $publication): void
    {
        if (empty($data->keywords)) {
            return; // No new keywords to add
        }

        $newKeywords = array_filter(array_map('trim', explode(';', $data->keywords)), fn($keyword) => $keyword !== '');

        if (empty($newKeywords)) {
            return;
        }

        $existingKeywords = $publication->getData('keywords') ?? [];
        $existingKeywords[$data->locale] = array_values($newKeywords);

        Repo::publication()->edit($publication, ['keywords' => $existingKeywords]);
    }
}
