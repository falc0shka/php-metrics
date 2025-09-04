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

    private string $collectorType = 'B2B';
    
    private string $loggerType = 'APCU';
    
    private array $allowedEvents = [
        'PROCESS_START',
        'ROUTE_START',
        'ROUTE_FINISH_SUCCESS',
        'ROUTE_FINISH_EXCEPTION',
        'DB_REQUEST',
        'DB_RESPONSE',
        'API_REQUEST',
        'API_RESPONSE',
        'CUSTOM_METRIC',
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
        $this->collector = new Collector();
        $this->logger = new APCU_Logger($this->collector->getStandardMetrics());
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

    public function dispatchEvent(string $eventType, ?array $customMetric = null): PhpMetrics
    {
        if (!in_array($eventType, $this->allowedEvents)) {
            throw new Exception("Invalid event type");
        }
        
        if ($this->enableMetrics) {
            $this->collector->processEvent($eventType);
            $standardMetrics = $this->collector->getStandardMetrics();
            $this->logger->processEvent($eventType, $standardMetrics, $customMetric);
        }

        return self::$instance;
    }

    public function setLogPath(string $path): PhpMetrics
    {
        $this->logger->setLogPath($path);

        return self::$instance;
    }

    public function setTag(string $tag): PhpMetrics
    {
        $this->logger->setTag($tag);

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


    public function getLogs() {
        $this->logger->getLogs();
    }
    
}