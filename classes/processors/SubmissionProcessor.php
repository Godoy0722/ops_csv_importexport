<?php

/**
 * @file plugins/importexport/csv/classes/processors/SubmissionProcessor.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubmissionProcessor
 *
 * @ingroup plugins_importexport_csv
 *
 * @brief Processes the submission data into the database.
 */

namespace APP\plugins\importexport\csv\classes\processors;

use APP\plugins\importexport\csv\shared\processors\SubmissionProcessor as SharedSubmissionProcessor;
use APP\publication\Publication;
use APP\server\Server;
use APP\submission\Submission;

class SubmissionProcessor extends SharedSubmissionProcessor
{
    public static function process(object $data, Publication $publication, Server $server): Submission
    {
        $normalizedAbstract = PublicationProcessor::normalizeAbstractToHtml($data->preprintAbstract);
        $dateSubmitted = $data->dateSubmitted ?? $data->datePosted;

        return parent::processCommons($data->locale, $publication, $server, $normalizedAbstract, $dateSubmitted);
    }
}
