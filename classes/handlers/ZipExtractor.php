<?php

/**
 * @file plugins/importexport/csv/classes/handlers/ZipExtractor.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ZipExtractor
 *
 * @ingroup plugins_importexport_csv
 *
 * @brief Safely extracts ZIP archives uploaded via the web GUI, with protection
 *        against path traversal, ZIP bombs (entry count, compression ratio, total size).
 */

namespace APP\plugins\importexport\csv\classes\handlers;

use APP\plugins\importexport\csv\classes\exceptions\ZipExtractionException;
use ZipArchive;

class ZipExtractor
{
    /** Maximum total uncompressed size allowed (500 MB) */
    public const MAX_TOTAL_BYTES = 524288000;

    /** Maximum allowed compression ratio per entry */
    public const MAX_RATIO = 100;

    /** Maximum number of entries allowed in a ZIP */
    public const MAX_ENTRIES = 10000;

    /**
     * Extract a ZIP archive to a unique temp directory under $targetBaseDir.
     *
     * Validates the archive for path traversal, entry-count bombs, per-entry
     * compression-ratio bombs, and total-size bombs before extracting a single byte.
     *
     * @param string      $zipPath       Absolute path to the ZIP file.
     * @param string|null $targetBaseDir Base directory for the extraction target.
     *                                   Defaults to sys_get_temp_dir().
     *
     * @return string Absolute path to the newly-created extraction directory.
     *
     * @throws ZipExtractionException On corrupt archive, path traversal, or bomb detection.
     */
    public function extract(string $zipPath, ?string $targetBaseDir = null): string
    {
        $baseDir = $targetBaseDir ?? sys_get_temp_dir();

        $zip = new ZipArchive();
        $result = $zip->open($zipPath);

        if ($result !== true) {
            throw ZipExtractionException::corruptArchive($zipPath);
        }

        try {
            $entryCount = $zip->count();

            if ($entryCount > self::MAX_ENTRIES) {
                throw ZipExtractionException::bombDetected($entryCount, self::MAX_ENTRIES);
            }

            $totalUncompressed = 0;

            for ($i = 0; $i < $entryCount; $i++) {
                $stat = $zip->statIndex($i);

                if ($stat === false) {
                    continue;
                }

                $name = $stat['name'];

                // Path traversal check
                if (str_contains($name, '..') || str_starts_with($name, '/')) {
                    throw ZipExtractionException::pathTraversal($name);
                }

                $compressedSize   = (int) $stat['comp_size'];
                $uncompressedSize = (int) $stat['size'];

                // Per-entry compression ratio bomb check (skip entries with no compressed size)
                if ($compressedSize > 0) {
                    $ratio = $uncompressedSize / $compressedSize;
                    if ($ratio > self::MAX_RATIO) {
                        throw ZipExtractionException::bombDetected((int) $ratio, self::MAX_RATIO);
                    }
                }

                $totalUncompressed += $uncompressedSize;

                // Total size bomb check
                if ($totalUncompressed > self::MAX_TOTAL_BYTES) {
                    throw ZipExtractionException::bombDetected($totalUncompressed, self::MAX_TOTAL_BYTES);
                }
            }

            // All checks passed — extract to a unique subdirectory
            $extractDir = $baseDir . '/csv_import_' . bin2hex(random_bytes(8));
            mkdir($extractDir, 0700, true);

            if (!$zip->extractTo($extractDir)) {
                throw ZipExtractionException::corruptArchive($zipPath);
            }
        } finally {
            $zip->close();
        }

        return $extractDir;
    }

    /**
     * Resolve the actual source directory from an extraction root.
     *
     * - If the root contains CSV files directly, returns the root.
     * - If the root contains exactly one subdirectory and no CSV files, returns that subdirectory.
     * - Otherwise returns the root.
     *
     * @param string $extractDir Path returned by extract().
     *
     * @return string Resolved source directory.
     */
    public static function resolveSourceDir(string $extractDir): string
    {
        $entries = array_diff(scandir($extractDir), ['.', '..']);

        $hasCsvAtRoot  = false;
        $subdirectories = [];

        foreach ($entries as $entry) {
            $fullPath = $extractDir . '/' . $entry;
            if (is_dir($fullPath)) {
                $subdirectories[] = $fullPath;
            } elseif (str_ends_with(mb_strtolower($entry), '.csv')) {
                $hasCsvAtRoot = true;
            }
        }

        if ($hasCsvAtRoot) {
            return $extractDir;
        }

        if (count($subdirectories) === 1) {
            return $subdirectories[0];
        }

        return $extractDir;
    }

    /**
     * Delete extraction directories whose mtime is older than $ttlSeconds.
     *
     * Only removes subdirectories; files at the $baseDir root are left untouched.
     * Does nothing if $baseDir does not exist.
     *
     * @param string $baseDir    Directory that contains csv_import_* subdirectories.
     * @param int    $ttlSeconds Age threshold in seconds (default: 3600 = 1 hour).
     */
    public static function cleanupExpired(string $baseDir, int $ttlSeconds = 3600): void
    {
        if (!is_dir($baseDir)) {
            return;
        }

        $cutoff = time() - $ttlSeconds;
        $entries = array_diff(scandir($baseDir), ['.', '..']);

        foreach ($entries as $entry) {
            $fullPath = $baseDir . '/' . $entry;
            if (!is_dir($fullPath)) {
                continue;
            }

            if (filemtime($fullPath) < $cutoff) {
                static::deleteDirectory($fullPath);
            }
        }
    }

    /**
     * Recursively delete a directory and all its contents.
     *
     * @param string $dir Absolute path to the directory.
     */
    private static function deleteDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $entries = array_diff(scandir($dir), ['.', '..']);
        foreach ($entries as $entry) {
            $path = $dir . '/' . $entry;
            if (is_dir($path)) {
                static::deleteDirectory($path);
            } else {
                unlink($path);
            }
        }

        rmdir($dir);
    }
}
