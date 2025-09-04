<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\ExchangeRate;
use App\Enum\CurrencyPair;
use App\Repository\Interface\ExchangeRateRepositoryInterface;
use App\Service\Interface\ExchangeRateServiceInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

final readonly class ExchangeRateService implements ExchangeRateServiceInterface
{
    public function __construct(
        private BinanceApiService               $binanceApiService,
        private ExchangeRateRepositoryInterface $exchangeRateRepository,
        private EntityManagerInterface          $entityManager,
        private LoggerInterface                 $logger
    )
    {
    }

    public function updateRates(): void
    {
        try {
            $rates = $this->binanceApiService->getExchangeRates();
            $savedCount = 0;

            foreach ($rates as $pairString => $rate) {
                $pair = CurrencyPair::fromString($pairString);

                if ($pair === null) {
                    $this->logger->warning('Invalid currency pair received', ['pair' => $pairString]);
                    continue;
                }

                $exchangeRate = new ExchangeRate();
                $exchangeRate->setPair($pair);
                $exchangeRate->setRate((string)$rate);

                $this->exchangeRateRepository->save($exchangeRate);
                $savedCount++;
            }

            $this->entityManager->flush();

            $this->logger->info('Exchange rates updated successfully', [
                'pairs_updated' => $savedCount,
                'total_pairs' => count($rates)
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to update exchange rates', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function getLast24Hours(string $pairString): array
    {
        $pair = $this->validateAndGetPair($pairString);

        $rates = $this->exchangeRateRepository->findLast24Hours($pair);

        return $this->formatRatesResponse($rates);
    }

    /**
     * @return array<string, mixed>
     */
    public function getRatesByDate(string $pairString, string $date): array
    {
        $pair = $this->validateAndGetPair($pairString);

        try {
            $dateObj = new \DateTimeImmutable($date);
        } catch (\Exception $e) {
            throw new \InvalidArgumentException('Invalid date format. Use YYYY-MM-DD');
        }

        $rates = $this->exchangeRateRepository->findByDate($pair, $dateObj);

        return $this->formatRatesResponse($rates);
    }

    public function cleanupOldData(): int
    {
        $deletedCount = $this->exchangeRateRepository->cleanupOldRecords();

        $this->logger->info('Cleaned up old exchange rate records', [
            'deleted_count' => $deletedCount
        ]);

        return $deletedCount;
    }

    /**
     * Get current rate for a pair
     */
    public function getCurrentRate(CurrencyPair $pair): ?float
    {
        try {
            return $this->binanceApiService->getSingleRate($pair);
        } catch (\Exception $e) {
            $this->logger->error('Failed to get current rate', [
                'pair' => $pair->value,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    private function validateAndGetPair(string $pairString): CurrencyPair
    {
        $pair = CurrencyPair::fromString($pairString);

        if ($pair === null) {
            $validPairs = implode(', ', CurrencyPair::values());
            throw new \InvalidArgumentException("Invalid pair '$pairString'. Supported pairs: $validPairs");
        }

        return $pair;
    }

    /**
     * @param ExchangeRate[] $rates
     * @return array<string, mixed>
     */
    private function formatRatesResponse(array $rates): array
    {
        $data = [];

        foreach ($rates as $rate) {
            $data[] = [
                'timestamp' => $rate->getCreatedAt()->format('Y-m-d H:i:s'),
                'rate' => (float)$rate->getRate()
            ];
        }

        return [
            'pair' => $rates[0]->getPair()->value ?? null,
            'count' => count($data),
            'data' => $data
        ];
    }
}
