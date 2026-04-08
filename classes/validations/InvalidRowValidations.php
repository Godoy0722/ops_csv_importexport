<?php

/**
 * @file plugins/importexport/csv/classes/validations/InvalidRowValidations.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class InvalidRowValidations
 *
 * @ingroup plugins_importexport_csv
 *
 * @brief Class to validate all necessary requirements for a CSV row to be valid
 */

namespace APP\plugins\importexport\csv\classes\validations;

use APP\plugins\importexport\csv\classes\exceptions\RowValidationException;
use APP\plugins\importexport\csv\shared\validations\InvalidRowValidations as SharedInvalidRowValidations;

class InvalidRowValidations extends SharedInvalidRowValidations
{
    /**
     * Validates whether the CSV row contains all required fields.
     *
     * @throws RowValidationException
     */
    public static function validateRowHasAllRequiredFields(object $data, callable $requiredFieldsValidation): void
    {
        parent::validateRowHasAllRequiredFieldsCommons($data, $requiredFieldsValidation);
    }
}
