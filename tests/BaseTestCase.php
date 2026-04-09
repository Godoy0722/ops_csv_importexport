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
use APP\server\Server;
use PHPUnit\Framework\MockObject\MockObject;

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

    // ==================== OPS-Specific Helpers ====================

    /**
     * Create a mock Server object (OPS-specific context)
     */
    protected function createMockServer(array $data = []): Server|MockObject
    {
        /** @var Server|MockObject */
        $server = $this->getMockBuilder(Server::class)
            ->onlyMethods(['getSupportedSubmissionLocales', 'getPrimaryLocale', 'getContactEmail'])
            ->getMock();

        $server->setId($data['id'] ?? 1);
        $server->setPath($data['path'] ?? 'testserver');
        $server->setName($data['name'] ?? 'Test Server', $data['locale'] ?? 'en');

        $supportedLocales = $data['supportedLocales'] ?? ['en'];
        $primaryLocale = $data['primaryLocale'] ?? 'en';
        $contactEmail = $data['contactEmail'] ?? 'contact@example.com';

        $server->method('getSupportedSubmissionLocales')->willReturn($supportedLocales);
        $server->method('getPrimaryLocale')->willReturn($primaryLocale);
        $server->method('getContactEmail')->willReturn($contactEmail);

        return $server;
    }

    /**
     * Create a preprint data object (OPS-specific submission)
     */
    protected function createPreprintDataObject(array $data): object
    {
        return (object) array_merge([
            'serverPath' => 'testserver',
            'locale' => 'en',
            'versionIdentifier' => '',
            'version' => '',
            'preprintPrefix' => '',
            'preprintTitle' => 'Test Preprint',
            'preprintSubtitle' => '',
            'preprintAbstract' => 'Test abstract',
            'authors' => 'John,Doe,john@example.com,,Test University',
            'keywords' => '',
            'subjects' => '',
            'coverage' => '',
            'categories' => '',
            'doi' => '',
            'coverImageFilename' => '',
            'coverImageAltText' => '',
            'galleyFilenames' => '',
            'galleyLabels' => '',
            'suppFilenames' => '',
            'suppLabels' => '',
            'suppDescriptions' => '',
            'sectionTitle' => 'Preprints',
            'sectionAbbrev' => 'PRE',
            'datePosted' => '2024-01-15',
            'dateSubmitted' => '',
            'copyrightYear' => '',
            'copyrightHolder' => '',
            'licenseUrl' => '',
            'references' => '',
            'vorDoi' => '',
            'supportingAgencies' => '',
            'username' => '',
            'funders' => '',
        ], $data);
    }
}
