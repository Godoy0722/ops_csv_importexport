<?php

/**
 * @file plugins/importexport/csv/classes/validations/RequiredPreprintHeaders.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class RequiredPreprintHeaders
 *
 * @ingroup plugins_importexport_csv
 *
 * @brief Class to validate headers in the preprint CSV files
 */

namespace APP\plugins\importexport\csv\classes\validations;

class RequiredPreprintHeaders
{
    static $preprintHeaders = [
        'serverPath',
        'locale',
        'preprintTitle',
        'preprintPrefix',
        'preprintSubtitle',
        'preprintAbstract',
        'authors',
        'keywords',
        'subjects',
        'coverage',
        'categories',
        'doi',
        'coverImageFilename',
        'coverImageAltText',
        'galleyFilenames',
        'galleyLabels',
        'suppFilenames',
        'suppLabels',
        'sectionTitle',
        'sectionAbbrev',
        'datePosted',
        'dateSubmitted',
        'copyrightYear',
		'copyrightHolder',
		'licenseUrl',
		'preprintIdentifier',
		'version',
    ];

    static $preprintRequiredHeaders = [
        'serverPath',
        'locale',
        'preprintTitle',
        'authors',
        'datePosted',
    ];

    public static function validateRowHasAllFields(array $row): bool
    {
        return count($row) === count(self::$preprintHeaders);
    }

    public static function validateRowHasAllRequiredFields(object $row): bool
    {
        if (!empty($row->preprintIdentifier) && !empty($row->version) && (int)$row->version > 1) {
            return true;
        }

        foreach (self::$preprintRequiredHeaders as $requiredHeader) {
            if (!$row->{$requiredHeader}) {
                return false;
            }
        }

        return true;
    }
}
