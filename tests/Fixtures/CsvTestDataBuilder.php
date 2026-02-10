<?php

/**
 * @file plugins/importexport/csv/tests/Fixtures/CsvTestDataBuilder.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class CsvTestDataBuilder
 *
 * @brief Builder class for creating test CSV data for CSV plugin tests
 */

namespace APP\plugins\importexport\csv\tests\Fixtures;

use APP\plugins\importexport\csv\classes\validations\RequiredPreprintHeaders;
use APP\plugins\importexport\csv\classes\validations\RequiredUserHeaders;

class CsvTestDataBuilder
{
    /**
     * Build a preprint data row with fluent interface
     */
    public static function preprint(): PreprintDataBuilder
    {
        return new PreprintDataBuilder();
    }

    /**
     * Build a user data row with fluent interface
     */
    public static function user(): UserDataBuilder
    {
        return new UserDataBuilder();
    }

    /**
     * Get preprint headers
     */
    public static function getPreprintHeaders(): array
    {
        return RequiredPreprintHeaders::$preprintHeaders;
    }

    /**
     * Get user headers
     */
    public static function getUserHeaders(): array
    {
        return RequiredUserHeaders::$userHeaders;
    }

    /**
     * Create a minimal valid preprint row (only required fields)
     */
    public static function minimalPreprintRow(): array
    {
        return self::preprint()
            ->withServerPath('testserver')
            ->withLocale('en')
            ->withTitle('Minimal Test Preprint')
            ->withAuthors('John,Doe,john@example.com,,Test University')
            ->withDatePosted('2024-01-15')
            ->withSectionTitle('Preprints')
            ->withSectionAbbrev('PRE')
            ->buildArray();
    }

    /**
     * Create a complete preprint row with all optional fields
     */
    public static function completePreprintRow(): array
    {
        return self::preprint()
            ->withServerPath('testserver')
            ->withLocale('en')
            ->withVersionIdentifier('TEST-2024-001')
            ->withVersion('1')
            ->withPrefix('QC')
            ->withTitle('Complete Test Preprint')
            ->withSubtitle('A Comprehensive Study')
            ->withAbstract('This is a complete test abstract with all fields.')
            ->withAuthors('Jane,Smith,jane@example.com,0000-0002-1825-0097,MIT;John,Doe,john@example.com,,Harvard')
            ->withKeywords('test;unit testing;php')
            ->withSubjects('Computer Science;Software Engineering')
            ->withCoverage('Global study')
            ->withCategories('Research Article')
            ->withDoi('10.1234/test-001')
            ->withSectionTitle('Preprints')
            ->withSectionAbbrev('PRE')
            ->withDatePosted('2024-01-15')
            ->withDateSubmitted('2024-01-10')
            ->withCopyrightYear('2024')
            ->withCopyrightHolder('Test Institute')
            ->withLicenseUrl('https://creativecommons.org/licenses/by/4.0')
            ->withSupportingAgencies('NSF;DOE')
            ->buildArray();
    }

    /**
     * Create a minimal valid user row
     */
    public static function minimalUserRow(): array
    {
        return self::user()
            ->withServerPath('testserver')
            ->withFirstname('John')
            ->withLastname('Doe')
            ->withEmail('john@example.com')
            ->withUsername('jdoe')
            ->withPassword('temppass123')
            ->withRoles('Author')
            ->buildArray();
    }

    /**
     * Create a complete user row with all optional fields
     */
    public static function completeUserRow(): array
    {
        return self::user()
            ->withServerPath('testserver')
            ->withFirstname('Jane')
            ->withLastname('Smith')
            ->withEmail('jane@example.com')
            ->withAffiliation('MIT')
            ->withCountry('US')
            ->withUsername('jsmith')
            ->withPassword('temppass123')
            ->withRoles('Author;Reader')
            ->withReviewInterests('machine learning;data science')
            ->withOrcid('0000-0002-1825-0097')
            ->buildArray();
    }
}

