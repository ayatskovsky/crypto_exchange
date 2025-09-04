<?php

declare(strict_types=1);

namespace App\Repository\Interface;

use App\Entity\ExchangeRate;
use App\Enum\CurrencyPair;

interface ExchangeRateRepositoryInterface
{
    /**
     * @return ExchangeRate[]
     */
    public function findLast24Hours(CurrencyPair $pair): array;

    /**
     * @return ExchangeRate[]
     */
    public function findByDate(CurrencyPair $pair, \DateTimeInterface $date): array;

    public function save(ExchangeRate $entity, bool $flush = false): void;

    /**
     * Clean up old records (older than 30 days)
     *
     * @return int number of records deleted
     */
    public function cleanupOldRecords(): int;
}
