<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\ApiResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final class IndexController
{
    #[Route('/', name: 'api_index', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        return ApiResponse::json([
            'name' => 'Spain Postal Codes API',
            'documentation' => '/openapi.yaml',
            'source' => 'https://github.com/codecruz/spain-postal-codes',
            'endpoints' => [
                'GET /api/locations' => 'Paginated list of locations. Filters: provincia, q, page, limit.',
                'GET /api/locations/{codPostal}' => 'Towns served by a 5-digit postal code.',
                'GET /api/provinces' => 'The 52 provinces with their location counts.',
            ],
        ]);
    }
}
