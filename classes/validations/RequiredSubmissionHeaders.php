<?php

/**
 * @file plugins/importexport/csv/classes/validations/RequiredIssueHeaders.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class RequiredIssueHeaders
 *
 * @ingroup plugins_importexport_csv
 *
 * @brief Class to validate headers in the issue CSV files
 */

namespace APP\plugins\importexport\csv\classes\validations;

class RequiredSubmissionHeaders
{
    static $submissionHeaders = [
        'serverPath',
        'locale',
        'articleTitle',
        'articlePrefix',
        'articleSubtitle',
        'articleAbstract',
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
        'datePublished',
        'startPage',
        'endPage',
        'copyrightYear',
		'copyrightHolder',
		'licenseUrl',
    ];

    static $submissionRequiredHeaders = [
        'serverPath',
        'locale',
        'articleTitle',
        'authors',
        'datePublished',
    ];

    public static function validateRowHasAllFields(array $row): bool
    {
        return count($row) === count(self::$submissionHeaders);
    }

    public static function validateRowHasAllRequiredFields(object $row): bool
    {
        foreach (self::$submissionRequiredHeaders as $requiredHeader) {
            if (!$row->{$requiredHeader}) {
                return false;
            }
        }

        return true;
    }
}
