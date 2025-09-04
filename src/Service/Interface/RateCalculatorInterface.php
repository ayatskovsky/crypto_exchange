<?php

declare(strict_types=1);

namespace App\Service\Interface;

interface RateCalculatorInterface
{
    /**
     * @param array<string, float> $prices
     * @return array<string, float>
     */
    public function calculateEurRates(array $prices): array;
}
