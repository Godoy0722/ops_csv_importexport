<?php

/**
 * @file plugins/importexport/csv/classes/cachedAttributes/CachedDaos.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class CachedDaos
 *
 * @ingroup plugins_importexport_csv
 *
 * @brief This class is responsible for retrieving cached DAOs.
 */

namespace APP\plugins\importexport\csv\classes\cachedAttributes;

use APP\facades\Repo;
use APP\plugins\generic\funding\classes\FunderAwardDAO;
use APP\plugins\generic\funding\classes\FunderDAO;
use APP\server\ServerDAO;
use PKP\db\DAO;
use PKP\db\DAORegistry;
use PKP\submission\GenreDAO;

class CachedDaos
{
    /** @var array<string,DAO> */
    static array $cachedDaos = [];

    /** Retrieves the cached ServerDAO instance. */
    public static function getServerDao(): ServerDAO
    {
        return self::$cachedDaos['ServerDAO'] ??= DAORegistry::getDAO('ServerDAO');
    }

    /** Retrieves the cached GenreDAO instance. */
    public static function getGenreDao(): GenreDAO
    {
        return self::$cachedDaos['GenreDAO'] ??= DAORegistry::getDAO('GenreDAO');
    }

    /** Retrieves the cached FunderDAO instance. */
    public static function getFunderDao(): FunderDAO
    {
        return self::$cachedDaos['FunderDAO'] ??= new FunderDAO();
    }

    /** Retrieves the cached FunderAwardDAO instance. */
    public static function getFunderAwardDao(): FunderAwardDAO
    {
        return self::$cachedDaos['FunderAwardDAO'] ??= new FunderAwardDAO();
    }

    /** Retrieves the cached CategoryDAO instance. */
    public static function getCategoryDao()
	{
		return Repo::category()->dao;
	}
}
