<?php

/**
 * @file plugins/importexport/csv/classes/exceptions/FileNotSavedException.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class FileNotSavedException
 *
 * @ingroup plugins_importexport_csv
 *
 * @brief Exception thrown when a CSV row validation fails
 */

namespace APP\plugins\importexport\csv\classes\exceptions;

use Exception;

class FileNotSavedException extends Exception
{
}
