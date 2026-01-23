<?php

/**
 * @file plugins/importexport/csv/tests/Fixtures/MockFactory.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class MockFactory
 *
 * @brief Factory class for creating mock objects used in CSV plugin tests
 */

namespace APP\plugins\importexport\csv\tests\Fixtures;

use APP\publication\Publication;
use APP\section\Section;
use APP\server\Server;
use APP\submission\Submission;
use APP\author\Author;
use Illuminate\Support\Collection;
use Mockery;
use Mockery\MockInterface;
use PKP\category\Category;
use PKP\galley\Galley;
use PKP\security\Role;
use PKP\submission\Genre;
use PKP\submissionFile\SubmissionFile;
use PKP\user\User;
use PKP\userGroup\UserGroup;

class MockFactory
{
    /**
     * Create a mock User with fluent interface
     */
    public static function user(): UserBuilder
    {
        return new UserBuilder();
    }

    /**
     * Create a mock Server with fluent interface
     */
    public static function server(): ServerBuilder
    {
        return new ServerBuilder();
    }

    /**
     * Create a mock Publication with fluent interface
     */
    public static function publication(): PublicationBuilder
    {
        return new PublicationBuilder();
    }

    /**
     * Create a mock Submission with fluent interface
     */
    public static function submission(): SubmissionBuilder
    {
        return new SubmissionBuilder();
    }

    /**
     * Create a mock Author with fluent interface
     */
    public static function author(): AuthorBuilder
    {
        return new AuthorBuilder();
    }

    /**
     * Create a mock Section with fluent interface
     */
    public static function section(): SectionBuilder
    {
        return new SectionBuilder();
    }

    /**
     * Create a mock Category with fluent interface
     */
    public static function category(): CategoryBuilder
    {
        return new CategoryBuilder();
    }

    /**
     * Create a mock UserGroup with fluent interface
     */
    public static function userGroup(): UserGroupBuilder
    {
        return new UserGroupBuilder();
    }

    /**
     * Create a mock Galley with fluent interface
     */
    public static function galley(): GalleyBuilder
    {
        return new GalleyBuilder();
    }

    /**
     * Create a mock Genre with fluent interface
     */
    public static function genre(): GenreBuilder
    {
        return new GenreBuilder();
    }

    /**
     * Create a mock SubmissionFile with fluent interface
     */
    public static function submissionFile(): SubmissionFileBuilder
    {
        return new SubmissionFileBuilder();
    }
}

/**
 * Builder for User mock objects
 */
class UserBuilder
{
    private array $data = [
        'id' => 1,
        'username' => 'testuser',
        'email' => 'test@example.com',
        'givenName' => 'Test',
        'familyName' => 'User',
        'locale' => 'en',
    ];

    public function withId(int $id): self
    {
        $this->data['id'] = $id;
        return $this;
    }

    public function withUsername(string $username): self
    {
        $this->data['username'] = $username;
        return $this;
    }

    public function withEmail(string $email): self
    {
        $this->data['email'] = $email;
        return $this;
    }

    public function withGivenName(string $givenName): self
    {
        $this->data['givenName'] = $givenName;
        return $this;
    }

    public function withFamilyName(string $familyName): self
    {
        $this->data['familyName'] = $familyName;
        return $this;
    }

    public function withLocale(string $locale): self
    {
        $this->data['locale'] = $locale;
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

    public function withOrcid(string $orcid): self
    {
        $this->data['orcid'] = $orcid;
        return $this;
    }

    public function build(): User
    {
        $user = new User();
        $user->setId($this->data['id']);
        $user->setUsername($this->data['username']);
        $user->setEmail($this->data['email']);
        $user->setGivenName($this->data['givenName'], $this->data['locale']);
        $user->setFamilyName($this->data['familyName'], $this->data['locale']);

        if (isset($this->data['affiliation'])) {
            $user->setAffiliation($this->data['affiliation'], $this->data['locale']);
        }
        if (isset($this->data['country'])) {
            $user->setCountry($this->data['country']);
        }
        if (isset($this->data['orcid'])) {
            $user->setOrcid($this->data['orcid']);
        }

        return $user;
    }
}

/**
 * Builder for Server mock objects
 */
class ServerBuilder
{
    private array $data = [
        'id' => 1,
        'path' => 'testserver',
        'name' => 'Test Server',
        'locale' => 'en',
        'supportedLocales' => ['en'],
        'primaryLocale' => 'en',
        'contactEmail' => 'contact@example.com',
    ];

    public function withId(int $id): self
    {
        $this->data['id'] = $id;
        return $this;
    }

    public function withPath(string $path): self
    {
        $this->data['path'] = $path;
        return $this;
    }

    public function withName(string $name): self
    {
        $this->data['name'] = $name;
        return $this;
    }

