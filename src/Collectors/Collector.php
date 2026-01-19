<?php

declare(strict_types=1);

namespace Falc0shka\PhpMetrics\Collectors;

use Falc0shka\PhpMetrics\Interfaces\CollectorInterface;

class Collector implements CollectorInterface
{

    protected array $requestMetrics = [
        'max_memory' => 0,
        'execution_time' => 0,
//        'db_requests' => 0,
//        'db_requests_time' => 0,
//        'db_requests_time_max' => 0,
//        'api_requests' => 0,
//        'api_requests_time' => 0,
//        'api_requests_time_max' => 0,
//        'validation_errors' => 0,
    ];

    /**
     * Timestamps
     */
    protected float $processStartTimestamp;

    public function __construct()
    {
        $this->processStartTimestamp = $this->getCurrentTimestamp();
    }

    /**
     * @inheritDoc
     */
    public function processEvent(string $eventType, array $eventParams = null): array
    {
        switch ($eventType) {
            case 'PROCESS_START':
            case 'ROUTE_START':
                break;
            case 'ROUTE_FINISH_SUCCESS':
            case 'ROUTE_FINISH_FAIL':
                $this->requestMetrics['execution_time'] = round($this->getCurrentTimestamp() - $this->processStartTimestamp, 3);
                break;
            case 'UPDATE_METRIC':
                $metricBaseName = strtolower($eventParams['metric']);

                $metricName = $metricBaseName;
                $this->requestMetrics[$metricName] = $this->requestMetrics[$metricName] ?? 0;
                $this->requestMetrics[$metricName]++;

                if (!empty($eventParams['value'])) {
                    $metricName = $metricBaseName . '_value';
                    $this->requestMetrics[$metricName] = $this->requestMetrics[$metricName] ?? 0;
                    $this->requestMetrics[$metricName] += $eventParams['value'];
                }

                if (!empty($eventParams['execution_time']) || !empty($eventParams['time_start'])) {
                    $metricTimeName = "{$metricBaseName}_time";
                    $metricTimeMaxName = "{$metricBaseName}_time_max";

                    $this->requestMetrics[$metricTimeName] = $this->requestMetrics[$metricTimeName] ?? 0;

                    if (!empty($eventParams['execution_time'])) {
                        $executionTime = $eventParams['execution_time'];
                    } else {
                        $executionTime = round(max($this->getCurrentTimestamp() - $eventParams['time_start'], 0), 3);
                    }

                    $this->requestMetrics[$metricTimeName] += $executionTime;

                    $this->requestMetrics[$metricTimeMaxName] = $this->requestMetrics[$metricTimeMaxName] ?? 0;
                    $this->requestMetrics[$metricTimeMaxName] = max($executionTime, $this->requestMetrics[$metricTimeMaxName]);
                }
                break;
            default:
        }

        // Collect memory usage
        $this->requestMetrics['max_memory'] = max($this->requestMetrics['max_memory'], $this->_getProcessMemoryUsage());

        return $this->requestMetrics;
    }

    public function getRequestMetrics(): array
    {
        return $this->requestMetrics;
    }

    protected function _getProcessMemoryUsage(): float
    {
        $data = getrusage();
        return isset($data['ru_maxrss']) ? round(intval($data['ru_maxrss']) / 1024, 2) : 0;
    }

    public function getCurrentTimestamp(): float
    {
        return microtime(true);
    }
}