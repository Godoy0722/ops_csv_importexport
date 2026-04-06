<?php

/**
 * @file plugins/importexport/csv/classes/exceptions/ZipExtractionException.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ZipExtractionException
 *
 * @ingroup plugins_importexport_csv
 *
 * @brief Exception thrown when ZIP extraction fails due to security violations or corrupt archives
 */

namespace APP\plugins\importexport\csv\classes\exceptions;

class ZipExtractionException extends \RuntimeException
{
    public static function corruptArchive(string $path): self
    {
        return new self("Cannot open ZIP archive: {$path}");
    }

    public static function bombDetected(int $actual, int $limit): self
    {
        return new self("ZIP bomb detected: {$actual} exceeds limit {$limit}");
    }

    public static function pathTraversal(string $entryName): self
    {
        return new self("Path traversal detected in ZIP entry: {$entryName}");
    }
}
