<?php

declare(strict_types=1);

namespace App\Support;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Local replacement for the framework's removed Waaseyaa\Api\JsonResponseTrait
 * (alpha.171 moved/replaced it with Waaseyaa\Foundation\Http\JsonApiResponseTrait,
 * which uses the JSON:API media type and a different signature).
 *
 * Minoo controllers expect the original ad-hoc JSON helpers:
 *   - $this->json(array $data, int $status = 200): JsonResponse
 *   - $this->jsonBody(Request $request): array
 *
 * This shim preserves that surface so existing callers (and tests asserting
 * application/json) keep working without rewrites.
 */
trait JsonResponseTrait
{
    /**
     * Return an application/json response.
     *
     * @param array<string, mixed>|list<mixed> $data
     * @param array<string, string> $headers
     */
    private function json(array $data, int $status = 200, array $headers = []): JsonResponse
    {
        return new JsonResponse($data, $status, $headers);
    }

    /**
     * Decode a JSON request body into an associative array.
     * Returns an empty array when the body is empty or invalid.
     *
     * @return array<string, mixed>
     */
    private function jsonBody(Request $request): array
    {
        $raw = (string) $request->getContent();
        if ($raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }
}
