<?php

/**
 * @file plugins/importexport/csv/classes/processors/SubjectsProcessor.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubjectsProcessor
 *
 * @ingroup plugins_importexport_csv
 *
 * @brief Processes the subjects data into the database.
 */

namespace APP\plugins\importexport\csv\classes\processors;

use APP\facades\Repo;
use APP\publication\Publication;

class SubjectsProcessor
{
	public static function process(object $data, Publication $publication, ?Publication $basePublication = null)
    {
        if (empty($data->subjects) && !is_null($basePublication)) {
            $baseSubjects = $basePublication->getData('subjects', $data->locale);

            // Filter out null/empty values
            if (is_array($baseSubjects)) {
                $baseSubjects = array_filter($baseSubjects, fn($subject) => !is_null($subject) && $subject !== '');
            }

            if (empty($baseSubjects)) {
                return;
            }

            // Wrap in locale array to match expected structure
            Repo::publication()->edit($publication, ['subjects' => [$data->locale => array_values($baseSubjects)]]);
            return;
        }

		$subjects = array_filter(array_map('trim', explode(';', $data->subjects)), fn($subject) => $subject !== '');

		if (empty($subjects)) {
            return;
        }

        $subjectsList = [$data->locale => array_values($subjects)];

        Repo::publication()->edit($publication, ['subjects' => $subjectsList]);
	}

    /**
     * Process subjects for multi-locale import (adds subjects in new locale)
     */
    public static function processMultiLocale(object $data, Publication $publication): void
    {
        if (empty($data->subjects)) {
            return; // No new subjects to add
        }

        $newSubjects = array_filter(array_map('trim', explode(';', $data->subjects)), fn($subject) => $subject !== '');

        if (empty($newSubjects)) {
            return;
        }

        $existingSubjects = $publication->getData('subjects') ?? [];
        $existingSubjects[$data->locale] = array_values($newSubjects);

        Repo::publication()->edit($publication, ['subjects' => $existingSubjects]);
    }
}