/**
 * Builder for preprint data rows
 */
class PreprintDataBuilder
{
    private array $data;

    public function __construct()
    {
        // Initialize with empty values for all headers
        $this->data = array_fill_keys(RequiredPreprintHeaders::$preprintHeaders, '');
    }

    public function withServerPath(string $serverPath): self
    {
        $this->data['serverPath'] = $serverPath;
        return $this;
    }

    public function withLocale(string $locale): self
    {
        $this->data['locale'] = $locale;
        return $this;
    }

    public function withVersionIdentifier(string $identifier): self
    {
        $this->data['versionIdentifier'] = $identifier;
        return $this;
    }

    public function withVersion(string $version): self
    {
        $this->data['version'] = $version;
        return $this;
    }

    public function withPrefix(string $prefix): self
    {
        $this->data['preprintPrefix'] = $prefix;
        return $this;
    }

    public function withTitle(string $title): self
    {
        $this->data['preprintTitle'] = $title;
        return $this;
    }

    public function withSubtitle(string $subtitle): self
    {
        $this->data['preprintSubtitle'] = $subtitle;
        return $this;
    }

    public function withAbstract(string $abstract): self
    {
        $this->data['preprintAbstract'] = $abstract;
        return $this;
    }

    public function withAuthors(string $authors): self
    {
        $this->data['authors'] = $authors;
        return $this;
    }

    public function withKeywords(string $keywords): self
    {
        $this->data['keywords'] = $keywords;
        return $this;
    }

    public function withSubjects(string $subjects): self
    {
        $this->data['subjects'] = $subjects;
        return $this;
    }

    public function withCoverage(string $coverage): self
    {
        $this->data['coverage'] = $coverage;
        return $this;
    }

    public function withCategories(string $categories): self
    {
        $this->data['categories'] = $categories;
        return $this;
    }

    public function withDoi(string $doi): self
    {
        $this->data['doi'] = $doi;
        return $this;
    }

    public function withCoverImage(string $filename, string $altText = ''): self
    {
        $this->data['coverImageFilename'] = $filename;
        $this->data['coverImageAltText'] = $altText;
        return $this;
    }

    public function withGalleys(string $filenames, string $labels): self
    {
        $this->data['galleyFilenames'] = $filenames;
        $this->data['galleyLabels'] = $labels;
        return $this;
    }

    public function withSupplementaryFiles(string $filenames, string $labels, string $descriptions = ''): self
    {
        $this->data['suppFilenames'] = $filenames;
        $this->data['suppLabels'] = $labels;
        $this->data['suppDescriptions'] = $descriptions;
        return $this;
    }

    public function withSectionTitle(string $title): self
    {
        $this->data['sectionTitle'] = $title;
        return $this;
    }

    public function withSectionAbbrev(string $abbrev): self
    {
        $this->data['sectionAbbrev'] = $abbrev;
        return $this;
    }

    public function withDatePosted(string $date): self
    {
        $this->data['datePosted'] = $date;
        return $this;
    }

    public function withDateSubmitted(?string $date): self
    {
        $this->data['dateSubmitted'] = $date;
        return $this;
    }

    public function withCopyrightYear(string $year): self
    {
        $this->data['copyrightYear'] = $year;
        return $this;
    }

    public function withCopyrightHolder(string $holder): self
    {
        $this->data['copyrightHolder'] = $holder;
        return $this;
    }

    public function withLicenseUrl(string $url): self
    {
        $this->data['licenseUrl'] = $url;
        return $this;
    }

    public function withReferences(string $filename): self
    {
        $this->data['references'] = $filename;
        return $this;
    }

    public function withVorDoi(string $vorDoi): self
    {
        $this->data['vorDoi'] = $vorDoi;
        return $this;
    }

    public function withSupportingAgencies(string $agencies): self
    {
        $this->data['supportingAgencies'] = $agencies;
        return $this;
    }

