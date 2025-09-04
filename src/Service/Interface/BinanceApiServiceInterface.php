<?php

declare(strict_types=1);

namespace App\Service\Interface;

use App\Enum\CurrencyPair;

interface BinanceApiServiceInterface
{
    /**
     * Get exchange rates for all supported currency pairs
     *
     * @return array<string, float>
     * @throws \Exception
     */
    public function getExchangeRates(): array;

    /**
     * Get a single pair rate for testing/debugging
     *
     * @throws \Exception
     */
    public function getSingleRate(CurrencyPair $pair): float;

    /**
     * Health check method to verify API connectivity
     */
    public function healthCheck(): bool;

    /**
     * Get API rate limit information
     *
     * @return array<string, mixed>|null
     */
    public function getRateLimitInfo(): ?array;
}