    public function withLocale(string $locale): self
    {
        $this->data['locale'] = $locale;
        return $this;
    }

    public function withSupportedLocales(array $locales): self
    {
        $this->data['supportedLocales'] = $locales;
        return $this;
    }

    public function withPrimaryLocale(string $locale): self
    {
        $this->data['primaryLocale'] = $locale;
        return $this;
    }

    public function withContactEmail(string $email): self
    {
        $this->data['contactEmail'] = $email;
        return $this;
    }

    public function build(): MockInterface
    {
        $server = Mockery::mock(Server::class)->makePartial();
        $server->setId($this->data['id']);
        $server->setPath($this->data['path']);
        $server->setName($this->data['name'], $this->data['locale']);
        $server->setPrimaryLocale($this->data['primaryLocale']);

        $server->shouldReceive('getSupportedSubmissionLocales')
            ->andReturn($this->data['supportedLocales']);
        $server->shouldReceive('getPrimaryLocale')
            ->andReturn($this->data['primaryLocale']);
        $server->shouldReceive('getContactEmail')
            ->andReturn($this->data['contactEmail']);
        $server->shouldReceive('getId')
            ->andReturn($this->data['id']);

        return $server;
    }
}

/**
 * Builder for Publication mock objects
 */
class PublicationBuilder
{
    private array $data = [
        'id' => 1,
        'submissionId' => 1,
        'status' => Submission::STATUS_PUBLISHED,
        'version' => 1,
        'locale' => 'en',
    ];

    public function withId(int $id): self
    {
        $this->data['id'] = $id;
        return $this;
    }

    public function withSubmissionId(int $submissionId): self
    {
        $this->data['submissionId'] = $submissionId;
        return $this;
    }

    public function withStatus(int $status): self
    {
        $this->data['status'] = $status;
        return $this;
    }

    public function withVersion(int $version): self
    {
        $this->data['version'] = $version;
        return $this;
    }

    public function withTitle(string $title, string $locale = 'en'): self
    {
        $this->data['title'] = $title;
        $this->data['titleLocale'] = $locale;
        return $this;
    }

    public function withAbstract(string $abstract, string $locale = 'en'): self
    {
        $this->data['abstract'] = $abstract;
        $this->data['abstractLocale'] = $locale;
        return $this;
    }

    public function withDatePublished(string $date): self
    {
        $this->data['datePublished'] = $date;
        return $this;
    }

    public function withSectionId(int $sectionId): self
    {
        $this->data['sectionId'] = $sectionId;
        return $this;
    }

    public function withAuthors(array|Collection $authors): self
    {
        $this->data['authors'] = $authors instanceof Collection ? $authors : collect($authors);
        return $this;
    }

    public function withKeywords(array $keywords): self
    {
        $this->data['keywords'] = $keywords;
        return $this;
    }

    public function withSubjects(array $subjects): self
    {
        $this->data['subjects'] = $subjects;
        return $this;
    }

    public function withLocale(string $locale): self
    {
        $this->data['locale'] = $locale;
        return $this;
    }

    public function withData(string $key, mixed $value): self
    {
        $this->data[$key] = $value;
        return $this;
    }

    public function build(): Publication
    {
        $publication = new Publication();
        $publication->setId($this->data['id']);
        $publication->setData('submissionId', $this->data['submissionId']);
        $publication->setData('status', $this->data['status']);
        $publication->setData('version', $this->data['version']);
        $publication->setData('locale', $this->data['locale']);

        if (isset($this->data['title'])) {
            $publication->setData('title', $this->data['title'], $this->data['titleLocale'] ?? 'en');
        }
        if (isset($this->data['abstract'])) {
            $publication->setData('abstract', $this->data['abstract'], $this->data['abstractLocale'] ?? 'en');
        }
        if (isset($this->data['datePublished'])) {
            $publication->setData('datePublished', $this->data['datePublished']);
        }
        if (isset($this->data['sectionId'])) {
            $publication->setData('sectionId', $this->data['sectionId']);
        }
        if (isset($this->data['authors'])) {
            $publication->setData('authors', $this->data['authors']);
        }
        if (isset($this->data['keywords'])) {
            $publication->setData('keywords', $this->data['keywords']);
        }
        if (isset($this->data['subjects'])) {
            $publication->setData('subjects', $this->data['subjects']);
        }
        if (isset($this->data['supportingAgencies'])) {
            $publication->setData('supportingAgencies', $this->data['supportingAgencies']);
        }
        if (isset($this->data['primaryContactId'])) {
            $publication->setData('primaryContactId', $this->data['primaryContactId']);
        }
        if (isset($this->data['contextId'])) {
            $publication->setData('contextId', $this->data['contextId']);
        }

        return $publication;
    }
}

/**
 * Builder for Submission mock objects
 */
class SubmissionBuilder
{
    private array $data = [
        'id' => 1,
        'contextId' => 1,
        'locale' => 'en',
        'status' => Submission::STATUS_PUBLISHED,
    ];

