<?php

declare(strict_types=1);

namespace Falc0shka\PhpMetrics\Loggers;

use ZipArchive;

class APCU_Logger extends BaseLogger
{

    protected int $currentApcuId;

    protected bool $isEven;

    protected string $logPath;

    public function __construct(array $standardMetrics)
    {
        parent::__construct($standardMetrics);

        $this->initApcuLogger();
    }

    /**
     * Init APCU logger
     *
     * @return void
     */
    public function initApcuLogger(): void
    {
        // Set APCU id for current request
        $this->currentApcuId = (int)floor(time() / 60);

        // Set current APCU id type (EVEN/ODD)
        $this->isEven = $this->currentApcuId % 2 === 0;

        // Set default logPath
        $this->logPath = dirname(__FILE__) . '/log';
    }

    /**
     * @inheritDoc
     */
    public function processEvent(string $eventType, array $standardMetrics, ?array $customMetric): void
    {
        switch ($eventType) {
            case 'PROCESS_START':
                // Save previous APCU data
                $this->saveApcuData();

                // Increment requests_hit_count metric
                $this->incrementApcuBaseMetric('requests_hit_count', 1);
                break;
            case 'ROUTE_START':
                // Increment requests_start_count metric
                $this->incrementApcuBaseMetric('requests_start_count', 1);
                break;
            case 'ROUTE_FINISH_SUCCESS':
                // Increment requests_finish_success_count metric
                $this->incrementApcuBaseMetric('requests_finish_success_count', 1);

                // Increment standard metrics
                $this->incrementApcuStandardMetrics($standardMetrics);
                break;
            case 'ROUTE_FINISH_EXCEPTION':
                // Increment requests_finish_exception_count metric
                $this->incrementApcuBaseMetric('requests_finish_exception_count', 1);

                // Increment standard metrics
                $this->incrementApcuStandardMetrics($standardMetrics, false);
                break;
            case 'CUSTOM_METRIC':
                // Increment custom metric
                if ($customMetric) {
                    $this->incrementApcuCustomMetric($customMetric);
                }
                break;
            case 'DB_REQUEST':
            case 'DB_RESPONSE':
            case 'API_REQUEST':
            case 'API_RESPONSE':
            default:
        }
    }

    public function saveApcuData(): void
    {
        $currentApcuId = $this->currentApcuId;

        if ($this->isEven) {
            if (!apcu_exists('PhpMetrics_EVEN_id')) {
                apcu_store('PhpMetrics_EVEN_id', $currentApcuId);
                $apcuEvenId = $currentApcuId;
            } else {
                $apcuEvenId = apcu_fetch('PhpMetrics_EVEN_id');
            }

            // Perform saving from APCU even metrics to file
            if ($apcuEvenId !== $currentApcuId) {

                $this->loggingStartTimestamp = microtime(true);

                apcu_store('PhpMetrics_EVEN_id', $currentApcuId);
                $this->saveApcuToFile($apcuEvenId);

                $loggingTime = round(microtime(true) - $this->loggingStartTimestamp, 3);

                // Increment logging metrics
                $this->incrementApcuBaseMetric('logging_max_memory', $this->_getProcessMemoryUsage());
                $this->incrementApcuBaseMetric('logging_execution_time', $loggingTime);
            }
        } else {
            if (!apcu_exists('PhpMetrics_ODD_id')) {
                apcu_store('PhpMetrics_ODD_id', $currentApcuId);
                $apcuOddId = $currentApcuId;
            } else {
                $apcuOddId = apcu_fetch('PhpMetrics_ODD_id');
            }

            // Perform saving from APCU odd metrics to file
            if ($apcuOddId !== $currentApcuId) {

                $this->loggingStartTimestamp = microtime(true);

                apcu_store('PhpMetrics_ODD_id', $currentApcuId);
                $this->saveApcuToFile($apcuOddId);

                $loggingTime = round(microtime(true) - $this->loggingStartTimestamp, 3);

                // Increment logging metrics
                $this->incrementApcuBaseMetric('logging_max_memory', $this->_getProcessMemoryUsage());
                $this->incrementApcuBaseMetric('logging_execution_time', $loggingTime);
            }
        }
    }

