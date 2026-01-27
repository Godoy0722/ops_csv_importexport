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
            $baseSubjects = $basePublication->getData('subjects');

            if (empty($baseSubjects)) {
                return;
            }

            Repo::publication()->edit($publication, ['subjects' => $baseSubjects]);
            return;
        }

		$subjectsList = [$data->locale => array_map('trim', explode(';', $data->subjects))];

		if (empty($subjectsList[$data->locale])) {
            return;
        }

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

        $existingSubjects = $publication->getData('subjects') ?? [];

        $newSubjects = array_map('trim', explode(';', $data->subjects));
        $existingSubjects[$data->locale] = $newSubjects;

        Repo::publication()->edit($publication, ['subjects' => $existingSubjects]);
    }
}
