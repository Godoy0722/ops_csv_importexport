<?php

/**
 * @file plugins/importexport/csv/classes/processors/FundersProcessor.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class FundersProcessor
 *
 * @ingroup plugins_importexport_csv
 *
 * @brief Process funder data into the database using the Funding plugin.
 */

namespace APP\plugins\importexport\csv\classes\processors;

use APP\plugins\generic\funding\classes\Funder;
use APP\plugins\generic\funding\classes\FunderAward;
use APP\plugins\importexport\csv\classes\cachedAttributes\CachedDaos;
use APP\publication\Publication;
use APP\submission\Submission;
use PKP\db\DAORegistry;
use PKP\db\DAOResultFactory;
use PKP\plugins\PluginRegistry;

class FundersProcessor
{
    /**
     * Check if the Funding plugin is enabled and properly loaded with DAOs registered.
     */
    public static function isFundingPluginEnabled(int $contextId): bool
    {
        $fundingPlugin = PluginRegistry::getPlugin('generic', 'FundingPlugin');

        if (!$fundingPlugin) {
            PluginRegistry::loadCategory('generic', true, $contextId);
            $fundingPlugin = PluginRegistry::getPlugin('generic', 'FundingPlugin');
        }

        if (!$fundingPlugin || !$fundingPlugin->getEnabled($contextId)) {
            return false;
        }

        return true;
    }

    /**
     * Check if Crossref registry validation is enabled in the Funding plugin settings.
     * This corresponds to the 'enableGrantIdValidation' setting.
     */
    public static function isCrossrefValidationEnabled(int $contextId): bool
    {
        $fundingPlugin = PluginRegistry::getPlugin('generic', 'FundingPlugin');

        if (!$fundingPlugin) {
            PluginRegistry::loadCategory('generic', true, $contextId);
            $fundingPlugin = PluginRegistry::getPlugin('generic', 'FundingPlugin');
        }

        if (!$fundingPlugin || !$fundingPlugin->getEnabled($contextId)) {
            return false;
        }

        return (bool) $fundingPlugin->getSetting($contextId, 'enableGrantIdValidation');
    }

    /**
     * Process funders for a new submission.
     *
     * Funder format: "FunderName,FunderIdentification,Award1|Award2;FunderName2,FunderIdentification2,Award3"
     * - Each funder is separated by `;`
     * - Funder fields are separated by `,`
     * - Multiple awards for the same funder are separated by `|`
     *
     * Note: Funders are at submission level, not publication level.
     * This means all versions of the same submission share the same funders.
     */
    public static function process(
        object $data,
        Submission $submission,
        int $contextId,
        ?Publication $basePublication = null
    ): void {
        if (!self::isFundingPluginEnabled($contextId)) {
            return;
        }

        if (self::submissionHasFunders($submission->getId())) {
            return;
        }

        if (empty($data->funders) && !is_null($basePublication)) {
            $baseSubmissionId = $basePublication->getData('submissionId');
            if ($baseSubmissionId !== $submission->getId()) {
                self::cloneFundersFromSubmission($baseSubmissionId, $submission->getId(), $contextId);
            }
            return;
        }

        if (empty($data->funders)) {
            return;
        }

        self::createFundersFromString($data->funders, $submission->getId(), $contextId);
    }

    /**
     * Check if a submission already has funders
     */
    private static function submissionHasFunders(int $submissionId): bool
    {
        $funderDao = CachedDaos::getFunderDao();

        /** @var DAOResultFactory<Funder> */
        $existingFunders = $funderDao->getBySubmissionId($submissionId);
        return $existingFunders->next() !== null;
    }

    /**
     * Process funders for multi-locale import.
     * For multi-locale, funders are shared across locales (they're linked to submission, not publication).
     * This method only adds new funders if none exist for the submission.
     */
    public static function processMultiLocale(
        object $data,
        Submission $submission,
        int $contextId
    ): void {
        if (!self::isFundingPluginEnabled($contextId)) {
            return;
        }

        if (empty($data->funders)) {
            return;
        }

        if (self::submissionHasFunders($submission->getId())) {
            return;
        }

        self::createFundersFromString($data->funders, $submission->getId(), $contextId);
    }