    protected function saveApcuToFile(int $apcuId): void
    {
        $logPath = $this->logPath . '/metrics/' . date('Y-m-d\TH', $apcuId * 60);
        if (!file_exists($logPath)) {
            mkdir($logPath, 0777, true);
        }
        $logFile = $logPath . '/metrics_' . date('Y-m-d\TH-i', $apcuId * 60);

        $apcuTagsKey = $this->getApcuTagsKey();
        $apcuTags = apcu_entry($apcuTagsKey, fn() => []);

        // Save base metrics
        foreach ($this->baseMetrics as $metricKey) {
            foreach ($apcuTags as $apcuTag) {
                $apcuKey = 'PhpMetrics_' . ($this->isEven ? 'EVEN' : 'ODD') . '_' . $metricKey . '_{{' . $apcuTag . '}}';
                if ($apcuValue = apcu_fetch($apcuKey)) {
                    $row = "$metricKey,$apcuTag,$apcuValue\n";
                    file_put_contents($logFile, $row, LOCK_EX | FILE_APPEND);
                    apcu_delete($apcuKey);
                }
            }
        }

        // Save standard metrics
        foreach ($this->standardMetrics as $metricKey) {
            $successTags = ['success', 'exception'];
            foreach ($successTags as $successTag) {
                foreach ($apcuTags as $apcuTag) {
                    $apcuKey = 'PhpMetrics_' . ($this->isEven ? 'EVEN' : 'ODD') . '_' . $successTag . '_' . $metricKey . '_{{' . $apcuTag . '}}';
                    if ($apcuValue = apcu_fetch($apcuKey)) {
                        $row = "{$successTag}_$metricKey,$apcuTag,$apcuValue\n";
                        file_put_contents($logFile, $row, LOCK_EX | FILE_APPEND);
                        apcu_delete($apcuKey);
                    }
                }
            }
        }

        // Save custom metrics
        $apcuCustomTagsKey = $this->getApcuCustomTagsKey();
        $apcuCustomMetricsKey = $this->getApcuCustomMetricsKey();
        $apcuCustomTags = apcu_entry($apcuCustomTagsKey, fn() => []);
        $apcuCustomMetrics = apcu_entry($apcuCustomMetricsKey, fn() => []);

        foreach ($apcuCustomTags as $apcuCustomTag) {
            foreach ($apcuCustomMetrics as $apcuCustomMetric) {
                $apcuKey = 'PhpMetrics_' . ($this->isEven ? 'EVEN' : 'ODD') . '_' . $apcuCustomMetric . '_{{' . $apcuCustomTag . '}}';
                if ($apcuValue = apcu_fetch($apcuKey)) {
                    $row = "$apcuCustomMetric,$apcuCustomTag,$apcuValue\n";
                    file_put_contents($logFile, $row, LOCK_EX | FILE_APPEND);
                }
                apcu_delete($apcuKey);
            }
        }

        // Save tags count
        $loggingTagsCount = count($apcuTags) + count($apcuCustomTags);
        if ($loggingTagsCount > 0) {
            $row = "logging_tags_count,UNKNOWN,$loggingTagsCount\n";
            file_put_contents($logFile, $row, LOCK_EX | FILE_APPEND);
        }

        apcu_delete($apcuTagsKey);
        apcu_delete($apcuCustomTagsKey);
        apcu_delete($apcuCustomMetricsKey);
    }

    protected function getApcuTagsKey(): string
    {
        return 'PhpMetrics_' . ($this->isEven ? 'EVEN' : 'ODD') . '_tags';
    }

    protected function getApcuCustomTagsKey(): string
    {
        return 'PhpMetrics_' . ($this->isEven ? 'EVEN' : 'ODD') . '_customTags';
    }

    protected function getApcuCustomMetricsKey(): string
    {
        return 'PhpMetrics_' . ($this->isEven ? 'EVEN' : 'ODD') . '_customMetrics';
    }

    /**
     * Increment one metric in APCU
     *
     * @param string $metricKey
     * @param        $metricValue
     * @param string $tag
     *
     * @return void
     */
    public function incrementApcuBaseMetric(string $metricKey, $metricValue): void
    {
        // Update tags store
        $apcuTagsKey = $this->getApcuTagsKey();
        $apcuTags = apcu_entry($apcuTagsKey, fn() => []);
        if (!in_array($this->tag, $apcuTags)) {
            $apcuTags[] = $this->tag;
            apcu_store($apcuTagsKey, $apcuTags);
        }

        // Increment one metric
        $apcuKey = 'PhpMetrics_' . ($this->isEven ? 'EVEN' : 'ODD') . '_' . $metricKey . '_{{' . $this->tag . '}}';
        $apcuValue = apcu_entry($apcuKey, fn() => 0);
        apcu_store($apcuKey, $apcuValue + $metricValue);
    }

    /**
     * Increment all standard metrics at once
     *
     * @param array $metrics
     * @param string $tag
     * @param bool $success
     *
     * @return void
     */
    public function incrementApcuStandardMetrics(array $metrics, bool $success = true): void
    {
        // Update tags store
        $apcuTagsKey = $this->getApcuTagsKey();
        $apcuTags = apcu_entry($apcuTagsKey, fn() => []);
        if (!in_array($this->tag, $apcuTags)) {
            $apcuTags[] = $this->tag;
            apcu_store($apcuTagsKey, $apcuTags);
        }

        // Increment metrics
        $successTags = ['success', 'exception'];
        foreach ($successTags as $successTag) {
            foreach ($metrics as $metricKey => $metricValue) {
                $apcuKey = 'PhpMetrics_' . ($this->isEven ? 'EVEN' : 'ODD') . '_' . $successTag . '_' . $metricKey . '_{{' . $this->tag . '}}';
                $apcuValue = apcu_entry($apcuKey, fn() => 0);
                if (($success && $successTag === 'success') || (!$success && $successTag === 'exception')) {
                    apcu_store($apcuKey, $apcuValue + $metricValue);
                }
            }
        }
    }

