<?php

declare(strict_types=1);

namespace App\Service;

use App\Enum\CurrencyPair;
use App\Service\Interface\ApiClientInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class BinanceApiClient implements ApiClientInterface
{
    private const string PRICE_ENDPOINT = '/api/v3/ticker/price';
    private const string PING_ENDPOINT = '/api/v3/ping';
    private const string EXCHANGE_INFO_ENDPOINT = '/api/v3/exchangeInfo';
    private const int REQUEST_TIMEOUT = 10;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly string $binanceApiBaseUrl
    ) {}

    public function fetchPrices(array $symbols): array
    {
        $requestId = uniqid('binance_', true);

        $this->logger->debug('Fetching prices from Binance API', [
            'request_id' => $requestId,
            'symbols' => $symbols,
            'base_currency' => CurrencyPair::getBaseCurrencySymbol()
        ]);

        try {
            $response = $this->httpClient->request('GET', $this->binanceApiBaseUrl . self::PRICE_ENDPOINT, [
                'query' => ['symbols' => json_encode($symbols)],
                'timeout' => self::REQUEST_TIMEOUT,
                'headers' => [
                    'User-Agent' => 'CryptoRatesAPI/1.0',
                    'X-Request-ID' => $requestId
                ]
            ]);

            $data = $response->toArray();
            return $this->parsePriceResponse($data, $requestId);

        } catch (DecodingExceptionInterface $e) {
            $this->logger->error('Failed to decode API response', [
                'request_id' => $requestId,
                'error' => $e->getMessage()
            ]);
            throw new \Exception('Invalid response format: ' . $e->getMessage());
        }
    }

    public function healthCheck(): bool
    {
        try {
            $response = $this->httpClient->request('GET', $this->binanceApiBaseUrl . self::PING_ENDPOINT, [
                'timeout' => 5,
                'headers' => ['User-Agent' => 'CryptoRatesAPI/1.0']
            ]);

            return $response->getStatusCode() === 200;

        } catch (\Exception $e) {
            $this->logger->error('Health check failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    public function getRateLimitInfo(): ?array
    {
        try {
            $response = $this->httpClient->request('GET', $this->binanceApiBaseUrl . self::EXCHANGE_INFO_ENDPOINT, [
                'timeout' => 5
            ]);

            $data = $response->toArray();
            return $data['rateLimits'] ?? null;

        } catch (\Exception $e) {
            $this->logger->warning('Failed to get rate limit info', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * @return array<string, float>
     * @throws \Exception
     */
    private function parsePriceResponse(array $data, string $requestId): array
    {
        if (!is_array($data) || empty($data)) {
            throw new \Exception('Invalid response format from Binance API');
        }

        $prices = [];
        $foundBaseCurrency = false;

        foreach ($data as $item) {
            if (!isset($item['symbol'], $item['price'])) {
                $this->logger->warning('Invalid price data', [
                    'request_id' => $requestId,
                    'item' => $item
                ]);
                continue;
            }

            $symbol = $item['symbol'];
            $price = (float) $item['price'];

            if ($price <= 0) {
                $this->logger->warning('Invalid price value', [
                    'request_id' => $requestId,
                    'symbol' => $symbol,
                    'price' => $item['price']
                ]);
                continue;
            }

            $prices[$symbol] = $price;

            // Track if we found the base currency
            if (CurrencyPair::isBaseCurrency($symbol)) {
                $foundBaseCurrency = true;
                $this->logger->debug('Base currency found', [
                    'request_id' => $requestId,
                    'base_currency' => $symbol,
                    'rate' => $price
                ]);
            }
        }

        if (!$foundBaseCurrency) {
            $this->logger->error('Base currency not found in response', [
                'request_id' => $requestId,
                'base_currency' => CurrencyPair::getBaseCurrencySymbol(),
                'found_symbols' => array_keys($prices)
            ]);
        }

        $this->logger->debug('Parsed price response', [
            'request_id' => $requestId,
            'total_prices' => count($prices),
            'base_currency_found' => $foundBaseCurrency
        ]);

        return $prices;
    }
}
