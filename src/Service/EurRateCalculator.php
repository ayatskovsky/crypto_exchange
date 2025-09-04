<?php

declare(strict_types=1);

namespace App\Service;

use App\Enum\CurrencyPair;
use App\Service\Interface\RateCalculatorInterface;
use Psr\Log\LoggerInterface;

final readonly class EurRateCalculator implements RateCalculatorInterface
{
    public function __construct(
        private readonly LoggerInterface $logger
    ) {}

    public function calculateEurRates(array $prices): array
    {
        $calculationId = uniqid('calc_', true);

        $this->logger->debug('Starting EUR rate calculation', [
            'calculation_id' => $calculationId,
            'input_prices' => array_keys($prices),
            'base_currency' => CurrencyPair::getBaseCurrencySymbol()
        ]);

        $this->validateInputPrices($prices);

        $eurUsdtRate = $prices[CurrencyPair::getBaseCurrencySymbol()];
        $result = [];

        foreach (CurrencyPair::cases() as $pair) {
            $cryptoSymbol = $pair->getBinanceSymbol();

            if (!isset($prices[$cryptoSymbol])) {
                $this->logger->warning('Missing price for calculation', [
                    'calculation_id' => $calculationId,
                    'symbol' => $cryptoSymbol,
                    'pair' => $pair->value
                ]);
                continue;
            }

            $cryptoUsdtRate = $prices[$cryptoSymbol];
            $eurCryptoRate = $this->calculateRate($eurUsdtRate, $cryptoUsdtRate);

            $result[$pair->value] = $eurCryptoRate;

            $this->logger->debug('Calculated rate for pair', [
                'calculation_id' => $calculationId,
                'pair' => $pair->value,
                'base_currency_rate' => $eurUsdtRate,
                'crypto_usdt_rate' => $cryptoUsdtRate,
                'eur_crypto_rate' => $eurCryptoRate
            ]);
        }

        if (empty($result)) {
            throw new \Exception('No valid EUR exchange rates could be calculated');
        }

        $this->logger->info('EUR rate calculation completed', [
            'calculation_id' => $calculationId,
            'pairs_calculated' => count($result),
            'base_currency' => CurrencyPair::getBaseCurrencySymbol()
        ]);

        return $result;
    }

    private function validateInputPrices(array $prices): void
    {
        $baseCurrency = CurrencyPair::getBaseCurrencySymbol();

        if (!isset($prices[$baseCurrency])) {
            throw new \Exception("Base currency price ($baseCurrency) not available for conversion");
        }

        $eurUsdtRate = $prices[$baseCurrency];
        if ($eurUsdtRate <= 0) {
            throw new \Exception("Invalid base currency rate ($baseCurrency) for conversion");
        }
    }

    private function calculateRate(float $eurUsdtRate, float $cryptoUsdtRate): float
    {
        if ($cryptoUsdtRate <= 0) {
            throw new \Exception('Invalid crypto USDT rate for calculation');
        }

        // Calculate how much crypto 1 EUR can buy
        return $eurUsdtRate / $cryptoUsdtRate;
    }
}
