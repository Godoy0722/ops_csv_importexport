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

use APP\facades\Repo;
use APP\section\Section;
use APP\server\Server;
use APP\server\ServerDAO;
use APP\subscription\SubscriptionType;
use PKP\category\Category;
use PKP\db\DAORegistry;
use PKP\security\Role;
use PKP\submission\GenreDAO;
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

    /** @var array<int,int|null> */
    static array $supplementaryGenreIds = [];

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
        $serverDao = DAORegistry::getDAO('ServerDAO'); /** @var ServerDAO $serverDao */

        return static::$servers[$serverPath] ?? static::$servers[$serverPath] = $serverDao->getByPath($serverPath);
    }

    /** Retrieves a cached userGroup ID by serverId. Returns null if an error occurs. */
    static function getCachedAuthorUserGroupId(string $serverPath, int $serverId): ?int
    {
        // Cache null values as well, so repeated misses aren't retried
        return static::$userGroupIds[$serverPath] ??= Repo::userGroup()->getByRoleIds([Role::ROLE_ID_AUTHOR], $serverId)->first()?->id;
    }

    /** Retrieves a cached User by email. Returns null if an error occurs. */
    static function getCachedUserByEmail(string $email): ?User
    {
        if (!isset(static::$users[$email])) {
            $user = Repo::user()->getByEmail($email);
            if ($user) {
                static::$users[$email] = $user;
                static::$users[$user->getUsername()] = $user;
            } else {
                static::$users[$email] = null;
            }
        }
        return static::$users[$email];
    }

    /** Retrieves a cached User by username. Returns null if an error occurs. */
    static function getCachedUserByUsername(string $username, bool $allowDisabled = false): ?User
    {
        if (!isset(static::$users[$username])) {
            $user = Repo::user()->getByUsername($username, $allowDisabled);
            if ($user) {
                static::$users[$username] = $user;
                static::$users[$user->getEmail()] = $user;
            } else {
                static::$users[$username] = null;
            }
        }
        return static::$users[$username];
    }

    /**
     * Retrieves a cached UserGroup by serverId. Returns null if an error occurs.
     *
     * @return UserGroup[]
     */
    static function getCachedUserGroupsByServerId(int $serverId): array
    {
        if (isset(static::$userGroups[$serverId])) {
            return static::$userGroups[$serverId];
        }

        $userGroups = [];
        $userGroupsCollection = UserGroup::withContextIds([$serverId])->get();

        foreach ($userGroupsCollection as $userGroup) {
            $userGroups[$userGroup->id] = $userGroup;
        }

        return static::$userGroups[$serverId] = $userGroups;
    }

    /** Retrieves a cached UserGroup by name and serverId. Returns null if an error occurs. */
    static function getCachedUserGroupByName(string $name, int $serverId, string $locale): ?UserGroup
    {
        $userGroups = static::getCachedUserGroupsByServerId($serverId);

        foreach ($userGroups as $userGroup) {
            if (mb_strtolower($userGroup->name[$locale]) === mb_strtolower($name)) {
                return $userGroup;
            }
        }

        return null;
    }

    /** Retrieves a cached genre ID by genreName and serverId. Returns null if an error occurs. */
    static function getCachedGenreId(string $genreName, int $serverId): ?int
    {
        $genreDao = DAORegistry::getDAO('GenreDAO'); /** @var GenreDAO $genreDao */
        return static::$genreIds[$genreName] ??= $genreDao->getByKey($genreName, $serverId)->getId();
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

    /** Retrieves a cached Category by categoryName and serverId. Returns null if an error occurs. */
    static function getCachedCategory(string $categoryName, int $serverId): ?Category
    {
        if (isset(static::$categories[$categoryName])) {
            return static::$categories[$categoryName];
        }

        $categories = Repo::category()->getCollector()
            ->filterByContextIds([$serverId])
            ->getMany();

        foreach ($categories as $category) {
            if ($category->getPath() === $categoryName) {
                return static::$categories[$categoryName] = $category;
            }
        }

        return null;
    }

    /** Retrieves a cached Section by sectionTitle, sectionAbbrev, and serverId. Returns null if an error occurs. */
    static function getCachedSection(string $sectionTitle, string $sectionAbbrev, string $locale, int $serverId): ?Section
    {
        $customSectionKey = $sectionTitle . '_' . mb_strtoupper(trim($sectionAbbrev));

        if (isset(static::$sections[$customSectionKey])) {
            return static::$sections[$customSectionKey];
        }

        $sections = Repo::section()->getCollector()
            ->filterByContextIds([$serverId])
            ->getMany();

        foreach ($sections as $section) {
            if ($section->getAbbrev($locale) === $sectionAbbrev && $section->getTitle($locale) === $sectionTitle) {
                return static::$sections[$customSectionKey] = $section;
            }
        }

        return null;
    }

    static function getCachedSectionById(int $baseSectionId, int $serverId, string $locale): ?Section
    {
        // Cache by ID first to avoid redundant lookups
        if (isset(static::$sections[$baseSectionId])) {
            return static::$sections[$baseSectionId];
        }

        $section = Repo::section()->get($baseSectionId, $serverId);
        if (!$section) {
            return null;
        }

        $sectionTitle = $section->getTitle($locale);
        $sectionAbbrev = $section->getAbbrev($locale);
        $customSectionKey = $sectionTitle . '_' . mb_strtoupper(trim($sectionAbbrev));

        static::$sections[$baseSectionId] = $section;
        static::$sections[$customSectionKey] = $section;

        return $section;
    }
}
