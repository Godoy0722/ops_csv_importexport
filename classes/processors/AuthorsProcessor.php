<?php

/**
 * @file plugins/importexport/csv/classes/processors/AuthorsProcessor.php
 *
 * Copyright (c) 2014-2025 Simon Fraser University
 * Copyright (c) 2003-2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class AuthorsProcessor
 *
 * @ingroup plugins_importexport_csv
 *
 * @brief Process the authors data into the database.
 */

namespace APP\plugins\importexport\csv\classes\processors;

use APP\author\Author;
use APP\facades\Repo;
use APP\publication\Publication;

class AuthorsProcessor
{
	public static function process(
        object $data,
        string $contactEmail,
        int $submissionId,
        Publication $publication,
        int $userGroupId,
        ?Publication $basePublication = null
    ) {
        if (empty($data->authors) && !is_null($basePublication)) {
            self::cloneAuthorsFromBasePublication($basePublication, $publication, $submissionId);
            return;
        }

		$authorsString = array_map('trim', explode(';', $data->authors));

        foreach ($authorsString as $index => $authorString) {
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

            $author = Repo::author()->newDataObject();

            $author->setSubmissionId($submissionId);
            $author->setUserGroupId($userGroupId);
            $author->setGivenName($givenName, $data->locale);
            $author->setFamilyName($familyName, $data->locale);
            $author->setEmail($emailAddress);
            $author->setAffiliation($affiliation, $data->locale);
            $author->setData('publicationId', $publication->getId());

            $normalizedOrcid = self::normalizeOrcid($orcid);
            if (!empty($normalizedOrcid)) {
                $author->setOrcid($normalizedOrcid);
            }

            $authorId = Repo::author()->add($author);

			if ($index === 0) {
                Repo::author()->edit($author, ['primaryContact' => true]);
                PublicationProcessor::updatePrimaryContactId($publication, $authorId);
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
        /** @var Author[] */
        $existingAuthors = $publication->getData('authors');

        foreach ($authorsString as $index => $authorString) {
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
            if (!empty($existingAuthors)) {
                foreach ($existingAuthors as $author) {
                    if ($author->getEmail() === $emailAddress) {
                        $existingAuthor = $author;
                        break;
                    }
                }
            }

            if ($existingAuthor) {
                $existingAuthor->setGivenName($givenName, $data->locale);
                $existingAuthor->setFamilyName($familyName, $data->locale);

                $normalizedOrcid = self::normalizeOrcid($orcid);
                if (!empty($normalizedOrcid)) {
                    $existingAuthor->setOrcid($normalizedOrcid);
                }

                if ($affiliation) {
                    $existingAuthor->setAffiliation($affiliation, $data->locale);
                }

                Repo::author()->dao->update($existingAuthor);

                continue;
            }

            $author = Repo::author()->newDataObject();
            $author->setSubmissionId($submissionId);
            $author->setUserGroupId($userGroupId);
            $author->setGivenName($givenName, $data->locale);
            $author->setFamilyName($familyName, $data->locale);
            $author->setEmail($emailAddress);
            $author->setData('publicationId', $publication->getId());

            $normalizedOrcidNew = self::normalizeOrcid($orcid);
            if (!empty($normalizedOrcidNew)) {
                $author->setOrcid($normalizedOrcidNew);
            }

            if ($affiliation) {
                $author->setAffiliation($affiliation, $data->locale);
            }

            Repo::author()->add($author);
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
        $authors = $basePublication->getData('authors');
        if (empty($authors)) {
            return;
        }

        foreach ($authors as $author) {
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
}
