<?php

declare(strict_types=1);

namespace App\Service;

use App\Enum\CurrencyPair;
use App\Service\Interface\ApiClientInterface;
use App\Service\Interface\BinanceApiServiceInterface;
use App\Service\Interface\RateCalculatorInterface;
use App\Service\Interface\RetryHandlerInterface;
use Psr\Log\LoggerInterface;

final readonly class BinanceApiService implements BinanceApiServiceInterface
{
    public function __construct(
        private readonly ApiClientInterface $apiClient,
        private readonly RateCalculatorInterface $rateCalculator,
        private readonly RetryHandlerInterface $retryHandler,
        private readonly LoggerInterface $logger
    ) {}

    /**
     * Get EUR exchange rates for all supported cryptocurrency pairs
     *
     * @return array<string, float>
     * @throws \Exception
     */
    public function getExchangeRates(): array
    {
        $startTime = microtime(true);
        $operationId = uniqid('eur_rates_', true);

        $this->logger->info('Starting EUR exchange rates fetch process', [
            'operation_id' => $operationId,
            'required_pairs' => CurrencyPair::values(),
            'base_currency' => CurrencyPair::getBaseCurrencySymbol()
        ]);

        try {
            $result = $this->retryHandler->execute(function () use ($operationId) {
                return $this->fetchAndCalculateRates($operationId);
            });

            $executionTime = round((microtime(true) - $startTime) * 1000, 2);

            $this->logger->info('EUR exchange rates fetch completed successfully', [
                'operation_id' => $operationId,
                'execution_time_ms' => $executionTime,
                'pairs_count' => count($result),
                'rates' => array_map(fn($rate) => number_format($rate, 8), $result)
            ]);

            return $result;

        } catch (\Exception $e) {
            $executionTime = round((microtime(true) - $startTime) * 1000, 2);

            $this->logger->error('Failed to fetch EUR exchange rates', [
                'operation_id' => $operationId,
                'execution_time_ms' => $executionTime,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw new \Exception('Failed to fetch EUR exchange rates: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Get exchange rate for a single currency pair
     *
     * @throws \Exception
     */
    public function getSingleRate(CurrencyPair $pair): float
    {
        $this->logger->debug('Getting single rate for pair', [
            'pair' => $pair->value
        ]);

        $rates = $this->getExchangeRates();

        if (!isset($rates[$pair->value])) {
            $this->logger->error('Rate not found for pair', [
                'pair' => $pair->value,
                'available_pairs' => array_keys($rates)
            ]);
            throw new \Exception("Rate not found for pair: {$pair->value}");
        }

        $rate = $rates[$pair->value];

        $this->logger->info('Successfully retrieved single rate', [
            'pair' => $pair->value,
            'rate' => $rate
        ]);

        return $rate;
    }

    /**
     * Check if Binance API is accessible and responding
     */
    public function healthCheck(): bool
    {
        $this->logger->info('Starting Binance API health check');

        try {
            $isHealthy = $this->apiClient->healthCheck();

            $this->logger->info('Binance API health check completed', [
                'healthy' => $isHealthy
            ]);

            return $isHealthy;

        } catch (\Exception $e) {
            $this->logger->error('Binance API health check failed', [
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Get Binance API rate limiting information
     *
     * @return array<string, mixed>|null
     */
    public function getRateLimitInfo(): ?array
    {
        try {
            $rateLimits = $this->apiClient->getRateLimitInfo();

            if ($rateLimits !== null) {
                $this->logger->debug('Retrieved Binance API rate limits', [
                    'rate_limits_count' => count($rateLimits)
                ]);
            } else {
                $this->logger->warning('Failed to retrieve rate limit information');
            }

            return $rateLimits;

        } catch (\Exception $e) {
            $this->logger->error('Error getting rate limit info', [
                'error' => $e->getMessage()
            ]);

            return null;
        }
    }

    /**
     * Internal method to fetch prices and calculate rates
     *
     * @return array<string, float>
     * @throws \Exception
     */
    private function fetchAndCalculateRates(string $operationId): array
    {
        $this->logger->debug('Fetching prices and calculating rates', [
            'operation_id' => $operationId
        ]);

        // Step 1: Get required symbols
        $symbols = CurrencyPair::getBinancePairs();

        $this->logger->debug('Required symbols identified', [
            'operation_id' => $operationId,
            'symbols' => $symbols,
            'symbols_count' => count($symbols)
        ]);

        // Step 2: Fetch prices from Binance
        $prices = $this->apiClient->fetchPrices($symbols);

        $this->logger->debug('Prices fetched from API', [
            'operation_id' => $operationId,
            'prices_received' => count($prices),
            'base_currency_present' => isset($prices[CurrencyPair::getBaseCurrencySymbol()])
        ]);

        // Step 3: Calculate EUR rates
        $rates = $this->rateCalculator->calculateEurRates($prices);

        $this->logger->debug('EUR rates calculated', [
            'operation_id' => $operationId,
            'rates_calculated' => count($rates)
        ]);

        return $rates;
    }
}
