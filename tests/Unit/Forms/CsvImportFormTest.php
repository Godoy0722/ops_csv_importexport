<?php

/**
 * @file plugins/importexport/csv/tests/Unit/Forms/CsvImportFormTest.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class CsvImportFormTest
 *
 * @ingroup plugins_importexport_csv
 *
 * @brief Tests for CsvImportForm field configuration
 */

namespace APP\plugins\importexport\csv\tests\Unit\Forms;

use APP\plugins\importexport\csv\classes\forms\CsvImportForm;
use APP\plugins\importexport\csv\tests\BaseTestCase;

class CsvImportFormTest extends BaseTestCase
{
    public function testFormHasFourFields(): void
    {
        $form = new CsvImportForm('http://example.com/action', 'http://example.com/upload');

        $config = $form->getConfig();

        $this->assertCount(4, $config['fields']);
    }

    public function testFormIdIsCorrect(): void
    {
        $form = new CsvImportForm('http://example.com/action', 'http://example.com/upload');

        $config = $form->getConfig();

        $this->assertEquals(FORM_CSV_IMPORT, $config['id']);
    }

    public function testFormMethodIsPost(): void
    {
        $form = new CsvImportForm('http://example.com/action', 'http://example.com/upload');

        $config = $form->getConfig();

        $this->assertEquals('POST', $config['method']);
    }

    public function testFormActionIsSetCorrectly(): void
    {
        $form = new CsvImportForm('http://example.com/import', 'http://example.com/upload');

        $config = $form->getConfig();

        $this->assertEquals('http://example.com/import', $config['action']);
    }

    public function testImportTypeFieldHasTwoOptions(): void
    {
        $form = new CsvImportForm('http://example.com/action', 'http://example.com/upload');

        $config = $form->getConfig();
        $importTypeField = null;
        foreach ($config['fields'] as $field) {
            if ($field['name'] === 'importType') {
                $importTypeField = $field;
                break;
            }
        }

        $this->assertNotNull($importTypeField);
        $this->assertCount(2, $importTypeField['options']);

        $values = array_column($importTypeField['options'], 'value');
        $this->assertContains('preprints', $values);
        $this->assertContains('users', $values);
    }

    public function testSendWelcomeEmailFieldHasShowWhenCondition(): void
    {
        $form = new CsvImportForm('http://example.com/action', 'http://example.com/upload');

        $config = $form->getConfig();
        $sendWelcomeEmailField = null;
        foreach ($config['fields'] as $field) {
            if ($field['name'] === 'sendWelcomeEmail') {
                $sendWelcomeEmailField = $field;
                break;
            }
        }

        $this->assertNotNull($sendWelcomeEmailField);
        $this->assertEquals(['importType', 'users'], $sendWelcomeEmailField['showWhen']);
    }
}