    private ?Publication $currentPublication = null;

    public function withId(int $id): self
    {
        $this->data['id'] = $id;
        return $this;
    }

    public function withContextId(int $contextId): self
    {
        $this->data['contextId'] = $contextId;
        return $this;
    }

    public function withLocale(string $locale): self
    {
        $this->data['locale'] = $locale;
        return $this;
    }

    public function withStatus(int $status): self
    {
        $this->data['status'] = $status;
        return $this;
    }

    public function withCurrentPublication(Publication $publication): self
    {
        $this->currentPublication = $publication;
        return $this;
    }

    public function build(): MockInterface
    {
        $submission = Mockery::mock(Submission::class)->makePartial();
        $submission->setId($this->data['id']);
        $submission->setData('contextId', $this->data['contextId']);
        $submission->setData('locale', $this->data['locale']);
        $submission->setData('status', $this->data['status']);

        $submission->shouldReceive('getId')->andReturn($this->data['id']);

        if ($this->currentPublication) {
            $submission->shouldReceive('getCurrentPublication')->andReturn($this->currentPublication);
        }

        return $submission;
    }
}

/**
 * Builder for Author mock objects
 */
class AuthorBuilder
{
    private array $data = [
        'id' => 1,
        'publicationId' => 1,
        'submissionId' => 1,
        'givenName' => 'John',
        'familyName' => 'Doe',
        'email' => 'author@example.com',
        'locale' => 'en',
    ];

    public function withId(int $id): self
    {
        $this->data['id'] = $id;
        return $this;
    }

    public function withPublicationId(int $publicationId): self
    {
        $this->data['publicationId'] = $publicationId;
        return $this;
    }

    public function withSubmissionId(int $submissionId): self
    {
        $this->data['submissionId'] = $submissionId;
        return $this;
    }

    public function withGivenName(string $givenName): self
    {
        $this->data['givenName'] = $givenName;
        return $this;
    }

    public function withFamilyName(string $familyName): self
    {
        $this->data['familyName'] = $familyName;
        return $this;
    }

    public function withEmail(string $email): self
    {
        $this->data['email'] = $email;
        return $this;
    }

    public function withLocale(string $locale): self
    {
        $this->data['locale'] = $locale;
        return $this;
    }

    public function withOrcid(string $orcid): self
    {
        $this->data['orcid'] = $orcid;
        return $this;
    }

    public function build(): Author
    {
        $author = new Author();
        $author->setId($this->data['id']);
        $author->setData('publicationId', $this->data['publicationId']);
        $author->setSubmissionId($this->data['submissionId']);
        $author->setGivenName($this->data['givenName'], $this->data['locale']);
        $author->setFamilyName($this->data['familyName'], $this->data['locale']);
        $author->setEmail($this->data['email']);

        if (isset($this->data['orcid'])) {
            $author->setOrcid($this->data['orcid']);
        }

        return $author;
    }
}

/**
 * Builder for Section mock objects
 */
class SectionBuilder
{
    private array $data = [
        'id' => 1,
        'contextId' => 1,
        'title' => 'Test Section',
        'abbrev' => 'TS',
        'locale' => 'en',
    ];

    public function withId(int $id): self
    {
        $this->data['id'] = $id;
        return $this;
    }

    public function withContextId(int $contextId): self
    {
        $this->data['contextId'] = $contextId;
        return $this;
    }

    public function withTitle(string $title): self
    {
        $this->data['title'] = $title;
        return $this;
    }

    public function withAbbrev(string $abbrev): self
    {
        $this->data['abbrev'] = $abbrev;
        return $this;
    }

    public function withLocale(string $locale): self
    {
        $this->data['locale'] = $locale;
        return $this;
    }

    public function build(): Section
    {
        $section = new Section();
        $section->setId($this->data['id']);
        $section->setContextId($this->data['contextId']);
        $section->setTitle($this->data['title'], $this->data['locale']);
        $section->setAbbrev($this->data['abbrev'], $this->data['locale']);

        return $section;
    }
}

/**
 * Builder for Category mock objects
 */
class CategoryBuilder
{
    private array $data = [
        'id' => 1,
        'contextId' => 1,
        'title' => 'Test Category',
        'path' => 'test-category',
        'locale' => 'en',
    ];

    public function withId(int $id): self
    {
        $this->data['id'] = $id;
        return $this;
    }

    public function withContextId(int $contextId): self
    {
        $this->data['contextId'] = $contextId;
        return $this;
    }

    public function withTitle(string $title): self
    {
        $this->data['title'] = $title;
        return $this;
    }

    public function withPath(string $path): self
    {
        $this->data['path'] = $path;
        return $this;
    }

