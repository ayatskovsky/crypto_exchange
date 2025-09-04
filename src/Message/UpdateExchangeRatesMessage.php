<?php

declare(strict_types=1);

namespace App\Message;

use Symfony\Component\Messenger\Attribute\AsMessage;

#[AsMessage('scheduler_default')]
final readonly class UpdateExchangeRatesMessage
{
    public function __construct(
        private string $triggeredAt = ''
    ) {}

    public function getTriggeredAt(): string
    {
        return $this->triggeredAt ?: (new \DateTimeImmutable())->format('Y-m-d H:i:s');
    }
}
