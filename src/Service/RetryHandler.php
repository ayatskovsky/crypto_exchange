<?php

declare(strict_types=1);

namespace App\Service;

use App\Service\Interface\RetryHandlerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

final readonly class RetryHandler implements RetryHandlerInterface
{
    private const int DEFAULT_MAX_RETRIES = 3;
    private const int DEFAULT_DELAY_MS = 1000;

    public function __construct(
        private readonly LoggerInterface $logger
    ) {}

    public function execute(callable $operation, int $maxRetries = self::DEFAULT_MAX_RETRIES, int $delayMs = self::DEFAULT_DELAY_MS): mixed
    {
        $operationId = uniqid('retry_', true);
        $lastException = null;

        $this->logger->debug('Starting retry operation', [
            'operation_id' => $operationId,
            'max_retries' => $maxRetries,
            'delay_ms' => $delayMs
        ]);

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                $this->logger->debug('Executing operation attempt', [
                    'operation_id' => $operationId,
                    'attempt' => $attempt,
                    'max_retries' => $maxRetries
                ]);

                $result = $operation();

                if ($attempt > 1) {
                    $this->logger->info('Operation succeeded after retry', [
                        'operation_id' => $operationId,
                        'successful_attempt' => $attempt
                    ]);
                }

                return $result;

            } catch (TransportExceptionInterface $e) {
                $lastException = $e;
                $this->logRetryAttempt('Network/Transport error', $e, $operationId, $attempt, $maxRetries);

                if ($attempt < $maxRetries) {
                    $this->waitBeforeRetry($delayMs, $attempt);
                    continue;
                }

            } catch (ClientExceptionInterface|ServerExceptionInterface|RedirectionExceptionInterface $e) {
                $lastException = $e;
                $statusCode = $e->getResponse()->getStatusCode();

                $this->logger->error('HTTP error during operation', [
                    'operation_id' => $operationId,
                    'attempt' => $attempt,
                    'status_code' => $statusCode,
                    'error' => $e->getMessage()
                ]);

                // Only retry server errors (5xx), not client errors (4xx)
                if ($statusCode >= 500 && $attempt < $maxRetries) {
                    $this->waitBeforeRetry($delayMs, $attempt);
                    continue;
                }
                break;

            } catch (\Exception $e) {
                $lastException = $e;
                $this->logger->error('Unexpected error during operation', [
                    'operation_id' => $operationId,
                    'attempt' => $attempt,
                    'error' => $e->getMessage(),
                    'exception_class' => get_class($e)
                ]);

                // For general exceptions, attempt retry
                if ($attempt < $maxRetries) {
                    $this->waitBeforeRetry($delayMs, $attempt);
                    continue;
                }
                break;
            }
        }

        // All attempts failed
        $this->logger->critical('Operation failed after all retry attempts', [
            'operation_id' => $operationId,
            'total_attempts' => $maxRetries,
            'last_error' => $lastException?->getMessage(),
            'last_exception_class' => $lastException ? get_class($lastException) : null
        ]);

        throw new \RuntimeException(
            'Operation failed after ' . $maxRetries . ' attempts: ' . $lastException?->getMessage(),
            0,
            $lastException
        );
    }

    /**
     * Log retry attempt details
     */
    private function logRetryAttempt(string $errorType, \Throwable $exception, string $operationId, int $attempt, int $maxRetries): void
    {
        $this->logger->warning($errorType . ' - will retry', [
            'operation_id' => $operationId,
            'attempt' => $attempt,
            'max_attempts' => $maxRetries,
            'error' => $exception->getMessage(),
            'exception_class' => get_class($exception)
        ]);
    }

    /**
     * Wait before next retry attempt with exponential backoff
     */
    private function waitBeforeRetry(int $baseDelayMs, int $attempt): void
    {
        // Exponential backoff: delay increases with each attempt
        $delay = $baseDelayMs * pow(2, $attempt - 1);

        // Add some jitter to prevent thundering herd
        $jitter = rand(0, (int)($delay * 0.1)); // 10% jitter
        $finalDelay = $delay + $jitter;

        $this->logger->debug('Waiting before retry', [
            'delay_ms' => $finalDelay,
            'base_delay' => $baseDelayMs,
            'attempt' => $attempt
        ]);

        usleep($finalDelay * 1000);
    }
}
