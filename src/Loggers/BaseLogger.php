<?php

declare(strict_types=1);

namespace Falc0shka\PhpMetrics\Loggers;

use Falc0shka\PhpMetrics\Interfaces\LoggerInterface;

class BaseLogger implements LoggerInterface
{

    protected string $project = 'main';

    protected string $tag = 'UNKNOWN';

    protected array $baseMetrics = [
        'requests_hit_count',
        'requests_start_count',
        'requests_finish_success_count',
        'requests_finish_fail_count',
        'logging_max_memory',
        'logging_execution_time',
        'system_cpu_usage',
        'system_load_average',
        'system_memory_usage',
        'system_memory_max',
        'system_disk_free_space',
        'system_disk_total_space',
    ];

    protected string $logPath;

    protected int $logMaxAge = 0; // Max log files age in days (0 - unlimited)

    /**
     * Timestamps
     */
    protected float $loggingStartTimestamp;

    protected bool $enableSystemMetrics = false;

    protected bool $enableAllProjectsMetrics = false;

    public function __construct()
    {
    }

    /**
     * @inheritDoc
     */
    public function processEvent(string $eventType, array $requestMetrics = null): void {}

    public function setTag(string $tag): void
    {
        $this->tag = $tag;
    }

    public function getLogs(): void {}

    public function enableSystemMetrics(): void
    {
        $this->enableSystemMetrics = true;
    }

    public function enableAllProjectsMetrics(): void
    {
        $this->enableAllProjectsMetrics = true;
    }

    public function setLogPath(string $logPath): void
    {
        $this->logPath = rtrim($logPath, '/\\');
    }

    public function getLogPath(): string
    {
        return $this->logPath;
    }

    public function setLogMaxAge(int $logMaxAge): void
    {
        $this->logMaxAge = $logMaxAge;
    }

    public function setProject(string $project): void
    {
        if ($project) {
            $this->project = $this->formatProjectName($project);
        }
    }

    public function getProject(): string
    {
        return $this->project;
    }

    protected function formatProjectName(string $project): string
    {
        $project = trim($project);
        $project = preg_replace('/[^a-zA-Z0-9_]/u', ' ', $project);
        $project = strtolower($project);
        $project = preg_replace('/\s+/', ' ', $project);
        $project = str_replace(' ', '_', $project);
        $project = mb_substr($project, 0, 20);
        return $project;
    }
}
