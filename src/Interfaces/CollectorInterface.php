<?php

declare(strict_types=1);

namespace Falc0shka\PhpMetrics\Interfaces;

interface CollectorInterface
{

    /**
     * Process event and collect values
     *
     * @param string $eventType
     *
     * @return void
     */
    public function processEvent(string $eventType): void;

    public function getStandardMetrics(): array;

}