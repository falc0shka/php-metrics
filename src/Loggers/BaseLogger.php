<?php

declare(strict_types=1);

namespace Falc0shka\PhpMetrics\Loggers;

use Falc0shka\PhpMetrics\Interfaces\LoggerInterface;

class BaseLogger implements LoggerInterface
{

    protected string $tag = 'UNKNOWN';

    protected array $baseMetrics = [
        'requests_hit_count',
        'requests_start_count',
        'requests_finish_success_count',
        'requests_finish_exception_count',
        'logging_max_memory',
        'logging_execution_time',
        'system_cpu_usage',
        'system_load_average',
        'system_memory_usage',
        'system_memory_max',
    ];

    protected array $standardMetrics;

    protected array $customMetrics = [];

    /**
     * Timestamps
     */
    protected float $loggingStartTimestamp;

    public bool $enableSystemMetrics = false;

    public function __construct(array $standardMetrics)
    {
        $this->standardMetrics = array_keys($standardMetrics);
    }

    /**
     * @inheritDoc
     */
    public function processEvent(string $eventType, array $standardMetrics, ?array $customMetric): void {}

    public function setTag(string $tag): void
    {
        $this->tag = $tag;
    }

    public function getLogs(): void {}

    public function enableSystemMetrics(): void
    {
        $this->enableSystemMetrics = true;
    }
}