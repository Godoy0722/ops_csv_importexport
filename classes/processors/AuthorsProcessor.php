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

class AuthorsProcessor
{
    public static function process(object $data, string $contactEmail, int $submissionId, Publication $publication, int $userGroupId, ?Publication $basePublication = null)
    {
        // @review I think I didn't see this cloneAuthorsFromBasePublication() on the processMultiLocale
        if (empty($data->authors) && !is_null($basePublication)) {
            // @review All self:: can be replaced by static::, but a big deal, but in general, that's the expected behavior (call a possible extended implementation instead of a fixed one)
            static::cloneAuthorsFromBasePublication($basePublication, $publication, $submissionId);
            return;
        }

        $authorsString = array_map('trim', explode(';', $data->authors));

        foreach ($authorsString as $index => $authorString) {
            // @review Not needed to initialize the variables here, they will be overwritten later
            $givenName = $familyName = $emailAddress = $orcid = $affiliation = null;
            $authorParts = array_map('trim', explode(',', $authorString));
            $givenName = $authorParts[0] ?? '';
            $familyName = $authorParts[1] ?? '';
            // @review As the email is important, maybe it makes sense to validate it too
            $emailAddress = $authorParts[2] ?? '';
            $orcid = $authorParts[3] ?? '';
            $affiliation = $authorParts[4] ?? '';

            if (empty($emailAddress)) {
                $emailAddress = $contactEmail;
            }

            $author = Repo::author()->newDataObject();

            $author->setSubmissionId($submissionId);
            $author->setUserGroupId($userGroupId);
            $author->setGivenName($givenName, $data->locale);
            $author->setFamilyName($familyName, $data->locale);
            $author->setEmail($emailAddress);
            $author->setData('publicationId', $publication->getId());

            $normalizedOrcid = static::normalizeOrcid($orcid);
            if (!empty($normalizedOrcid)) {
                $author->setOrcid($normalizedOrcid);
            }

            if ($affiliation) {
                $affiliationEntity = Repo::affiliation()->newDataObject();
                $affiliationEntity->setName((string) $affiliation, $data->locale);

                $author->addAffiliation($affiliationEntity);
            }


            $authorId = Repo::author()->add($author);

            if ($index === 0) {
                // @review This is not present on the multilocale variant, anyway, I think it should be removed from here and also from the codebase (the source of truth is the publication)
                Repo::author()->edit($author, ['primaryContact' => true]);
                PublicationProcessor::updatePrimaryContactId($publication, $authorId);
            }
        }
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
        $authors = $basePublication->getData('authors') ?: []; // @review In case it can be empty... Then the checks can be simplified below
        foreach ($authors as $author) {
            // @review This would be nice, but given the clone doesn't clone sub-objects, we might have problems (e.g. $author->subObjectThatShouldNotBeReusedOnTheNewAuthor), like changing entitites/references of the cloned object
            $newAuthor = clone $author;
            $newAuthor->setData('id', null);
            $newAuthor->setData('publicationId', $newPublication->getId());
            $newAuthor->setSubmissionId($submissionId);
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
            $givenName = $familyName = $emailAddress = $orcid = $affiliation = null;
            $authorParts = array_map('trim', explode(',', $authorString));
            $givenName = $authorParts[0] ?? '';
            $familyName = $authorParts[1] ?? '';
            $emailAddress = $authorParts[2] ?? '';
            $orcid = $authorParts[3] ?? '';
            $affiliation = $authorParts[4] ?? '';

            if (empty($emailAddress)) {
                $emailAddress = $contactEmail;
            }

            $existingAuthor = null;
            foreach ($existingAuthors as $author) {
                if ($author->getEmail() === $emailAddress) {
                    $existingAuthor = $author;
                    break;
                }
            }
            /* @review We can check if the "$existingAuthor" wasn't found and create one
            if (!$existingAuthor) {
                $author = Repo::author()->newDataObject();
            }

            Then the code should be basically the same, and we can remove the duplicated pieces...
            At the end it's just needed to do a small check to update errr: `if ($existingAuthor) Repo::author()->dao->update($author); else Repo::author()->add($author);`
            */
            if ($existingAuthor) {
                $existingAuthor->setGivenName($givenName, $data->locale);
                $existingAuthor->setFamilyName($familyName, $data->locale);

                $normalizedOrcid = static::normalizeOrcid($orcid);
                if (!empty($normalizedOrcid)) {
                    $existingAuthor->setOrcid($normalizedOrcid);
                }

                if ($affiliation) {
                    $existingAffiliations = $existingAuthor->getAffiliations();

                    if (!empty($existingAffiliations)) {
                        $firstAffiliation = reset($existingAffiliations);
                        if ($firstAffiliation) {
                            $firstAffiliation->setName((string) $affiliation, $data->locale);
                        }
                    } else {
                        $affiliationEntity = Repo::affiliation()->newDataObject();
                        $affiliationEntity->setName((string) $affiliation, $data->locale);
                        $existingAuthor->addAffiliation($affiliationEntity);
                    }
                }

                Repo::author()->dao->update($existingAuthor);
            } else {
                // Create new author if not found (shouldn't happen often in multi-locale imports)
                $author = Repo::author()->newDataObject();
                $author->setSubmissionId($submissionId);
                $author->setUserGroupId($userGroupId);
                $author->setGivenName($givenName, $data->locale);
                $author->setFamilyName($familyName, $data->locale);
                $author->setEmail($emailAddress);
                $author->setData('publicationId', $publication->getId());

                $normalizedOrcidNew = static::normalizeOrcid($orcid);
                if (!empty($normalizedOrcidNew)) {
                    $author->setOrcid($normalizedOrcidNew);
                }

                if ($affiliation) {
                    $affiliationEntity = Repo::affiliation()->newDataObject();
                    $affiliationEntity->setName((string) $affiliation, $data->locale);
                    $author->addAffiliation($affiliationEntity);
                }

                Repo::author()->add($author);
            }
        }
    }
}
