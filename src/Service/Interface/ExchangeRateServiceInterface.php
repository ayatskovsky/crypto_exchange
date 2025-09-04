<?php

declare(strict_types=1);

namespace App\Service\Interface;

use App\Enum\CurrencyPair;

interface ExchangeRateServiceInterface
{
    /**
     * Update exchange rates from external API
     *
     * @throws \Exception
     */
    public function updateRates(): void;

    /**
     * Get exchange rates for the last 24 hours
     *
     * @return array<string, mixed>
     * @throws \InvalidArgumentException
     */
    public function getLast24Hours(string $pairString): array;

    /**
     * Get exchange rates for a specific date
     *
     * @return array<string, mixed>
     * @throws \InvalidArgumentException
     */
    public function getRatesByDate(string $pairString, string $date): array;

    /**
     * Clean up old exchange rate records
     *
     * @return int Number of deleted records
     */
    public function cleanupOldData(): int;

    /**
     * Get current rate for a specific pair
     */
    public function getCurrentRate(CurrencyPair $pair): ?float;
}
