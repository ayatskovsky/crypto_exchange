<?php

declare(strict_types=1);

namespace App\Controller;

use App\Enum\CurrencyPair;
use App\Service\Interface\ExchangeRateServiceInterface;
use OpenApi\Attributes as OA;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api')]
#[OA\Tag(name: 'Cryptocurrency Exchange Rates')]
class ApiController extends AbstractController
{
    public function __construct(
        private readonly ExchangeRateServiceInterface $exchangeRateService,
        private readonly LoggerInterface $logger
    ) {}

    #[Route('/rates/last-24h', name: 'api_rates_last_24h', methods: ['GET'])]
    #[OA\Get(
        path: '/api/rates/last-24h',
        summary: 'Get exchange rates for the last 24 hours',
        parameters: [
            new OA\Parameter(
                name: 'pair',
                description: 'Currency pair (EUR/BTC, EUR/ETH, EUR/LTC)',
                in: 'query',
                required: true,
                schema: new OA\Schema(
                    type: 'string',
                    enum: ['EUR/BTC', 'EUR/ETH', 'EUR/LTC']
                )
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Successful response with 24-hour exchange rates',
                content: new OA\JsonContent(
                    properties: [
                        'pair' => new OA\Property(property: 'pair', type: 'string', example: 'EUR/BTC'),
                        'count' => new OA\Property(property: 'count', type: 'integer', example: 288),
                        'data' => new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    'timestamp' => new OA\Property(property: 'timestamp', type: 'string', format: 'datetime', example: '2025-09-02 14:00:00'),
                                    'rate' => new OA\Property(property: 'rate', type: 'number', format: 'float', example: 0.00002340)
                                ]
                            )
                        )
                    ]
                )
            ),
            new OA\Response(
                response: 400,
                description: 'Bad request - invalid or missing pair parameter',
                content: new OA\JsonContent(
                    properties: [
                        'error' => new OA\Property(property: 'error', type: 'string', example: 'Invalid pair'),
                        'supported_pairs' => new OA\Property(
                            property: 'supported_pairs',
                            type: 'array',
                            items: new OA\Items(type: 'string'),
                            example: ['EUR/BTC', 'EUR/ETH', 'EUR/LTC']
                        )
                    ]
                )
            ),
            new OA\Response(
                response: 500,
                description: 'Internal server error',
                content: new OA\JsonContent(
                    properties: [
                        'error' => new OA\Property(property: 'error', type: 'string', example: 'Internal server error')
                    ]
                )
            )
        ]
    )]
    public function getLast24Hours(Request $request): JsonResponse
    {
        try {
            $pair = $request->query->get('pair');

            if (!$pair) {
                return $this->json([
                    'error' => 'Missing required parameter: pair',
                    'supported_pairs' => CurrencyPair::values()
                ], Response::HTTP_BAD_REQUEST);
            }

            $data = $this->exchangeRateService->getLast24Hours($pair);
            return $this->json($data);

        } catch (\InvalidArgumentException $e) {
            return $this->json([
                'error' => $e->getMessage(),
                'supported_pairs' => CurrencyPair::values()
            ], Response::HTTP_BAD_REQUEST);

        } catch (\Exception $e) {
            $this->logger->error('Error in getLast24Hours endpoint', [
                'error' => $e->getMessage(),
                'pair' => $request->query->get('pair')
            ]);

            return $this->json([
                'error' => 'Internal server error'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/rates/day', name: 'api_rates_day', methods: ['GET'])]
    #[OA\Get(
        path: '/api/rates/day',
        summary: 'Get exchange rates for a specific date',
        parameters: [
            new OA\Parameter(
                name: 'pair',
                description: 'Currency pair (EUR/BTC, EUR/ETH, EUR/LTC)',
                in: 'query',
                required: true,
                schema: new OA\Schema(
                    type: 'string',
                    enum: ['EUR/BTC', 'EUR/ETH', 'EUR/LTC']
                )
            ),
            new OA\Parameter(
                name: 'date',
                description: 'Date in YYYY-MM-DD format',
                in: 'query',
                required: true,
                schema: new OA\Schema(
                    type: 'string',
                    format: 'date',
                    example: '2025-09-02'
                )
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Successful response with daily exchange rates',
                content: new OA\JsonContent(ref: '#/components/schemas/ExchangeRateResponse')
            ),
            new OA\Response(
                response: 400,
                description: 'Bad request - invalid parameters',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            )
        ]
    )]
    public function getRatesByDay(Request $request): JsonResponse
    {
        try {
            $pair = $request->query->get('pair');
            $date = $request->query->get('date');

            if (!$pair || !$date) {
                return $this->json([
                    'error' => 'Missing required parameters: pair and date',
                    'supported_pairs' => CurrencyPair::values(),
                    'date_format' => 'YYYY-MM-DD'
                ], Response::HTTP_BAD_REQUEST);
            }

            $data = $this->exchangeRateService->getRatesByDate($pair, $date);
            return $this->json($data);

        } catch (\InvalidArgumentException $e) {
            return $this->json([
                'error' => $e->getMessage(),
                'supported_pairs' => CurrencyPair::values(),
                'date_format' => 'YYYY-MM-DD'
            ], Response::HTTP_BAD_REQUEST);

        } catch (\Exception $e) {
            $this->logger->error('Error in getRatesByDay endpoint', [
                'error' => $e->getMessage(),
                'pair' => $request->query->get('pair'),
                'date' => $request->query->get('date')
            ]);

            return $this->json([
                'error' => 'Internal server error'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
