<?php

declare(strict_types=1);

namespace Falc0shka\PhpMetrics\Collectors;

use Falc0shka\PhpMetrics\Interfaces\CollectorInterface;

class Collector implements CollectorInterface
{

    protected array $standardMetrics = [
        'max_memory' => 0,
        'execution_time' => 0,
        'db_requests' => 0,
        'db_requests_time' => 0,
        'api_requests' => 0,
        'api_requests_time' => 0,
    ];

    /**
     * Timestamps
     */
    protected float $processStartTimestamp;

    protected float $dbRequestTimestamp;

    protected float $apiRequestTimestamp;

    public function __construct()
    {
    }

    /**
     * @inheritDoc
     */
    public function processEvent(string $eventType): void
    {
        // Collect memory usage
        $this->standardMetrics['max_memory'] = max($this->standardMetrics['max_memory'], $this->_getProcessMemoryUsage());

        switch ($eventType) {
            case 'PROCESS_START':
                $this->processStartTimestamp = microtime(true);
                break;
            case 'ROUTE_START':
                break;
            case 'ROUTE_FINISH_EXCEPTION':
            case 'ROUTE_FINISH_SUCCESS':
                $this->standardMetrics['execution_time'] = round(microtime(true) - $this->processStartTimestamp, 3);
                break;
            case 'DB_REQUEST':
                $this->standardMetrics['db_requests']++;
                $this->dbRequestTimestamp = microtime(true);
                break;
            case 'DB_RESPONSE':
                $this->standardMetrics['db_requests_time'] += round(microtime(true) - $this->dbRequestTimestamp, 3);
                break;
            case 'API_REQUEST':
                $this->standardMetrics['api_requests']++;
                $this->apiRequestTimestamp = microtime(true);
                break;
            case 'API_RESPONSE':
                $this->standardMetrics['api_requests_time'] += round(microtime(true) - $this->apiRequestTimestamp, 3);
                break;
            default:
        }
    }

    public function getStandardMetrics(): array
    {
        return $this->standardMetrics;
    }

    protected function _getProcessMemoryUsage(): float
    {
        $data = getrusage();
        return isset($data['ru_maxrss']) ? round($data['ru_maxrss'] / 1024, 2) : 0;
    }
}