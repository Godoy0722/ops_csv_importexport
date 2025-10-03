<?php

/**
 * @file plugins/importexport/csv/classes/cachedAttributes/CachedEntities.php
 *
 * Copyright (c) 2014-2025 Simon Fraser University
 * Copyright (c) 2003-2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class CachedEntities
 *
 * @ingroup plugins_importexport_csv
 *
 * @brief This class is responsible for retrieving cached entities such as
 * servers, user groups, genres, categories, sections, and issues.
 */

namespace APP\plugins\importexport\csv\classes\cachedAttributes;

use APP\facades\Repo;
use APP\section\Section;
use APP\server\Server;
use APP\subscription\SubscriptionType;
use PKP\category\Category;
use PKP\security\Role;
use PKP\user\User;
use PKP\userGroup\UserGroup;

class CachedEntities
{
    /** @var array<string,Server> */
    static array $servers = [];

    /** @var array<string,int|null> */
    static array $userGroupIds = [];

    /** @var array<int,array<int,UserGroup>> */
    static array $userGroups = [];

    /** @var array<string,int|null> */
    static array $genreIds = [];

    /** @var array<string,Category|null> */
    static array $categories = [];

    /** @var array<string,Section|null> */
    static array $sections = [];

    /** @var array<string,User|null> */
    static array $users = [];

    /** @var array<string,SubscriptionType|null> */
    static array $subscriptionTypes = [];

    /** Retrieves a cached Server by its path. Returns null if an error occurs. */
    static function getCachedServer(string $serverPath): ?Server
    {
        $serverDao = CachedDaos::getServerDAO();

        return self::$servers[$serverPath] ?? self::$servers[$serverPath] = $serverDao->getByPath($serverPath);
    }

    /** Retrieves a cached userGroup ID by serverId. Returns null if an error occurs. */
    static function getCachedUserGroupId(string $serverPath, int $serverId): ?int
    {
        if (isset(self::$userGroupIds[$serverPath])) {
            return self::$userGroupIds[$serverPath];
        }

        $userGroups = Repo::userGroup()->getByRoleIds([Role::ROLE_ID_AUTHOR], $serverId);

        if (empty($userGroups)) {
            return null;
        }

        $userGroup = $userGroups->first();
        return self::$userGroupIds[$serverPath] = $userGroup->getId();
    }

	/** Retrieves a cached User by email. Returns null if an error occurs. */
    static function getCachedUserByEmail(string $email): ?User
    {
		return self::$users[$email] ??= Repo::user()->getByEmail($email);
    }

	/** Retrieves a cached User by username. Returns null if an error occurs. */
    static function getCachedUserByUsername(string $username): ?User
    {
		return self::$users[$username] ??= Repo::user()->getByUsername($username);
    }

	/**
	 * Retrieves a cached UserGroup by serverId. Returns null if an error occurs.
	 *
	 * @return UserGroup[]
	 */
    static function getCachedUserGroupsByServerId(int $serverId): array
    {
        if (isset(self::$userGroups[$serverId])) {
            return self::$userGroups[$serverId];
        }

        $collector = Repo::userGroup()->getCollector()->filterByContextIds([$serverId]);

        $userGroupsIterator = $collector->getMany();
        $userGroups = [];

        foreach ($userGroupsIterator as $userGroup) {
            $userGroups[$userGroup->getId()] = $userGroup;
        }

        return self::$userGroups[$serverId] = $userGroups;
    }

	/** Retrieves a cached UserGroup by name and serverId. Returns null if an error occurs. */
    static function getCachedUserGroupByName(string $name, int $serverId, string $locale): ?UserGroup
    {
        $userGroups = self::getCachedUserGroupsByServerId($serverId);

        foreach ($userGroups as $userGroup) {
            if (mb_strtolower($userGroup->getName($locale)) === mb_strtolower($name)) {
                return $userGroup;
            }
        }

        return null;
    }

    /** Retrieves a cached genre ID by genreName and serverId. Returns null if an error occurs. */
    static function getCachedGenreId(string $genreName, int $serverId): ?int
    {
		$genreDao = CachedDaos::getGenreDao();
		$genre = $genreDao->getByKey($genreName, $serverId);

		return self::$genreIds[$genreName] ?? self::$genreIds[$genreName] = $genre->getId();
    }

    /** Retrieves a cached Category by categoryName and serverId. Returns null if an error occurs. */
    static function getCachedCategory(string $categoryName, int $serverId): ?Category
    {
        if (isset(self::$categories[$categoryName])) {
            return self::$categories[$categoryName];
        }

        $categories = Repo::category()->getCollector()
            ->filterByContextIds([$serverId])
            ->getMany();

        foreach ($categories as $category) {
            if ($category->getPath() === $categoryName) {
                return self::$categories[$categoryName] = $category;
            }
        }

        return null;
    }

    /** Retrieves a cached Section by sectionTitle, sectionAbbrev, and serverId. Returns null if an error occurs. */
    static function getCachedSection(string $sectionTitle, string $sectionAbbrev, string $locale, int $serverId): ?Section
    {
        $customSectionKey = $sectionTitle . '_' . mb_strtoupper(trim($sectionAbbrev));

        if (isset(self::$sections[$customSectionKey])) {
            return self::$sections[$customSectionKey];
        }

        $sections = Repo::section()->getCollector()
            ->filterByContextIds([$serverId])
            ->getMany();

        foreach ($sections as $section) {
            if ($section->getAbbrev($locale) === $sectionAbbrev && $section->getTitle($locale) === $sectionTitle) {
                return self::$sections[$customSectionKey] = $section;
            }
        }

        return null;
    }

    static function getCachedSectionById(int $baseSectionId, int $serverId, string $locale): ?Section
    {
        $existingSection = array_find(self::$sections, function (Section $section) use ($baseSectionId) {
            return $section->getId() === $baseSectionId;
        });

        if ($existingSection) {
            return $existingSection;
        }

        $section = Repo::section()->get($baseSectionId, $serverId);
        $sectionTitle = $section->getTitle($locale);
        $sectionAbbrev = $section->getAbbrev($locale);

        $customSectionKey = $sectionTitle . '_' . mb_strtoupper(trim($sectionAbbrev));
        self::$sections[$customSectionKey] = $section;
        return $section;
    }
}
