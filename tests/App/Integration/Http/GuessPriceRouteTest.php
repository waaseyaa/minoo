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
}
