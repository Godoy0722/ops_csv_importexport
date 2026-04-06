<?php

/**
 * @file plugins/importexport/csv/classes/exceptions/ImportLockException.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ImportLockException
 *
 * @ingroup plugins_importexport_csv
 *
 * @brief Exception thrown when an import is already in progress
 */

namespace APP\plugins\importexport\csv\classes\exceptions;

class ImportLockException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct(__('plugins.importexport.csv.importLocked'));
    }
}
