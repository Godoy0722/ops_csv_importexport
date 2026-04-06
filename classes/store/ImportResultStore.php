<?php

/**
 * @file plugins/importexport/csv/classes/store/ImportResultStore.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ImportResultStore
 *
 * @ingroup plugins_importexport_csv
 *
 * @brief Persists and retrieves import result data as JSON files keyed by UUID
 */

namespace APP\plugins\importexport\csv\classes\store;

class ImportResultStore
{
    public function __construct(private string $storeDir)
    {
    }

    public function save(string $uuid, array $data): void
    {
        if (!is_dir($this->storeDir)) {
            mkdir($this->storeDir, 0700, true);
        }

        file_put_contents(
            $this->path($uuid),
            json_encode($data, JSON_THROW_ON_ERROR)
        );
    }

    public function get(string $uuid): ?array
    {
        $path = $this->path($uuid);

        if (!file_exists($path)) {
            return null;
        }

        return json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    }

    private function path(string $uuid): string
    {
        $sanitized = preg_replace('/[^a-f0-9\-]/', '', $uuid);
        return $this->storeDir . '/' . $sanitized . '.json';
    }
}