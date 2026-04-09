<?php

/**
 * @file plugins/importexport/csv/tests/BaseTestCase.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class BaseTestCase
 *
 * @brief Base test case class for CSV Import/Export plugin tests.
 *        Extends the shared BaseTestCase and adds OPS-specific CachedEntities backup/restore.
 */

namespace APP\plugins\importexport\csv\tests;

use APP\plugins\importexport\csv\shared\tests\BaseTestCase as SharedBaseTestCase;

abstract class BaseTestCase extends SharedBaseTestCase
{
    /**
     * Backup static properties from CachedEntities (shared + OPS-specific)
     */
    protected function backupCachedStatics(): void
    {
        parent::backupCachedStatics();

        $cachedEntitiesClass = \APP\plugins\importexport\csv\classes\cachedAttributes\CachedEntities::class;

        $this->cachedEntitiesBackup['servers'] = $cachedEntitiesClass::$servers;
        $this->cachedEntitiesBackup['supplementaryGenreIds'] = $cachedEntitiesClass::$supplementaryGenreIds;

        $cachedEntitiesClass::$servers = [];
        $cachedEntitiesClass::$supplementaryGenreIds = [];
    }

    /**
     * Restore static properties to CachedEntities (shared + OPS-specific)
     */
    protected function restoreCachedStatics(): void
    {
        parent::restoreCachedStatics();

        $cachedEntitiesClass = \APP\plugins\importexport\csv\classes\cachedAttributes\CachedEntities::class;

        $cachedEntitiesClass::$servers = $this->cachedEntitiesBackup['servers'] ?? [];
        $cachedEntitiesClass::$supplementaryGenreIds = $this->cachedEntitiesBackup['supplementaryGenreIds'] ?? [];
    }
}
