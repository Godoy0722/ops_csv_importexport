<?php

/**
 * @file plugins/importexport/csv/CSVImportExportPlugin.inc.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class CSVImportExportPlugin
 * @ingroup plugins_importexport_csv
 *
 * @brief CSV import/export plugin
 */

namespace APP\plugins\importexport\csv;

use APP\core\Application;
use APP\facades\Repo;
use APP\plugins\importexport\csv\classes\commands\PreprintCommand;
use APP\plugins\importexport\csv\classes\commands\UserCommand;
use PKP\plugins\ImportExportPlugin;
use PKP\user\User;

class CSVImportExportPlugin extends ImportExportPlugin
{
    /** @var string Command being used from CLI (supports "issues" or "users") */
    private string $command = '';

    /** @var string Username for authentication */
    private string $username = '';

    /** @var User|null Authenticated user instance */
    private ?User $user = null;

    /** @var string Source directory for import/export */
    private string $sourceDir = '';

    /** @var bool Whether to send welcome email */
    private bool $sendWelcomeEmail = false;

    /** @copydoc Plugin::register() */
    public function register($category, $path, $mainContextId = null): bool
    {
        if (!parent::register($category, $path, $mainContextId)) {
            return false;
        }

        if (!Application::isUnderMaintenance() && $this->getEnabled()) {
            $this->addLocaleData();
        }

        return true;
    }

    /**
     * @copydoc Plugin::getDisplayName()
     */
    public function getDisplayName(): string
    {
        return __('plugins.importexport.csv.displayName');
    }

    /**
     * @copydoc Plugin::getDescription()
     */
    public function getDescription(): string
    {
        return __('plugins.importexport.csv.description');
    }

    /**
     * @copydoc Plugin::getName()
     */
    public function getName(): string
    {
        return 'CSVImportExportPlugin';
    }

    /**
     * @copydoc PKPImportExportPlugin::usage
     */
    public function usage($scriptName): void
    {
        echo __('plugins.importexport.csv.cliUsage', [
            'scriptName' => $scriptName,
            'pluginName' => $this->getName()
        ]) . "\n\n";
        echo __('plugins.importexport.csv.cliUsage.examples', [
            'scriptName' => $scriptName,
            'pluginName' => $this->getName()
        ]) . "\n\n";
    }

    /**
     * @see PKPImportExportPlugin::executeCLI()
     */
    public function executeCLI($scriptName, &$args): void
    {
        $startTime = microtime(true);
        $this->command = array_shift($args);
        $this->username = array_shift($args);
        $this->sourceDir = array_shift($args);
        $this->sendWelcomeEmail = array_shift($args) === 'true' ?? false; // @review I think it's better to check against a value or just use a flag "--sendWelcomeEmail"

        if (! in_array($this->command, ['preprints', 'users']) || !$this->sourceDir || !$this->username) {
            $this->usage($scriptName);
            exit(1);
        }

        if (! is_dir($this->sourceDir)) {
            echo __('plugins.importexport.csv.unknownSourceDir', ['sourceDir' => $this->sourceDir]) . "\n";
            exit(1);
        }

        $this->validateUser();

        match ($this->command) {
            'preprints' => (new PreprintCommand($this->sourceDir, $this->user))->run(),
            'users' => (new UserCommand($this->sourceDir, $this->user, $this->sendWelcomeEmail))->run(),
            default => throw new \InvalidArgumentException(__('plugins.importexport.csv.invalidCommand', ['command' => $this->command])),
        };

        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;
        echo __('plugins.importexport.csv.ExecutedInNSeconds', ['seconds' => number_format($executionTime, 2)]);
    }

    private function validateUser(): void
    {
        $this->user = Repo::user()->getByUsername($this->username);
        if (!$this->user) {
            echo __('plugins.importexport.csv.unknownUser', ['username' => $this->username]) . "\n";
            exit(1);
        }
    }
}
