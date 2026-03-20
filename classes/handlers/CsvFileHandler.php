<?php

/**
 * @file plugins/importexport/csv/classes/handlers/CsvFileHandler.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class CsvFileHandler
 *
 * @ingroup plugins_importexport_csv
 *
 * @brief Handles the issue import when the user uses the issue command
 */

namespace APP\plugins\importexport\csv\classes\handlers;

use Exception;

class CsvFileHandler
{
    /** Create a new readable SplFileObject. Return null if an error occurred. */
    public static function createReadableCSVFile(string $filePath): ?\SplFileObject
    {
        try {
            $file = new \SplFileObject($filePath, 'r');
            $file->setFlags(\SplFileObject::READ_CSV);
            return $file;
        } catch (\Exception $e) {
            $errorMessage =  __('plugins.importexport.csv.couldNotOpenFile', [
                'filePath' => $filePath,
                'errorMessage' => $e->getMessage(),
            ]);

            throw new Exception($errorMessage);
        }
    }

    /** Create a new writable SplFileObject for invalid rows from a unique CSV file. Return null if an error occurred. */
    public static function createCSVFileInvalidRows(string $sourceDir, string $filename, array $requiredHeaders): ?\SplFileObject
    {
        try {
            $invalidRowsFile = new \SplFileObject($sourceDir . '/' . $filename, 'a+');
            $invalidRowsFile->fputcsv(array_merge($requiredHeaders, ['error']));

            return $invalidRowsFile;
        } catch (\Exception $e) {
            throw new Exception(__('plugins.importexport.csv.couldNotCreateFile', ['filename' => $sourceDir . '/' . $filename]));
        }
	}

    /** Add a new row on the invalid csv file */
    public static function processFailedRow(
        \SplFileObject &$invalidRowsCsvFile,
        array $fields,
        int $rowSize,
        string $reason,
        int &$failedRows
    ) {
        if (!$invalidRowsCsvFile->fputcsv(array_merge(array_pad($fields, $rowSize, null), [$reason]))) {
            throw new Exception(__('plugins.importexport.csv.couldNotWriteFile', ['filename' => $invalidRowsCsvFile->getFilename()]));
        }
		++$failedRows;
	}
}