    /**
     * Increment optional metric
     *
     * @param array $metrics
     * @param string $tag
     * @param bool $success
     *
     * @return void
     */
    public function incrementApcuCustomMetric(array $customMetric): void
    {
        // Update custom tags store
        $apcuCustomTagsKey = $this->getApcuCustomTagsKey();
        $apcuCustomTags = apcu_entry($apcuCustomTagsKey, fn() => []);
        if (!in_array($customMetric['tag'], $apcuCustomTags)) {
            $apcuCustomTags[] = $customMetric['tag'];
            apcu_store($apcuCustomTagsKey, $apcuCustomTags);
        }

        // Update custom metrics store
        $apcuCustomMetricsKey = $this->getApcuCustomMetricsKey();
        $apcuCustomMetrics = apcu_entry($apcuCustomMetricsKey, fn() => []);
        if (!in_array($customMetric['metric'], $apcuCustomMetrics)) {
            $apcuCustomMetrics[] = $customMetric['metric'];
            apcu_store($apcuCustomMetricsKey, $apcuCustomMetrics);
        }

        // Increment metrics
        $apcuKey = 'PhpMetrics_' . ($this->isEven ? 'EVEN' : 'ODD') . '_' . $customMetric['metric'] . '_{{' . $customMetric['tag'] . '}}';
        $apcuValue = apcu_entry($apcuKey, fn() => 0);
        apcu_store($apcuKey, $apcuValue + $customMetric['value']);
    }

    public function setLogPath(string $logPath): void
    {
        $this->logPath = $logPath;
    }

    public function getLogPath(): string
    {
        return $this->logPath;
    }

    public function getLogs(): void
    {
        $baseDir = $this->logPath . '/metrics/';

        if (file_exists($baseDir . '.processing')) {
            http_response_code(400);
            echo 'Process is still running...';
            exit;
        }

        // Set processing status
        touch($baseDir . '.processing');

        // Check GET-parameter
        if (empty($_GET['file'])) {
            $currentFolder = date('Y-m-d\TH', time());

            $logFolders = glob($baseDir . '????-??-??T??');

            // Archive all log folders, except current
            foreach ($logFolders as $logFolder) {
                if (substr($logFolder, -13) !== $currentFolder && !file_exists($logFolder . '.zip')) {
                    // Create archive
                    $archiveFile = new ZipArchive();
                    $archiveFile->open($logFolder . '.zip', ZipArchive::CREATE | ZipArchive::OVERWRITE);

                    // Add log files to archive
                    $logFiles = glob($logFolder . '/*');
                    foreach ($logFiles as $logFile) {
                        $archiveFile->addFile($logFile, basename($logFile));
                    }
                    $archiveFile->close();

                    // Remove all log files and folders
                    foreach ($logFiles as $logFile) {
                        unlink($logFile);
                    }
                    rmdir($logFolder);
                } elseif (file_exists($logFolder . '.zip')) {
                    // If archive is already exist, but folder hadn't been deleted, remove it
                    $logFiles = glob($logFolder . '/*');
                    foreach ($logFiles as $logFile) {
                        unlink($logFile);
                    }
                    rmdir($logFolder);
                }
            }

            $logArchives = glob($baseDir . '????-??-??T??.zip') ?: [];
            $logArchives = array_map(fn($logArchive) => substr($logArchive, -17, 13), $logArchives);
            header('Content-Type: application/json');
            echo json_encode($logArchives);
        } else {
            $fileName = $_GET['file'] . '.zip';
            $archivePath = $baseDir . $fileName;

            // Check if archive exist and output it
            if (file_exists($archivePath)) {
                header('Content-Type: application/zip');
                header('Content-Disposition: attachment; filename="' . $fileName . '"');
                readfile($archivePath);
            } else {
                http_response_code(404);
                echo 'File not found.';
            }
        }

        // Clear processing status
        unlink($baseDir . '.processing');
    }

    /**
     * Returns memory usage from /proc<PID>/status in MB.
     *
     * @return float|int sum of VmRSS and VmSwap in MB. On error returns false.
     */
    protected function _getProcessMemoryUsage(): float
    {
        $status = file_get_contents('/proc/' . getmypid() . '/status');

        if (!$status) {
            return 0;
        }

        $matchArr = [];
        preg_match_all('~^(VmRSS|VmSwap):\s*([0-9]+).*$~im', $status, $matchArr);

        if (!isset($matchArr[2][0]) || !isset($matchArr[2][1])) {
            return 0;
        }
        return round((intval($matchArr[2][0]) + intval($matchArr[2][1])) / 1024, 2);
    }
}