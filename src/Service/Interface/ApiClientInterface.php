<?php

declare(strict_types=1);

namespace App\Service\Interface;

interface ApiClientInterface
{
    /**
     * @return array<string, float>
     * @throws \Exception
     */
    public function fetchPrices(array $symbols): array;

    public function healthCheck(): bool;

    public function getRateLimitInfo(): ?array;
}
