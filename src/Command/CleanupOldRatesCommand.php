<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\ExchangeRateService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:cleanup-old-rates',
    description: 'Remove exchange rate records older than 30 days'
)]
class CleanupOldRatesCommand extends Command
{
    public function __construct(
        private readonly ExchangeRateService $exchangeRateService
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->info('Cleaning up old exchange rate records...');

        try {
            $deletedCount = $this->exchangeRateService->cleanupOldData();
            $io->success("Successfully deleted $deletedCount old records!");
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Failed to cleanup old records: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
