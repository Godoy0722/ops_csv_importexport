<?php

/**
 * @file plugins/importexport/csv/classes/validations/RequiredIssueHeaders.php
 *
 * Copyright (c) 2014-2025 Simon Fraser University
 * Copyright (c) 2003-2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class RequiredIssueHeaders
 *
 * @ingroup plugins_importexport_csv
 *
 * @brief Class to validate headers in the issue CSV files
 */

namespace APP\plugins\importexport\csv\classes\validations;

class RequiredPreprintHeaders
{
    static $preprintHeaders = [
        'serverPath',
        'locale',
        'versionIdentifier',
		'version',
        'preprintPrefix',
        'preprintTitle',
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
        'references',
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

    public static function validateRowHasAllRequiredFields(object $row, array $processedPreprints = []): bool
    {
        if (!empty($row->versionIdentifier) && !empty($row->version) && !empty($row->locale)) {
            $identifier = $row->versionIdentifier;
            $version = (int)$row->version;
            $locale = $row->locale;

            if (
                isset($processedPreprints[$identifier][$version])
                && !isset($processedPreprints[$identifier][$version][$locale])
            ) {
                return true;
            }

            if ($version > 1) {
                return true;
            }
        }

        foreach (self::$preprintRequiredHeaders as $requiredHeader) {
            if (!$row->{$requiredHeader}) {
                return false;
            }
        }

        return true;
    }
}
