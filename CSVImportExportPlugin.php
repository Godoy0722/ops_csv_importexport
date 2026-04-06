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
use APP\plugins\importexport\csv\classes\exceptions\ImportLockException;
use APP\plugins\importexport\csv\classes\exceptions\ZipExtractionException;
use APP\plugins\importexport\csv\classes\forms\CsvImportForm;
use APP\plugins\importexport\csv\classes\handlers\ZipExtractor;
use APP\plugins\importexport\csv\classes\store\ImportResultStore;
use PKP\core\JSONMessage;
use PKP\core\PKPApplication;
use PKP\core\PKPRequest;
use PKP\file\TemporaryFileManager;
use PKP\plugins\ImportExportPlugin;
use PKP\user\User;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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

    /** @var bool Whether the result was handled by an operation handler */
    public bool $isResultManaged = false;

    /** @var string JSON result string from operation handlers */
    public string $result = '';

    /** @copydoc Plugin::register() */
    public function register($category, $path, $mainContextId = null): bool
    {
        if (!parent::register($category, $path, $mainContextId)) {
            return false;
        }

        if (!Application::isUnderMaintenance() && $this->getEnabled()) {
            $this->addLocaleData();
            \HookRegistry::register('Template::Settings::website', [$this, 'callbackShowWebsiteSettingsTab']);

            $request = Application::get()->getRequest();
            $templateMgr = \APP\template\TemplateManager::getManager($request);
            $scriptUrl = $request->getBaseUrl() . '/' . $this->getPluginPath() . '/scripts/csvImportResults.js';
            $templateMgr->addJavaScript('csvImportResults', $scriptUrl, [
                'contexts' => ['backend'],
                'priority' => $templateMgr::STYLE_SEQUENCE_LAST,
            ]);
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
     * Hook callback for Template::Settings::website — injects the CSV import tab.
     */
    public function callbackShowWebsiteSettingsTab(string $hookName, array $args): bool
    {
        $templateMgr = $args[1];
        $output = &$args[2];
        $request = Application::get()->getRequest();

        $form = new CsvImportForm(
            $request->getDispatcher()->url($request, PKPApplication::ROUTE_PAGE, null, 'management', 'importexport', ['plugin', $this->getName(), 'import']),
            $request->getDispatcher()->url($request, PKPApplication::ROUTE_API, $request->getContext()->getPath(), 'temporaryFiles')
        );

        $state = $templateMgr->getTemplateVars('state');
        $state['components'][FORM_CSV_IMPORT] = $form->getConfig();
        $templateMgr->assign('state', $state);

        $output .= $templateMgr->fetch($this->getTemplateResource('settingsForm.tpl'));

        $downloadBaseUrl = $request->getDispatcher()->url(
            $request,
            PKPApplication::ROUTE_PAGE,
            null,
            'management',
            'importexport',
            ['plugin', $this->getName(), 'downloadInvalidCsv']
        );
        $configJson = json_encode([
            'formId' => FORM_CSV_IMPORT,
            'downloadBaseUrl' => $downloadBaseUrl,
            'labels' => [
                'dryModeTitle' => __('plugins.importexport.csv.results.dryModeTitle'),
                'importCompleteTitle' => __('plugins.importexport.csv.results.importCompleteTitle'),
                'importType' => __('plugins.importexport.csv.results.importType'),
                'filesProcessed' => __('plugins.importexport.csv.results.filesProcessed'),
                'totalRows' => __('plugins.importexport.csv.results.totalRows'),
                'successfulRows' => __('plugins.importexport.csv.results.successfulRows'),
                'failedRows' => __('plugins.importexport.csv.results.failedRows'),
                'invalidFiles' => __('plugins.importexport.csv.results.invalidFiles'),
            ],
        ]);
        $output .= '<script>window.csvImportPluginConfig = ' . $configJson . ';</script>';

        return false;
    }

    /**
     * @copydoc ImportExportPlugin::display()
     */
    public function display($args, $request)
    {
        parent::display($args, $request);

        ZipExtractor::cleanupExpired(
            sys_get_temp_dir() . '/csv_import_results'
        );

        $op = array_shift($args) ?? '';

        switch ($op) {
            case 'index':
            case '':
                $this->isResultManaged = true;
                break;
            case 'uploadZip':
                $this->handleUploadZip($request);
                break;
            case 'import':
                $this->handleImport($request);
                break;
            case 'pollResult':
                $this->handlePollResult($request);
                break;
            case 'downloadInvalidCsv':
                $this->handleDownloadInvalidCsv($request);
                break;
            default:
                throw new NotFoundHttpException();
        }
    }

    private function verifyCsrf(PKPRequest $request): void
    {
        $headerToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        if ($headerToken === null || $headerToken !== $request->getSession()->token()) {
            throw new \Exception('CSRF mismatch!');
        }
    }

    private function sendJsonResponse(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        $this->result = json_encode($data);
        echo $this->result;
        $this->isResultManaged = true;
    }

    private function handleUploadZip(PKPRequest $request): void
    {
        $this->verifyCsrf($request);

        $user = $request->getUser();
        $temporaryFileManager = new TemporaryFileManager();
        $temporaryFile = $temporaryFileManager->handleUpload('uploadedFile', $user->getId());

        if (!$temporaryFile) {
            $this->sendJsonResponse(['errorMessage' => __('plugins.importexport.csv.uploadFailed')], 400);
            return;
        }

        $this->sendJsonResponse(['temporaryFileId' => $temporaryFile->getId()]);
    }

    private function handleImport(PKPRequest $request): void
    {
        $this->verifyCsrf($request);

        $user = $request->getUser();
        $importType = $request->getUserVar('importType');
        $dryMode = (bool) $request->getUserVar('dryMode');
        $sendWelcomeEmail = (bool) $request->getUserVar('sendWelcomeEmail');

        if (!in_array($importType, ['preprints', 'users'])) {
            $this->sendJsonResponse(['errorMessage' => __('plugins.importexport.csv.invalidImportType', ['importType' => $importType])], 400);
            return;
        }

        $importFile = $request->getUserVar('importFile');
        $temporaryFileId = is_array($importFile) ? ($importFile['temporaryFileId'] ?? null) : $request->getUserVar('temporaryFileId');
        $temporaryFileManager = new TemporaryFileManager();
        $temporaryFile = $temporaryFileManager->getFile($temporaryFileId, $user->getId());

        if (!$temporaryFile) {
            $this->sendJsonResponse(['errorMessage' => __('plugins.importexport.csv.uploadFailed')], 400);
            return;
        }

        try {
            set_time_limit(1200);

            $filePath = $temporaryFile->getFilePath();
            $extension = strtolower(pathinfo($temporaryFile->getOriginalFileName(), PATHINFO_EXTENSION));

            if ($extension === 'zip') {
                $extractor = new ZipExtractor();
                $extractDir = $extractor->extract($filePath);
                $sourceDir = ZipExtractor::resolveSourceDir($extractDir);
            } else {
                $sourceDir = sys_get_temp_dir() . '/csv_import_' . bin2hex(random_bytes(16));
                mkdir($sourceDir, 0700, true);
                copy($filePath, $sourceDir . '/' . $temporaryFile->getOriginalFileName());
            }

            ob_start();
            $result = match ($importType) {
                'preprints' => (new PreprintCommand($sourceDir, $user, $dryMode))->run(),
                'users' => (new UserCommand($sourceDir, $user, $sendWelcomeEmail, $dryMode))->run(),
            };
            $capturedOutput = ob_get_clean();

            $uuid = bin2hex(random_bytes(16));
            $storeDir = sys_get_temp_dir() . '/csv_import_results';
            $store = new ImportResultStore($storeDir);

            $invalidFiles = [];
            foreach ($result['perFile'] as $fileResult) {
                if ($fileResult['invalidFile'] !== null) {
                    $invalidFiles[] = $fileResult['invalidFile'];
                }
            }

            $store->save($uuid, [
                'status' => $result['failedRows'] > 0 ? 'partial' : 'success',
                'importType' => $importType,
                'rowsProcessed' => $result['totalRows'],
                'rowsFailed' => $result['failedRows'],
                'capturedOutput' => $capturedOutput,
                'perFileResults' => $invalidFiles,
                'sourceDir' => $sourceDir,
            ]);

            $this->sendJsonResponse([
                'uuid' => $uuid,
                'importType' => $importType,
                'dryMode' => $dryMode,
                'filesProcessed' => $result['filesProcessed'],
                'totalRows' => $result['totalRows'],
                'successfulRows' => $result['successfulRows'],
                'failedRows' => $result['failedRows'],
                'invalidFiles' => $invalidFiles,
                'perFile' => $result['perFile'],
            ]);

        } catch (ImportLockException $e) {
            $this->sendJsonResponse(['errorMessage' => $e->getMessage()], 403);
        } catch (ZipExtractionException $e) {
            $this->sendJsonResponse(['errorMessage' => __('plugins.importexport.csv.zipExtractionFailed', ['reason' => $e->getMessage()])], 400);
        }
    }

    private function handlePollResult(PKPRequest $request): void
    {
        $uuid = $request->getUserVar('uuid');
        $storeDir = sys_get_temp_dir() . '/csv_import_results';
        $store = new ImportResultStore($storeDir);
        $result = $store->get($uuid);

        if ($result === null) {
            $this->sendJsonResponse(['done' => false]);
        } else {
            $this->sendJsonResponse([
                'done'           => true,
                'status'         => $result['status'],
                'importType'     => $result['importType'],
                'rowsProcessed'  => $result['rowsProcessed'],
                'rowsFailed'     => $result['rowsFailed'],
                'capturedOutput' => $result['capturedOutput'],
                'invalidFiles'   => $result['perFileResults'] ?? [],
            ]);
        }
    }

    private function handleDownloadInvalidCsv(PKPRequest $request): void
    {
        $uuid = $request->getUserVar('uuid');
        $filename = basename($request->getUserVar('filename') ?? '');

        if (empty($filename) || empty($uuid)) {
            throw new NotFoundHttpException();
        }

        $storeDir = sys_get_temp_dir() . '/csv_import_results';
        $store = new ImportResultStore($storeDir);
        $result = $store->get($uuid);

        if ($result === null) {
            throw new NotFoundHttpException();
        }

        $invalidFiles = $result['perFileResults'] ?? [];
        if (!in_array($filename, $invalidFiles)) {
            throw new NotFoundHttpException();
        }

        $sourceDir = $result['sourceDir'] ?? '';
        $filePath = $sourceDir . '/' . $filename;

        if (!file_exists($filePath)) {
            throw new NotFoundHttpException();
        }

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        readfile($filePath);
        $this->isResultManaged = true;
    }

    /**
     * @see PKPImportExportPlugin::executeCLI()
     */
    public function executeCLI($scriptName, &$args): void
    {
        $startTime = microtime(true);
        $this->sendWelcomeEmail = false;

        $key = array_search('--sendWelcomeEmail', $args);
        if ($key !== false) {
            $this->sendWelcomeEmail = true;
            unset($args[$key]);
            $args = array_values($args);
        }

        $dryMode = false;
        $key = array_search('--dry-mode', $args);
        if ($key !== false) {
            $dryMode = true;
            unset($args[$key]);
            $args = array_values($args);
        }

        $this->command = array_shift($args);
        $this->username = array_shift($args);
        $this->sourceDir = array_shift($args);

        if (! in_array($this->command, ['preprints', 'users']) || !$this->sourceDir || !$this->username) {
            $this->usage($scriptName);
            exit(1);
        }

        if (! is_dir($this->sourceDir)) {
            echo __('plugins.importexport.csv.unknownSourceDir', ['sourceDir' => $this->sourceDir]) . "\n";
            exit(1);
        }

        $this->validateUser();

        $result = match ($this->command) {
            'preprints' => (new PreprintCommand($this->sourceDir, $this->user, $dryMode))->run(),
            'users' => (new UserCommand($this->sourceDir, $this->user, $this->sendWelcomeEmail, $dryMode))->run(),
            default => throw new \InvalidArgumentException(__('plugins.importexport.csv.invalidCommand', ['command' => $this->command])),
        };
        $exitCode = $result['exitCode'];

        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;
        echo __('plugins.importexport.csv.ExecutedInNSeconds', ['seconds' => number_format($executionTime, 2)]);

        exit($exitCode);
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
