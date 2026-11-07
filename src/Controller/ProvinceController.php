<?php

declare(strict_types=1);

namespace App\Controller;

use App\Data\Province;
use App\Http\ApiResponse;
use App\Repository\LocationRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final class ProvinceController
{
    public function __construct(private readonly LocationRepository $locations)
    {
    }

    /**
     * The 52 provinces with their code and how many locations the dataset holds.
     */
    #[Route('/api/provinces', name: 'api_provinces_index', methods: ['GET'])]
    public function index(): JsonResponse
    {
        $counts = $this->locations->countByProvince();

        $provinces = [];
        foreach (Province::ALL as $code => $name) {
            $provinces[] = [
                'code' => (string) $code,
                'name' => $name,
                'locations' => $counts[$name]['locations'] ?? 0,
                'postal_codes' => $counts[$name]['postal_codes'] ?? 0,
            ];
        }

        return ApiResponse::data($provinces);
    }
}