    public function withLocale(string $locale): self
    {
        $this->data['locale'] = $locale;
        return $this;
    }

    public function build(): Category
    {
        $category = new Category();
        $category->setId($this->data['id']);
        $category->setContextId($this->data['contextId']);
        $category->setTitle($this->data['title'], $this->data['locale']);
        $category->setPath($this->data['path']);

        return $category;
    }
}

/**
 * Builder for UserGroup mock objects
 */
class UserGroupBuilder
{
    private array $data = [
        'id' => 1,
        'contextId' => 1,
        'roleId' => Role::ROLE_ID_AUTHOR,
        'name' => ['en' => 'Author'],
    ];

    public function withId(int $id): self
    {
        $this->data['id'] = $id;
        return $this;
    }

    public function withContextId(int $contextId): self
    {
        $this->data['contextId'] = $contextId;
        return $this;
    }

    public function withRoleId(int $roleId): self
    {
        $this->data['roleId'] = $roleId;
        return $this;
    }

    public function withName(array $name): self
    {
        $this->data['name'] = $name;
        return $this;
    }

    public function build(): UserGroup
    {
        $userGroup = new UserGroup();
        $userGroup->id = $this->data['id'];
        $userGroup->contextId = $this->data['contextId'];
        $userGroup->roleId = $this->data['roleId'];
        $userGroup->name = $this->data['name'];

        return $userGroup;
    }
}

/**
 * Builder for Galley mock objects
 */
class GalleyBuilder
{
    private array $data = [
        'id' => 1,
        'publicationId' => 1,
        'label' => 'PDF',
        'locale' => 'en',
    ];

    public function withId(int $id): self
    {
        $this->data['id'] = $id;
        return $this;
    }

    public function withPublicationId(int $publicationId): self
    {
        $this->data['publicationId'] = $publicationId;
        return $this;
    }

    public function withLabel(string $label): self
    {
        $this->data['label'] = $label;
        return $this;
    }

    public function withLocale(string $locale): self
    {
        $this->data['locale'] = $locale;
        return $this;
    }

    public function withSubmissionFileId(int $submissionFileId): self
    {
        $this->data['submissionFileId'] = $submissionFileId;
        return $this;
    }

    public function build(): Galley
    {
        $galley = new Galley();
        $galley->setId($this->data['id']);
        $galley->setData('publicationId', $this->data['publicationId']);
        $galley->setLabel($this->data['label']);
        $galley->setLocale($this->data['locale']);

        if (isset($this->data['submissionFileId'])) {
            $galley->setData('submissionFileId', $this->data['submissionFileId']);
        }

        return $galley;
    }
}

/**
 * Builder for Genre mock objects
 */
class GenreBuilder
{
    private array $data = [
        'id' => 1,
        'contextId' => 1,
        'key' => 'SUBMISSION',
        'name' => ['en' => 'Article Text'],
    ];

    public function withId(int $id): self
    {
        $this->data['id'] = $id;
        return $this;
    }

    public function withContextId(int $contextId): self
    {
        $this->data['contextId'] = $contextId;
        return $this;
    }

    public function withKey(string $key): self
    {
        $this->data['key'] = $key;
        return $this;
    }

    public function withName(array $name): self
    {
        $this->data['name'] = $name;
        return $this;
    }

    public function build(): MockInterface
    {
        $genre = Mockery::mock(Genre::class)->makePartial();
        $genre->shouldReceive('getId')->andReturn($this->data['id']);
        $genre->shouldReceive('getContextId')->andReturn($this->data['contextId']);
        $genre->shouldReceive('getKey')->andReturn($this->data['key']);

        return $genre;
    }
}

/**
 * Builder for SubmissionFile mock objects
 */
class SubmissionFileBuilder
{
    private array $data = [
        'id' => 1,
        'submissionId' => 1,
        'fileId' => 1,
        'genreId' => 1,
        'locale' => 'en',
    ];

    public function withId(int $id): self
    {
        $this->data['id'] = $id;
        return $this;
    }

    public function withSubmissionId(int $submissionId): self
    {
        $this->data['submissionId'] = $submissionId;
        return $this;
    }

    public function withFileId(int $fileId): self
    {
        $this->data['fileId'] = $fileId;
        return $this;
    }

    public function withGenreId(int $genreId): self
    {
        $this->data['genreId'] = $genreId;
        return $this;
    }

    public function withLocale(string $locale): self
    {
        $this->data['locale'] = $locale;
        return $this;
    }

    public function build(): SubmissionFile
    {
        $file = new SubmissionFile();
        $file->setId($this->data['id']);
        $file->setData('submissionId', $this->data['submissionId']);
        $file->setData('fileId', $this->data['fileId']);
        $file->setData('genreId', $this->data['genreId']);
        $file->setData('locale', $this->data['locale']);

        return $file;
    }
}
