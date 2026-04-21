<?php

declare(strict_types=1);

namespace App\Tests\Integration\Http;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;

#[CoversNothing]
final class GuessPriceRouteTest extends HttpKernelTestCase
{
    #[Test]
    public function guess_price_page_returns_200_and_shell(): void
    {
        $response = $this->send('GET', '/games/guess-price');
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $body = (string) $response->getContent();
        $this->assertStringContainsString('guess-price-game', $body);
        $this->assertStringContainsString('guess-price-i18n', $body);
    }

    #[Test]
    public function guess_price_short_path_aliases_to_same_page(): void
    {
        $response = $this->send('GET', '/guess-price');
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertStringContainsString('guess-price-game', (string) $response->getContent());
    }

    #[Test]
    public function guess_price_trailing_slash_redirects_to_canonical(): void
    {
        $response = $this->send('GET', '/games/guess-price/');
        $this->assertSame(Response::HTTP_PERMANENTLY_REDIRECT, $response->getStatusCode());
        $this->assertSame('/games/guess-price', $response->headers->get('Location'));
    }

    #[Test]
    public function guess_price_catalog_json_on_disk_is_valid(): void
    {
        // Static JSON is served by the web server from public/; the kernel test harness does not emulate that.
        $path = self::$projectRoot . '/public/data/games/guess-price/items.json';
        $this->assertFileExists($path);
        $data = json_decode((string) file_get_contents($path), true);
        $this->assertIsArray($data);
        $this->assertGreaterThanOrEqual(3, count($data));
        foreach ($data as $row) {
            $this->assertIsArray($row);
            $this->assertArrayHasKey('id', $row);
            $this->assertArrayHasKey('name', $row);
            $this->assertArrayHasKey('image', $row);
            $this->assertArrayHasKey('actual_price', $row);
            $this->assertIsString($row['id']);
            $this->assertIsString($row['name']);
            $this->assertIsString($row['image']);
            $this->assertIsNumeric($row['actual_price']);
            $this->assertGreaterThan(0, (float) $row['actual_price']);
        }
    }
}
