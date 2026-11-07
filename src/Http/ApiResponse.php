<?php

declare(strict_types=1);

namespace App\Http;

use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Builds JSON responses with readable UTF-8 output (no "á" escapes).
 */
final class ApiResponse
{
    private const ENCODING_OPTIONS = JsonResponse::DEFAULT_ENCODING_OPTIONS | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES;

    private function __construct()
    {
    }

    public static function data(mixed $data, array $meta = [], int $status = 200): JsonResponse
    {
        $payload = ['data' => $data];
        if ([] !== $meta) {
            $payload['meta'] = $meta;
        }

        return self::json($payload, $status);
    }

    public static function error(int $status, string $message): JsonResponse
    {
        return self::json(['error' => ['status' => $status, 'message' => $message]], $status);
    }

    public static function json(mixed $payload, int $status = 200): JsonResponse
    {
        $response = new JsonResponse(null, $status);
        $response->setEncodingOptions(self::ENCODING_OPTIONS);
        $response->setData($payload);

        return $response;
    }
}
