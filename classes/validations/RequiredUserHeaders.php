<?php

/**
 * @file plugins/importexport/csv/classes/validations/RequiredUserHeaders.php
 *
 * Copyright (c) 2014-2025 Simon Fraser University
 * Copyright (c) 2003-2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class RequiredUserHeaders
 *
 * @ingroup plugins_importexport_csv
 *
 * @brief Class to validate headers in the user CSV files
 */

namespace APP\plugins\importexport\csv\classes\validations;

class RequiredUserHeaders
{
    static $userHeaders = [
        'serverPath',
        'firstname',
        'lastname',
        'email',
        'affiliation',
        'country',
        'username',
        'tempPassword',
        'roles',
        'reviewInterests',
    ];

    static $userRequiredHeaders = [
        'serverPath',
        'firstname',
        'lastname',
        'email',
        'roles',
    ];

    public static function validateRowHasAllFields(array $row): bool
    {
        return count($row) === count(self::$userHeaders);
    }

    public static function validateRowHasAllRequiredFields(object $row): bool
    {
        foreach(self::$userRequiredHeaders as $requiredHeader) {
            if (!$row->{$requiredHeader}) {
                return false;
            }
        }

        return true;
    }
}
