<?php

declare(strict_types=1);

namespace App\Enum;

enum CurrencyPair: string
{
    case EUR_BTC = 'EUR/BTC';
    case EUR_ETH = 'EUR/ETH';
    case EUR_LTC = 'EUR/LTC';

    /**
     * Get the base currency symbol for conversion
     */
    public static function getBaseCurrencySymbol(): string
    {
        return 'EURUSDT';
    }

    /**
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get Binance symbols needed for EUR conversion
     * @return array<string>
     */
    public static function getBinancePairs(): array
    {
        return [
            'BTCUSDT',
            'ETHUSDT',
            'LTCUSDT',
            'EURUSDT'
        ];
    }

    public function getCryptoSymbol(): string
    {
        return match($this) {
            self::EUR_BTC => 'BTC',
            self::EUR_ETH => 'ETH',
            self::EUR_LTC => 'LTC',
        };
    }

    public function getBinanceSymbol(): string
    {
        return $this->getCryptoSymbol() . 'USDT';
    }

    public static function fromString(string $pair): ?self
    {
        return self::tryFrom($pair);
    }

    /**
     * @return array<string, string>
     */
    public static function getChoices(): array
    {
        $choices = [];
        foreach (self::cases() as $case) {
            $choices[$case->value] = $case->value;
        }
        return $choices;
    }

    /**
     * Get all required symbols including base currency
     * @return array<string>
     */
    public static function getAllRequiredSymbols(): array
    {
        return self::getBinancePairs();
    }

    /**
     * Check if a symbol is the base currency
     */
    public static function isBaseCurrency(string $symbol): bool
    {
        return $symbol === self::getBaseCurrencySymbol();
    }
}
