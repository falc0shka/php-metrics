<?php

declare(strict_types=1);

namespace Falc0shka\PhpMetrics;

use Exception;
use Falc0shka\PhpMetrics\Interfaces\CollectorInterface;
use Falc0shka\PhpMetrics\Interfaces\LoggerInterface;
use Falc0shka\PhpMetrics\Collectors\Collector;
use Falc0shka\PhpMetrics\Loggers\APCU_Logger;

final class PhpMetrics
{

    private static ?PhpMetrics $instance = null;

    private LoggerInterface $logger;

    private CollectorInterface $collector;

    private bool $enableMetrics = false;

    private string $collectorClass = Collector::class;

    private string $loggerClass = APCU_Logger::class;

    private array $allowedEvents = [
        'PROCESS_START',
        'ROUTE_START',
        'ROUTE_FINISH_SUCCESS',
        'ROUTE_FINISH_FAIL',
        'UPDATE_METRIC',
    ];

    /**
     * gets the instance via lazy initialization (created on first usage)
     */
    public static function getInstance(): PhpMetrics
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * is not allowed to call from outside to prevent from creating multiple instances, to use the singleton,
     * you have to obtain the instance from Singleton::getInstance() instead
     */
    private function __construct()
    {
        // TODO add collector and logger settings
        $this->collector = new $this->collectorClass();
        $this->logger = new $this->loggerClass($this->collector->getRequestMetrics());
    }

    /**
     * prevent the instance from being cloned (which would create a second instance of it)
     */
    private function __clone() {}

    /**
     * prevent from being unserialized (which would create a second instance of it)
     */
    public function __wakeup()
    {
        throw new Exception("Cannot unserialize singleton");
    }

    public function dispatchEvent(string $eventType, array $eventParams = []): PhpMetrics
    {
        if (!in_array($eventType, $this->allowedEvents)) {
            //throw new Exception("Invalid event type");
            return self::$instance;
        }

        if ($this->enableMetrics) {
            $requestMetrics = $this->collector->processEvent($eventType, $eventParams);
            $this->logger->processEvent($eventType, $requestMetrics);
        }

        return self::$instance;
    }

    public function enableMetrics(): PhpMetrics
    {
        $this->enableMetrics = true;

        return self::$instance;
    }

    public function disableMetrics(): PhpMetrics
    {
        $this->enableMetrics = false;

        return self::$instance;
    }

    public function enableSystemMetrics(): PhpMetrics
    {
        $this->logger->enableSystemMetrics();

        return self::$instance;
    }

    public function setProject(string $project): PhpMetrics
    {
        $this->logger->setProject($project);

        return self::$instance;
    }

    public function setLogPath(string $path): PhpMetrics
    {
        $this->logger->setLogPath($path);

        return self::$instance;
    }

    public function setLogMaxAge(int $logMaxAge): PhpMetrics
    {
        $this->logger->setLogMaxAge($logMaxAge);

        return self::$instance;
    }

    public function setTag(string $tag): PhpMetrics
    {
        $this->logger->setTag($tag);

        return self::$instance;
    }

    public function enableAllProjectsMetrics(): PhpMetrics
    {
        $this->logger->enableAllProjectsMetrics();

        return self::$instance;
    }

    public function getLogs() {
        $this->logger->getLogs();
    }

    public function getCurrentTimestamp() {
        $this->collector->getCurrentTimestamp();
    }

    public function processStart(): PhpMetrics
    {
        return $this->dispatchEvent('PROCESS_START');
    }

    public function routeStart(): PhpMetrics
    {
        return $this->dispatchEvent('ROUTE_START');
    }

    public function routeFinishSuccess(): PhpMetrics
    {
        return $this->dispatchEvent('ROUTE_FINISH_SUCCESS');
    }

    public function routeFinishFail(): PhpMetrics
    {
        return $this->dispatchEvent('ROUTE_FINISH_FAIL');
    }

    public function updateMetric(string $metric, array $metricParams = []): PhpMetrics
    {
        $metricParams['metric'] = $metric;
        return $this->dispatchEvent('UPDATE_METRIC', $metricParams);
    }
}