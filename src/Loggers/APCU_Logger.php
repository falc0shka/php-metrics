<?php

declare(strict_types=1);

namespace Falc0shka\PhpMetrics\Loggers;

use Exception;
use Throwable;
use ZipArchive;

class APCU_Logger extends BaseLogger
{

    protected int $currentApcuId;

    protected bool $isEven;

    public function __construct()
    {
        parent::__construct();

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
    }

    /**
     * @inheritDoc
     */
    public function processEvent(string $eventType, array $requestMetrics = null): void
    {
        if (empty($this->logPath)) {
            throw new Exception("You must set log path");
        }

        if ($this->enableAllProjectsMetrics) {
            // Handle event for all projects
            $currentProject = $this->getProject();
            $this->setProject('all');
            $this->processEventHandler($eventType, $requestMetrics);
            $this->setProject($currentProject);
        }

        // Handle event for current project
        $this->processEventHandler($eventType, $requestMetrics);
    }

    protected function processEventHandler(string $eventType, array $requestMetrics): void
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
                $this->incrementApcuRequestMetrics($requestMetrics);
                break;
            case 'ROUTE_FINISH_FAIL':
                // Increment requests_finish_fail_count metric
                $this->incrementApcuBaseMetric('requests_finish_fail_count', 1);

                // Increment standard metrics
                $this->incrementApcuRequestMetrics($requestMetrics, false);
                break;
            default:
        }
    }

    protected function saveApcuData(): void
    {
        $currentApcuId = $this->currentApcuId;

        if ($this->isEven) {
            $apcuIdKey = 'PhpMetrics_' . $this->project . '_EVEN_id';
        } else {
            $apcuIdKey = 'PhpMetrics_' . $this->project . '_ODD_id';
        }

        if (!apcu_exists($apcuIdKey)) {
            apcu_store($apcuIdKey, $currentApcuId);
            $apcuId = $currentApcuId;
        } else {
            $apcuId = apcu_fetch($apcuIdKey);
        }

        // Perform saving from APCU to file and collect system metrics (with APCU lock)
        $lockKey = 'PhpMetrics_APCU_Logger_lock_' . $this->project . '_' . $apcuId;
        if ($apcuId !== $currentApcuId && apcu_add($lockKey, true, 3600)) {
            $this->loggingStartTimestamp = microtime(true);

            // Switch APCU ID to current ID
            apcu_store($apcuIdKey, $currentApcuId);

            // Save metrics to file
            $this->saveApcuToFile($apcuId);

            $loggingTime = round(microtime(true) - $this->loggingStartTimestamp, 3);

            // Increment logging metrics
            $this->incrementApcuBaseMetric('logging_max_memory', $this->_getProcessMemoryUsage());
            $this->incrementApcuBaseMetric('logging_execution_time', $loggingTime);

            // Collect system metrics
            if ($this->enableSystemMetrics) {
                $this->collectSystemMetrics();
            }
        }
    }

    protected function saveApcuToFile(int $apcuId): void
    {
        $logPath = $this->logPath . DIRECTORY_SEPARATOR . 'metrics' . DIRECTORY_SEPARATOR . $this->project . DIRECTORY_SEPARATOR . date('Y-m-d\TH', $apcuId * 60);

        // Try to create log folder
        try {
            if (!is_dir($logPath)) {
                mkdir($logPath, 0775, true);
            }
        } catch (Throwable $e) {
            if (!is_dir($logPath)) {
                throw $e;
            }
        }

        $logFile = $logPath . DIRECTORY_SEPARATOR . 'metrics_' . date('Y-m-d\TH-i', $apcuId * 60);

        $apcuTagsKey = $this->getApcuTagsKey();
        $apcuTags = apcu_entry($apcuTagsKey, fn() => []);
        $apcuMetricsKey = $this->getApcuMetricsKey();
        $apcuMetrics = apcu_entry($apcuMetricsKey, fn() => []);

        // Save base metrics
        foreach ($this->baseMetrics as $metricKey) {
            foreach ($apcuTags as $apcuTag) {
                $apcuKey = $this->getApcuTagPrefix() . '_' . $metricKey . '_{{' . $apcuTag . '}}';
                if ($apcuValue = apcu_fetch($apcuKey)) {
                    $row = "$metricKey,$apcuTag,$apcuValue" . PHP_EOL;
                    file_put_contents($logFile, $row, LOCK_EX | FILE_APPEND);
                    apcu_delete($apcuKey);
                }
            }
        }

        // Save request metrics
        foreach ($apcuMetrics as $metricKey) {
            $successTags = ['success', 'fail'];
            foreach ($successTags as $successTag) {
                foreach ($apcuTags as $apcuTag) {
                    $apcuKey = $this->getApcuTagPrefix() . '_' . $successTag . '_' . $metricKey . '_{{' . $apcuTag . '}}';
                    if ($apcuValue = apcu_fetch($apcuKey)) {
                        $row = "{$successTag}_$metricKey,$apcuTag,$apcuValue" . PHP_EOL;
                        file_put_contents($logFile, $row, LOCK_EX | FILE_APPEND);
                        apcu_delete($apcuKey);
                    }
                }
            }
        }

        // Save tags count
        $loggingTagsCount = count($apcuTags);
        if ($loggingTagsCount > 0) {
            $row = "logging_tags_count,UNKNOWN,$loggingTagsCount" . PHP_EOL;
            file_put_contents($logFile, $row, LOCK_EX | FILE_APPEND);
        }

        apcu_delete($apcuTagsKey);
    }

    protected function getApcuTagsKey(): string
    {
        return $this->getApcuTagPrefix() . '_tags';
    }

    protected function getApcuMetricsKey(): string
    {
        return $this->getApcuTagPrefix() . '_metrics';
    }

    /**
     * Increment one metric in APCU
     *
     * @param string $metricKey
     * @param        $metricValue
     * @return void
     */
    public function incrementApcuBaseMetric(string $metricKey, $metricValue): void
    {
        // Update tags store
        $this->updateTagsStore($this->tag);

        // Increment one metric
        $apcuKey = $this->getApcuTagPrefix() . '_' . $metricKey . '_{{' . $this->tag . '}}';
        $apcuValue = apcu_entry($apcuKey, fn() => 0);
        apcu_store($apcuKey, $apcuValue + $metricValue);
    }

    /**
     * Increment all request metrics at once
     *
     * @param array $metrics
     * @param bool $success
     *
     * @return void
     */
    public function incrementApcuRequestMetrics(array $metrics, bool $success = true): void
    {
        // Update tags store
        $this->updateTagsStore($this->tag);

        // Update metrics store
        foreach (array_keys($metrics) as $metric) {
            $this->updateMetricsStore($metric);
        }

        // Increment metrics
        $successTags = ['success', 'fail'];
        foreach ($successTags as $successTag) {
            foreach ($metrics as $metricKey => $metricValue) {
                $apcuKey = $this->getApcuTagPrefix() . '_' . $successTag . '_' . $metricKey . '_{{' . $this->tag . '}}';
                $apcuValue = apcu_entry($apcuKey, fn() => 0);
                if (($success && $successTag === 'success') || (!$success && $successTag === 'fail')) {
                    apcu_store($apcuKey, $apcuValue + $metricValue);
                }
            }
        }
    }

    public function getLogs(): void
    {
        $baseDir = $this->logPath . DIRECTORY_SEPARATOR . 'metrics' . DIRECTORY_SEPARATOR . $this->project . DIRECTORY_SEPARATOR;

        if (!file_exists($baseDir)) {
            http_response_code(400);
            echo 'There is no project data...';
            exit;
        }

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
                    $logFiles = glob($logFolder . DIRECTORY_SEPARATOR . '*');
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
                    $logFiles = glob($logFolder . DIRECTORY_SEPARATOR . '*');
                    foreach ($logFiles as $logFile) {
                        unlink($logFile);
                    }
                    rmdir($logFolder);
                }
            }

            // Remove old archives (older, then 30 days)
            $logArchives = glob($baseDir . '????-??-??T??.zip') ?: [];
            foreach ($logArchives as $logArchive) {
                $modificationTime = filemtime($logArchive);
                $currentTime = time();
                if (($this->logMaxAge > 0) && $currentTime - $modificationTime > (86400 * $this->logMaxAge)) {
                    unlink($logArchive);
                }
            }

            // Prepare archive list
            $logArchives = glob($baseDir . '????-??-??T??.zip') ?: [];
            $logArchives = array_map(fn($logArchive) => substr($logArchive, -17, 13), $logArchives);

            // Output
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

    protected function _getCpuUsage(): float
    {
        $data = shell_exec("top -bn1 | grep 'Cpu(s)'");

        if (!$data) {
            return 0;
        }

        preg_match_all('~([0-9.]+)\s*id~im', $data, $matchArr);

        $idle = floatVal($matchArr[1][0]);

        return 100 - $idle;
    }

    protected function _getProcessMemoryUsage(): float
    {
        $data = getrusage();
        return isset($data['ru_maxrss']) ? round($data['ru_maxrss'] / 1024, 2) : 0;
    }

    protected function _getSystemMemoryUsage(): float
    {
        $data = file_get_contents('/proc/meminfo');

        if (!$data) {
            return 0;
        }

        $matchArr = [];
        preg_match_all('~^(MemTotal|MemFree):\s*([0-9]+).*$~im', $data, $matchArr);

        if (!isset($matchArr[2][0]) || !isset($matchArr[2][1])) {
            return 0;
        }
        return round((intval($matchArr[2][0]) - intval($matchArr[2][1])) / 1024, 2);
    }

    protected function _getSystemMemoryMax(): float
    {
        $data = file_get_contents('/proc/meminfo');

        if (!$data) {
            return 0;
        }

        $matchArr = [];
        preg_match_all('~^MemTotal:\s*([0-9]+).*$~im', $data, $matchArr);

        return isset($matchArr[1][0]) ? round(intval($matchArr[1][0]) / 1024, 2) : 0;
    }

    protected function _getLoadAverage(): float
    {
        $data = file_get_contents('/proc/loadavg');

        if (!$data) {
            return 0;
        }

        return floatval(preg_split('/\s+/', trim($data))[0]);
    }


    protected function _getSystemDiskFreeSpace(): float
    {
        $data = disk_free_space('.');

        return $data ? round(intval($data) / 1024, 2) : 0;
    }

    protected function _getSystemDiskTotalSpace(): float
    {
        $data = disk_total_space('.');

        return $data ? round(intval($data) / 1024, 2) : 0;
    }

    /**
     * @return void
     */
    public function collectSystemMetrics(): void
    {
        // Collect system cpu usage
        $this->incrementApcuBaseMetric('system_cpu_usage', $this->_getCpuUsage());
        // Collect system load average
        $this->incrementApcuBaseMetric('system_load_average', $this->_getLoadAverage());
        // Collect system memory usage
        $this->incrementApcuBaseMetric('system_memory_usage', $this->_getSystemMemoryUsage());
        // Collect system memory installed
        $this->incrementApcuBaseMetric('system_memory_max', $this->_getSystemMemoryMax());
        // Collect system free disk space
        $this->incrementApcuBaseMetric('system_disk_free_space', $this->_getSystemDiskFreeSpace());
        // Collect system total disk space
        $this->incrementApcuBaseMetric('system_disk_total_space', $this->_getSystemDiskTotalSpace());
    }

    public function getApcuTagPrefix(): string
    {
        return 'PhpMetrics_' . $this->project . '_' . ($this->isEven ? 'EVEN' : 'ODD');
    }

    public function updateTagsStore(string $tag): void
    {
        $apcuTagsKey = $this->getApcuTagsKey();
        $apcuTags = apcu_entry($apcuTagsKey, fn() => []);
        if (!in_array($tag, $apcuTags)) {
            $apcuTags[] = $tag;
            apcu_store($apcuTagsKey, $apcuTags);
        }
    }

    public function updateMetricsStore($metric): void
    {
        $apcuMetricsKey = $this->getApcuMetricsKey();
        $apcuMetrics = apcu_entry($apcuMetricsKey, fn() => []);
        if (!in_array($metric, $apcuMetrics)) {
            $apcuMetrics[] = $metric;
            apcu_store($apcuMetricsKey, $apcuMetrics);
        }
    }
}
