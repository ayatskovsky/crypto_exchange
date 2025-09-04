<?php

declare(strict_types=1);

namespace App\Service\Interface;

interface RetryHandlerInterface
{
    public function execute(callable $operation, int $maxRetries = 3, int $delayMs = 1000): mixed;
}
