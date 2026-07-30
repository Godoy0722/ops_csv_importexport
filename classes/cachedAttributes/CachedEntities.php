<?php

/**
 * @file plugins/importexport/csv/classes/cachedAttributes/CachedEntities.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class CachedEntities
 *
 * @ingroup plugins_importexport_csv
 *
 * @brief This class is responsible for retrieving cached entities such as
 * servers, user groups, genres, categories and sections.
 */

namespace APP\plugins\importexport\csv\classes\cachedAttributes;

use APP\plugins\importexport\csv\shared\cachedAttributes\CachedEntities as SharedCachedEntities;
use APP\server\Server;
use APP\server\ServerDAO;
use PKP\db\DAORegistry;
use PKP\submission\GenreDAO;

class CachedEntities extends SharedCachedEntities
{
    /** @var array<string,Server> */
    static array $servers = [];

    /** @var array<int,int|null> */
    static array $supplementaryGenreIds = [];

    /** @var array<int,array<string,bool>> Server-scoped cache of existing DOIs from the database */
    static array $existingDoisByServer = [];

    /** Resets all cached entities. Used after dry-mode rollback to clear stale IDs. */
    public static function reset(): void
    {
        parent::reset();

        static::$servers = [];
        static::$supplementaryGenreIds = [];
        static::$existingDoisByServer = [];
    }

    /** Retrieves a cached Server by its path. Returns null if an error occurs. */
    static function getCachedServer(string $serverPath): ?Server
    {
        $serverDao = DAORegistry::getDAO('ServerDAO'); /** @var ServerDAO $serverDao */

        return static::$servers[$serverPath] ?? static::$servers[$serverPath] = $serverDao->getByPath($serverPath);
    }

    /** Retrieves a cached supplementary genre ID by serverId. Returns null if none found. */
    static function getCachedSupplementaryGenreId(int $serverId): ?int
    {
        if (array_key_exists($serverId, static::$supplementaryGenreIds)) {
            return static::$supplementaryGenreIds[$serverId];
        }

        $genreDao = DAORegistry::getDAO('GenreDAO'); /** @var GenreDAO $genreDao */
        $supplementaryGenres = $genreDao->getBySupplementaryAndContextId(true, $serverId)->toArray();

        return static::$supplementaryGenreIds[$serverId] = !empty($supplementaryGenres) ? $supplementaryGenres[0]->getId() : null;
    }

    /** Retrieves all existing DOIs for a server, cached statically. Returns assoc array [doi => true]. */
    static function getExistingDois(int $serverId): array
    {
        if (isset(static::$existingDoisByServer[$serverId])) {
            return static::$existingDoisByServer[$serverId];
        }

        $dois = \Illuminate\Support\Facades\DB::table('dois')
            ->where('context_id', $serverId)
            ->whereNotNull('doi')
            ->distinct()
            ->pluck('doi');

        $map = [];
        foreach ($dois as $doi) {
            $map[$doi] = true;
        }

        return static::$existingDoisByServer[$serverId] = $map;
    }
}