    public function withUsername(string $username): self
    {
        $this->data['username'] = $username;
        return $this;
    }

    public function withFunders(string $funders): self
    {
        $this->data['funders'] = $funders;
        return $this;
    }

    /**
     * Build as associative array
     */
    public function buildArray(): array
    {
        return array_values($this->data);
    }

    /**
     * Build as object (like the command processes data)
     */
    public function buildObject(): object
    {
        return (object) $this->data;
    }

    /**
     * Get as associative array with keys
     */
    public function getData(): array
    {
        return $this->data;
    }
}

/**
 * Builder for user data rows
 */
class UserDataBuilder
{
    private array $data;

    public function __construct()
    {
        // Initialize with empty values for all headers
        $this->data = array_fill_keys(RequiredUserHeaders::$userHeaders, '');
    }

    public function withServerPath(string $serverPath): self
    {
        $this->data['serverPath'] = $serverPath;
        return $this;
    }

    public function withFirstname(string $firstname): self
    {
        $this->data['firstname'] = $firstname;
        return $this;
    }

    public function withLastname(string $lastname): self
    {
        $this->data['lastname'] = $lastname;
        return $this;
    }

    public function withEmail(string $email): self
    {
        $this->data['email'] = $email;
        return $this;
    }

    public function withAffiliation(string $affiliation): self
    {
        $this->data['affiliation'] = $affiliation;
        return $this;
    }

    public function withCountry(string $country): self
    {
        $this->data['country'] = $country;
        return $this;
    }

    public function withUsername(string $username): self
    {
        $this->data['username'] = $username;
        return $this;
    }

    public function withPassword(string $password): self
    {
        $this->data['tempPassword'] = $password;
        return $this;
    }

    public function withRoles(string $roles): self
    {
        $this->data['roles'] = $roles;
        return $this;
    }

    public function withReviewInterests(string $interests): self
    {
        $this->data['reviewInterests'] = $interests;
        return $this;
    }

    public function withOrcid(string $orcid): self
    {
        $this->data['orcid'] = $orcid;
        return $this;
    }

    /**
     * Build as indexed array (CSV row format)
     */
    public function buildArray(): array
    {
        return array_values($this->data);
    }

    /**
     * Build as object (like the command processes data)
     */
    public function buildObject(): object
    {
        return (object) $this->data;
    }

    /**
     * Get as associative array with keys
     */
    public function getData(): array
    {
        return $this->data;
    }
}

/**
 * Helper class for creating multi-version/multi-locale test scenarios
 */
class MultiVersionScenarioBuilder
{
    private array $rows = [];

    /**
     * Add version 1 in primary locale
     */
    public function addVersion1(string $locale = 'en'): PreprintDataBuilder
    {
        $builder = new PreprintDataBuilder();
        $builder->withLocale($locale)->withVersion('1');
        $this->rows[] = $builder;
        return $builder;
    }

    /**
     * Add version 2 (inherits from version 1)
     */
    public function addVersion2(string $locale = 'en'): PreprintDataBuilder
    {
        $builder = new PreprintDataBuilder();
        $builder->withLocale($locale)->withVersion('2');
        $this->rows[] = $builder;
        return $builder;
    }

    /**
     * Add additional locale for same version
     */
    public function addLocale(string $version, string $locale): PreprintDataBuilder
    {
        $builder = new PreprintDataBuilder();
        $builder->withLocale($locale)->withVersion($version);
        $this->rows[] = $builder;
        return $builder;
    }

    /**
     * Get all rows as array of arrays
     */
    public function buildAllArrays(): array
    {
        return array_map(fn($builder) => $builder->buildArray(), $this->rows);
    }

    /**
     * Get all rows as array of objects
     */
    public function buildAllObjects(): array
    {
        return array_map(fn($builder) => $builder->buildObject(), $this->rows);
    }
}
