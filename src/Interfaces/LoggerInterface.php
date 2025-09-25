<?php

declare(strict_types=1);

namespace Falc0shka\PhpMetrics\Interfaces;

interface LoggerInterface
{

    /**
     * Process event and perform logging
     *
     * @param string $eventType
     * @param array $standardMetrics
     * @param array|null $customMetric
     * @return void
     */
    public function processEvent(string $eventType, array $standardMetrics, ?array $customMetric): void;

    public function getLogs(): void;

    public function disableSystemMetrics(): void;

}