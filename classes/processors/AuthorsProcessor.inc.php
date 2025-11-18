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

namespace PKP\Plugins\ImportExport\CSV\Classes\Processors;

use PKP\Plugins\ImportExport\CSV\Classes\CachedAttributes\CachedDaos;

class AuthorsProcessor
{
    /**
	 * Process data for Submission authors
	 *
	 * @param object $data
	 * @param string $contactEmail
	 * @param int $submissionId
	 * @param \Publication $publication
	 * @param int $userGroupId
	 * @param ?\Publication $basePublication
	 *
	 * @return void
	 */
	public static function process($data, $contactEmail, $submissionId, $publication, $userGroupId, $basePublication = null)
    {
		if (empty($data->authors) && !is_null($basePublication)) {
            self::cloneAuthorsFromBasePublication($basePublication, $publication, $submissionId);
            return;
        }

		$authorDao = CachedDaos::getAuthorDao();
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

			/** @var \Author $author */
			$author = $authorDao->newDataObject();
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

			$authorDao->insertObject($author);

			if (!$index) {
				$author->setPrimaryContact(true);
				$authorDao->updateObject($author);

                PublicationProcessor::updatePrimaryContactId($publication, $author->getId());
			}
		}
	}

	/**
	 * @param ?string $raw
	 *
	 * @return ?string
	 */
	private static function normalizeOrcid($raw)
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
	 *
	 * @param object $data
	 * @param string $contactEmail
	 * @param int $submissionId
	 * @param \Publication $publication
	 * @param int $userGrouppId
	 *
	 * @return void
     */
    public static function processMultiLocale($data, $contactEmail, $submissionId, $publication, $userGroupId)
	{
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

				CachedDaos::getAuthorDao()->updateObject($existingAuthor);

                continue;
            }

			$authorDao = CachedDaos::getAuthorDao();

			/** @var \Author $author */
            $author = $authorDao->newDataObject();
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

			$authorDao->insertObject($author);
        }
    }

	/**
     * Clone authors from base publication to new versioned publication
	 *
	 * @param \Publication $basePublication
	 * @param \Publication $newPublication
	 * @param int $submissionId
	 *
	 * @return void
     */
    private static function cloneAuthorsFromBasePublication($basePublication, $newPublication, $submissionId)
    {
        $authors = $basePublication->getData('authors');
        if (empty($authors)) {
            return;
        }

		$authorDao = CachedDaos::getAuthorDao();
        foreach ($authors as $author) {
            $newAuthor = clone $author;
            $newAuthor->setData('id', null);
            $newAuthor->setData('publicationId', $newPublication->getId());
            $newAuthor->setSubmissionId($submissionId);

            $newAuthorId = $authorDao->insertObject($newAuthor);

            if ($author->getId() === $basePublication->getData('primaryContactId')) {
                PublicationProcessor::updatePrimaryContactId($newPublication, $newAuthorId);
            }
        }
    }
}
