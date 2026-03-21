<?php

declare(strict_types=1);

namespace Minoo\Support;

/**
 * Geocodes addresses via the Nominatim (OpenStreetMap) API.
 */
final class GeocodingService
{
    private const USER_AGENT = 'Minoo/1.0 (https://minoo.live; russell@minoo.live)';

    private const TIMEOUT = 10;

    private const BASE_URL = 'https://nominatim.openstreetmap.org/search';

    /**
     * Geocode an address string to lat/lng coordinates.
     *
     * @return array{lat: float, lng: float}|null
     */
    public function geocode(string $address): ?array
    {
        $url = self::buildUrl($address);
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => self::TIMEOUT,
                'ignore_errors' => true,
                'header' => 'User-Agent: ' . self::USER_AGENT . "\r\n",
            ],
        ]);

        $attempt = 0;

        while ($attempt < 2) {
            $json = @file_get_contents($url, false, $context);

            if ($json === false) {
                $attempt++;
                if ($attempt < 2) {
                    usleep(2_000_000);
                }
                continue;
            }

            // Check for retry-worthy HTTP status codes.
            $statusCode = $this->extractStatusCode($http_response_header ?? []);
            if ($statusCode === 429 || $statusCode === 503) {
                $attempt++;
                if ($attempt < 2) {
                    usleep(2_000_000);
                }
                continue;
            }

            return self::parseResponse($json);
        }

        return null;
    }

    /**
     * Build the Nominatim search URL for a given address.
     */
    public static function buildUrl(string $address): string
    {
        return self::BASE_URL . '?' . http_build_query([
            'q' => $address,
            'format' => 'json',
            'limit' => 1,
        ]);
    }

    /**
     * Parse a Nominatim JSON response into lat/lng.
     *
     * @return array{lat: float, lng: float}|null
     */
    public static function parseResponse(string $json): ?array
    {
        $data = json_decode($json, true);

        if (!is_array($data) || $data === []) {
            return null;
        }

        $first = $data[0] ?? null;

        if (!is_array($first) || !isset($first['lat'], $first['lon'])) {
            return null;
        }

        $lat = (float) $first['lat'];
        $lng = (float) $first['lon'];

        if ($lat === 0.0 && $lng === 0.0) {
            return null;
        }

        return ['lat' => $lat, 'lng' => $lng];
    }

    /**
     * Extract the HTTP status code from response headers.
     *
     * @param list<string> $headers
     */
    private function extractStatusCode(array $headers): int
    {
        if ($headers === []) {
            return 0;
        }

        // The first header line is like "HTTP/1.1 200 OK".
        if (preg_match('/HTTP\/\S+\s+(\d{3})/', $headers[0], $matches)) {
            return (int) $matches[1];
        }

        return 0;
    }
}
