<?php

/**
 * @file plugins/importexport/csv/classes/forms/CsvImportForm.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class CsvImportForm
 *
 * @ingroup plugins_importexport_csv
 *
 * @brief Form for CSV import via Settings > Website tab
 */

namespace APP\plugins\importexport\csv\classes\forms;

use PKP\components\forms\FieldOptions;
use PKP\components\forms\FieldSelect;
use PKP\components\forms\FieldUpload;
use PKP\components\forms\FormComponent;

define('FORM_CSV_IMPORT', 'csvImport');

class CsvImportForm extends FormComponent
{
    public $id = FORM_CSV_IMPORT;
    public $method = 'POST';

    public function __construct(string $action, string $uploadUrl)
    {
        $this->action = $action;

        $this
            ->addField(new FieldUpload('zipFile', [
                'label' => __('plugins.importexport.csv.form.zipFile'),
                'description' => __('plugins.importexport.csv.form.zipFile.description'),
                'isRequired' => true,
                'options' => [
                    'url' => $uploadUrl,
                    'acceptedFiles' => '.zip',
                ],
            ]))
            ->addField(new FieldSelect('importType', [
                'label' => __('plugins.importexport.csv.form.importType'),
                'isRequired' => true,
                'options' => [
                    ['value' => 'preprints', 'label' => __('plugins.importexport.csv.form.importType.preprints')],
                    ['value' => 'users', 'label' => __('plugins.importexport.csv.form.importType.users')],
                ],
                'value' => 'preprints',
            ]))
            ->addField(new FieldOptions('dryMode', [
                'label' => __('plugins.importexport.csv.form.dryMode'),
                'description' => __('plugins.importexport.csv.form.dryMode.description'),
                'type' => 'checkbox',
                'options' => [
                    ['value' => true, 'label' => __('plugins.importexport.csv.form.dryMode.enable')],
                ],
                'value' => [],
            ]))
            ->addField(new FieldOptions('sendWelcomeEmail', [
                'label' => __('plugins.importexport.csv.form.sendWelcomeEmail'),
                'type' => 'checkbox',
                'options' => [
                    ['value' => true, 'label' => __('plugins.importexport.csv.form.sendWelcomeEmail.enable')],
                ],
                'value' => [],
                'showWhen' => ['importType', 'users'],
            ]));
    }
}