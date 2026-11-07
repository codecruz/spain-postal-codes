<?php

declare(strict_types=1);

namespace App\Controller;

use App\Data\Province;
use App\Http\ApiResponse;
use App\Repository\LocationRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[Route('/api/locations', name: 'api_locations_')]
final class LocationController
{
    private const DEFAULT_LIMIT = 50;
    private const MAX_LIMIT = 500;

    public function __construct(private readonly LocationRepository $locations)
    {
    }

    /**
     * Paginated list of locations, optionally filtered by province and town name.
     */
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request): JsonResponse
    {
        $page = $this->positiveInt($request, 'page', 1);
        $limit = $this->positiveInt($request, 'limit', self::DEFAULT_LIMIT);
        if ($limit > self::MAX_LIMIT) {
            throw new BadRequestHttpException(\sprintf('Query parameter "limit" must not exceed %d.', self::MAX_LIMIT));
        }

        $provincia = null;
        $rawProvincia = trim($request->query->getString('provincia'));
        if ('' !== $rawProvincia) {
            $provincia = Province::resolve($rawProvincia)
                ?? throw new BadRequestHttpException(\sprintf('Unknown province "%s". Use a province code (e.g. "28") or name (e.g. "Madrid").', $rawProvincia));
        }

        $search = trim($request->query->getString('q'));
        if ('' !== $search && mb_strlen($search) < 2) {
            throw new BadRequestHttpException('Query parameter "q" must be at least 2 characters long.');
        }

        $result = $this->locations->paginate($provincia, '' === $search ? null : $search, $page, $limit);

        return ApiResponse::data($result['items'], [
            'page' => $page,
            'limit' => $limit,
            'total' => $result['total'],
            'pages' => (int) ceil($result['total'] / $limit),
        ]);
    }

    /**
     * All towns served by a postal code.
     */
    #[Route('/{codPostal}', name: 'show', methods: ['GET'])]
    public function show(string $codPostal): JsonResponse
    {
        // Accept codes whose leading zero was dropped (e.g. "1450" for "01450").
        if (1 === preg_match('/^\d{4}$/', $codPostal)) {
            $codPostal = '0'.$codPostal;
        }

        if (1 !== preg_match('/^\d{5}$/', $codPostal)) {
            throw new BadRequestHttpException(\sprintf('Invalid postal code "%s": it must have 5 digits.', $codPostal));
        }

        $locations = $this->locations->findByCodPostal($codPostal);
        if ([] === $locations) {
            throw new NotFoundHttpException(\sprintf('No locations found for postal code %s.', $codPostal));
        }

        return ApiResponse::data($locations);
    }

    private function positiveInt(Request $request, string $name, int $default): int
    {
        $raw = $request->query->getString($name);
        if ('' === $raw) {
            return $default;
        }

        $value = filter_var($raw, \FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (false === $value) {
            throw new BadRequestHttpException(\sprintf('Query parameter "%s" must be a positive integer.', $name));
        }

        return $value;
    }
}
