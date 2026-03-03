<?php

/**
 * @file plugins/importexport/csv/classes/processors/AuthorsProcessor.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class AuthorsProcessor
 *
 * @ingroup plugins_importexport_csv
 *
 * @brief Process the authors data into the database.
 */

namespace APP\plugins\importexport\csv\classes\processors;

use APP\facades\Repo;
use APP\publication\Publication;
use APP\server\Server;
use APP\submission\Submission;
use PKP\user\User;

class AuthorsProcessor
{
    public static function process(
        object $data,
        string $contactEmail,
        int $submissionId,
        Publication $publication,
        int $userGroupId,
        ?Publication $basePublication = null,
        ?User $usernameUser = null
    ): void
    {
        if (empty($data->authors) && !is_null($basePublication)) {
            static::cloneAuthorsFromBasePublication($basePublication, $publication, $submissionId);
            return;
        }

        $authorsString = array_map('trim', explode(';', $data->authors));

        foreach ($authorsString as $index => $authorString) {
            [$givenName, $familyName, $emailAddress, $orcid, $affiliation, $biography] = static::parseAuthorString($authorString, $contactEmail);

            if ($usernameUser && static::csvAuthorMatchesUser($givenName, $familyName, $emailAddress, $usernameUser, $data->locale)) {
                continue;
            }

            $author = Repo::author()->newDataObject();

            $author->setSubmissionId($submissionId);
            $author->setUserGroupId($userGroupId);
            $author->setEmail($emailAddress);
            $author->setData('publicationId', $publication->getId());

            static::updateAuthorFromCsv($author, $givenName, $familyName, $orcid, $affiliation, $biography, $data->locale, false);

            $authorId = Repo::author()->add($author);

            if ($index === 0 && is_null($usernameUser)) {
                PublicationProcessor::updatePrimaryContactId($publication, $authorId);
            }
        }
    }

    /**
     * Create an Author from a User and add as primary contact
     */
    public static function addAuthorFromUser(
        User $user,
        Submission $submission,
        Publication $publication,
        Server $server,
        int $userGroupId
    ): int
    {
        $author = Repo::author()->newAuthorFromUser($user, $submission, $server);
        $author->setData('publicationId', $publication->getId());
        $author->setUserGroupId($userGroupId);
        $author->setSubmissionId($submission->getId());

        $authorId = Repo::author()->add($author);

        PublicationProcessor::updatePrimaryContactId($publication, $authorId);

        return $authorId;
    }

    /**
     * Update locale-specific name data on the username-derived author
     */
    public static function updateUsernameAuthorLocale(
        User $user,
        Publication $publication,
        string $locale
    ): void
    {
        $existingAuthors = $publication->getData('authors') ?: [];
        $userEmail = $user->getEmail();

        foreach ($existingAuthors as $author) {
            if (strcasecmp($author->getEmail(), $userEmail) === 0) {
                $givenName = $user->getGivenName($locale) ?: $user->getGivenName($user->getDefaultLocale());
                if ($givenName) {
                    $author->setGivenName($givenName, $locale);
                }

                $familyName = $user->getFamilyName($locale) ?: $user->getFamilyName($user->getDefaultLocale());
                if ($familyName) {
                    $author->setFamilyName($familyName, $locale);
                }

                Repo::author()->dao->update($author);
                return;
            }
        }
    }

    /**
     * Check if a CSV author entry matches a User on all three fields: givenName, familyName, and email.
     * Returns true only when ALL three match (case-insensitive), meaning it's a duplicate.
     */
    private static function csvAuthorMatchesUser(
        string $csvGivenName,
        string $csvFamilyName,
        string $csvEmail,
        User $user,
        string $locale
    ): bool
    {
        if (strcasecmp($csvEmail, $user->getEmail()) !== 0) {
            return false;
        }

        $userGivenName = $user->getGivenName($locale) ?: $user->getGivenName($user->getDefaultLocale()) ?: '';
        if (strcasecmp($csvGivenName, $userGivenName) !== 0) {
            return false;
        }

        $userFamilyName = $user->getFamilyName($locale) ?: $user->getFamilyName($user->getDefaultLocale()) ?: '';
        if (strcasecmp($csvFamilyName, $userFamilyName) !== 0) {
            return false;
        }

        return true;
    }