    /**
     * Parse and create funders from the CSV string format.
     *
     * This method follows the same business rules as FunderForm::execute()
     * from the Funding plugin to ensure consistency.
     */
    private static function createFundersFromString(string $fundersString, int $submissionId, int $contextId): void
    {
        $funderDao = CachedDaos::getFunderDao();
        $funderAwardDao = CachedDaos::getFunderAwardDao();

        $fundersArray = array_map('trim', explode(';', $fundersString));

        foreach ($fundersArray as $funderString) {
            if (empty($funderString)) {
                continue;
            }

            $funderParts = array_map('trim', explode(',', $funderString));

            $funderName = $funderParts[0] ?? '';
            $funderIdentification = $funderParts[1] ?? '';
            $awardsString = $funderParts[2] ?? '';

            if (empty($funderName)) {
                continue;
            }

            $funder = $funderDao->newDataObject();
            $funder->setContextId($contextId);
            $funder->setSubmissionId($submissionId);
            $funder->setFunderIdentification($funderIdentification);
            $funder->setFunderName($funderName);

            $funderId = $funderDao->insertObject($funder);

            // Create awards if provided
            if (!empty($awardsString) && $funderId) {
                $awardsArray = array_map('trim', explode('|', $awardsString));

                foreach ($awardsArray as $awardNumber) {
                    if (empty($awardNumber)) {
                        continue;
                    }

                    $funderAward = $funderAwardDao->newDataObject();
                    $funderAward->setFunderId($funderId);
                    $funderAward->setFunderAwardNumber($awardNumber);
                    $funderAwardDao->insertObject($funderAward);
                }
            }
        }
    }

    /**
     * Clone funders from a base submission to a new submission
     */
    private static function cloneFundersFromSubmission(int $baseSubmissionId, int $newSubmissionId, int $contextId): void
    {
        $funderDao = CachedDaos::getFunderDao();
        $funderAwardDao = CachedDaos::getFunderAwardDao();

        /** @var DAOResultFactory<Funder> */
        $baseFunders = $funderDao->getBySubmissionId($baseSubmissionId);

        /** @var Funder|null $baseFunder */
        while ($baseFunder = $baseFunders->next()) {
            // Create new funder
            $newFunder = $funderDao->newDataObject();
            $newFunder->setContextId($contextId);
            $newFunder->setSubmissionId($newSubmissionId);
            $newFunder->setFunderIdentification($baseFunder->getFunderIdentification());
            $newFunder->setFunderName($baseFunder->getFunderName());

            $newFunderId = $funderDao->insertObject($newFunder);

            if ($newFunderId) {
                /** @var DAOResultFactory<FunderAward> */
                $baseAwards = $funderAwardDao->getByFunderId($baseFunder->getId());
                /** @var FunderAward $baseAward */
                while ($baseAward = $baseAwards->next()) {
                    $newAward = $funderAwardDao->newDataObject();
                    $newAward->setFunderId($newFunderId);
                    $newAward->setFunderAwardNumber($baseAward->getFunderAwardNumber());
                    $funderAwardDao->insertObject($newAward);
                }
            }
        }
    }

    /**
     * Validate funders string format.
     * Returns null if valid, error message if invalid.
     */
    public static function validateFundersFormat(?string $fundersString): ?string
    {
        if (empty($fundersString)) {
            return null;
        }

        $fundersArray = array_map('trim', explode(';', $fundersString));

        foreach ($fundersArray as $index => $funderString) {
            if (empty($funderString)) {
                continue;
            }

            $funderParts = array_map('trim', explode(',', $funderString));
            $funderName = $funderParts[0] ?? '';

            if (empty($funderName)) {
                return __('plugins.importexport.csv.invalidFunderFormat', ['index' => $index + 1]);
            }
        }

        return null;
    }
}
