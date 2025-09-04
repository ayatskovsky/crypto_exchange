<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\UpdateExchangeRatesMessage;
use App\Service\Interface\ExchangeRateServiceInterface;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class UpdateExchangeRatesMessageHandler
{
    public function __construct(
        private ExchangeRateServiceInterface $exchangeRateService,
        private LoggerInterface              $logger
    ) {}

    /**
     * @throws Exception
     */
    public function __invoke(UpdateExchangeRatesMessage $message): void
    {
        $this->logger->info('Starting scheduled exchange rates update', [
            'triggered_at' => $message->getTriggeredAt()
        ]);

        try {
            $this->exchangeRateService->updateRates();

            $this->logger->info('Scheduled exchange rates update completed successfully');

        } catch (Exception $e) {
            $this->logger->error('Scheduled exchange rates update failed', [
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }
}
