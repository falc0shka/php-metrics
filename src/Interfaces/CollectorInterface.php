<?php

declare(strict_types=1);

namespace Falc0shka\PhpMetrics\Interfaces;

interface CollectorInterface
{

    /**
     * Process event and collect values
     *
     * @param string $eventType
     * @param array|null $eventParams
     * @return void
     */
    public function processEvent(string $eventType, array $eventParams = null): array;

    public function getRequestMetrics(): array;

    public function getCurrentTimestamp(): float;
}