    /**
     * Clone authors from base publication to new versioned publication
     */
    private static function cloneAuthorsFromBasePublication(
        Publication $basePublication,
        Publication $newPublication,
        int $submissionId
    ): void
    {
        $authors = $basePublication->getData('authors') ?: [];
        if (empty($authors)) {
            return;
        }

        foreach ($authors as $author) {
            $newAuthor = Repo::author()->newDataObject();
            $newAuthor->setSubmissionId($submissionId);
            $newAuthor->setUserGroupId($author->getUserGroupId());
            $newAuthor->setGivenName($author->getGivenName(null), null);
            $newAuthor->setFamilyName($author->getFamilyName(null), null);
            $newAuthor->setEmail($author->getEmail());
            $newAuthor->setData('publicationId', $newPublication->getId());
            $newAuthor->setOrcid($author->getOrcid());
            $newAuthor->setBiography($author->getBiography(null), null);

            foreach ($author->getAffiliations() as $affiliation) {
                $newAffiliation = Repo::affiliation()->newDataObject();
                $newAffiliation->setRor($affiliation->getRor());
                $newAffiliation->setName($affiliation->getName());
                $newAuthor->addAffiliation($newAffiliation);
            }

            $newAuthorId = Repo::author()->add($newAuthor);

            if ($author->getId() === $basePublication->getData('primaryContactId')) {
                PublicationProcessor::updatePrimaryContactId($newPublication, $newAuthorId);
            }
        }
    }

    private static function normalizeOrcid(?string $raw): ?string
    {
        if (empty($raw)) {
            return null;
        }

        $value = trim($raw);
        $id = $value;
        if (preg_match('/^https?:\\/\\/orcid\\.org\\/(.+)$/i', $value, $m)) {
            $id = $m[1];
        }

        $id = mb_strtoupper(str_replace([' ', '-'], '', $id));
        if (!preg_match('/^\\d{15}[\\dX]$/', $id)) {
            return null;
        }

        $parts = mb_str_split($id, 4);
        $hyphenated = implode('-', $parts);
        return 'https://orcid.org/' . $hyphenated;
    }

    /**
     * Process authors for multi-locale import (adds locale data to existing authors)
     */
    public static function processMultiLocale(
        object $data,
        string $contactEmail,
        int $submissionId,
        Publication $publication,
        int $userGroupId
    ): void {
        if (empty($data->authors)) {
            return; // No new author data to add
        }

        $authorsString = array_map('trim', explode(';', $data->authors));
        $existingAuthors = $publication->getData('authors') ?: [];

        if (empty($existingAuthors)) {
            return;
        }

        foreach ($authorsString as $authorString) {
            [$givenName, $familyName, $emailAddress, $orcid, $affiliation, $biography] = static::parseAuthorString($authorString, $contactEmail);

            $existingAuthor = null;
            foreach ($existingAuthors as $author) {
                if ($author->getEmail() === $emailAddress) {
                    $existingAuthor = $author;
                    break;
                }
            }
            $author = $existingAuthor;
            if (!$author) {
                $author = Repo::author()->newDataObject();
                $author->setSubmissionId($submissionId);
                $author->setUserGroupId($userGroupId);
                $author->setData('publicationId', $publication->getId());
                $author->setEmail($emailAddress);
            }

            static::updateAuthorFromCsv(
                $author,
                $givenName,
                $familyName,
                $orcid,
                $affiliation,
                $biography,
                $data->locale,
                $existingAuthor !== null
            );

            $existingAuthor
                ? Repo::author()->dao->update($author)
                : Repo::author()->add($author);
        }
    }

    /**
     * Parse author string components
     */
    private static function parseAuthorString(string $authorString, string $contactEmail): array
    {
        $authorParts = array_map('trim', explode(',', $authorString));
        $givenName = $authorParts[0] ?? '';
        $familyName = $authorParts[1] ?? '';
        $emailAddress = $authorParts[2] ?? '';
        $orcid = $authorParts[3] ?? '';
        $affiliation = $authorParts[4] ?? '';
        $biography = $authorParts[5] ?? '';

        if (empty($emailAddress)) {
            $emailAddress = $contactEmail;
        }

        return [$givenName, $familyName, $emailAddress, $orcid, $affiliation, $biography];
    }

    /**
     * Update author object with parsed data
     */
    private static function updateAuthorFromCsv(
        object $author,
        string $givenName,
        string $familyName,
        string $orcid,
        string $affiliation,
        string $biography,
        string $locale,
        bool $isExistingAuthor
    ): void {
        if (!$isExistingAuthor || !empty($givenName)) {
            $author->setGivenName($givenName, $locale);
        }
        if (!$isExistingAuthor || !empty($familyName)) {
            $author->setFamilyName($familyName, $locale);
        }

        $normalizedOrcid = static::normalizeOrcid($orcid);
        if (!empty($normalizedOrcid)) {
            $author->setOrcid($normalizedOrcid);
        }

        if ($affiliation) {
            $existingAffiliations = $author->getAffiliations();
            if (!empty($existingAffiliations)) {
                $firstAffiliation = reset($existingAffiliations);
                if ($firstAffiliation) {
                    $firstAffiliation->setName((string) $affiliation, $locale);
                }
            } else {
                $affiliationEntity = Repo::affiliation()->newDataObject();
                $affiliationEntity->setName((string) $affiliation, $locale);
                $author->addAffiliation($affiliationEntity);
            }
        }

        if (!empty($biography)) {
            $author->setBiography($biography, $locale);
        }
    }
}
