<?php

/**
 * @file plugins/importexport/csv/classes/cachedAttributes/CachedDaos.php
 *
 * Copyright (c) 2014-2025 Simon Fraser University
 * Copyright (c) 2003-2025 John Willinsky
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
use APP\server\ServerDAO;
use PKP\category\DAO as CategoryDAO;
use PKP\db\DAO;
use PKP\db\DAORegistry;
use PKP\submission\GenreDAO;
use PKP\submission\SubmissionKeywordDAO;
use PKP\submission\SubmissionSubjectDAO;
use PKP\user\InterestDAO;

class CachedDaos
{
    /** @var array<string,DAO> */
    static array $cachedDaos = [];

    /**
     * Retrieves the cached ServerDAO instance.
     */
    public static function getServerDAO(): ServerDAO
    {
        return self::$cachedDaos['ServerDAO'] ??= DAORegistry::getDAO('ServerDAO');
    }

    /** Retrieves the cached GenreDAO instance. */
    public static function getGenreDao(): GenreDAO
    {
        return self::$cachedDaos['GenreDAO'] ??= DAORegistry::getDAO('GenreDAO');
    }

    /** Retrieves the cached SubmissionKeywordDAO instance. */
    public static function getSubmissionKeywordDao(): SubmissionKeywordDAO
    {
        return self::$cachedDaos['SubmissionKeywordDAO'] ??= DAORegistry::getDAO('SubmissionKeywordDAO');
    }

    /** Retrieves the cached SubmissionSubjectDAO instance. */
    public static function getSubmissionSubjectDao(): SubmissionSubjectDAO
    {
        return self::$cachedDaos['SubmissionSubjectDAO'] ??= DAORegistry::getDAO('SubmissionSubjectDAO');
    }

    /** Retrieves the cached InterestDAO instance, which is used for user interests. */
    public static function getUserInterestDao(): InterestDAO
    {
        return self::$cachedDaos['InterestDAO'] ??= DAORegistry::getDAO('InterestDAO');
    }

    /** Retrieves the cached CategoryDAO instance. */
    public static function getCategoryDao(): CategoryDAO
	{
		return self::$cachedDaos['CategoryDAO'] ??= Repo::category()->dao;
	}
}
