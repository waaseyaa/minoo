<?php

declare(strict_types=1);

namespace App\Tests\Integration\Http;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;

/**
 * Game URL normalization (#862): /games/{slug} is canonical, and the short links
 * 301 to it (no more duplicate-content 200s).
 */
final class GameUrlRedirectTest extends HttpKernelTestCase
{
    /** @return iterable<string, array{string, string}> */
    public static function shortLinks(): iterable
    {
        yield 'matcher' => ['/matcher', '/games/matcher'];
        yield 'agim' => ['/agim', '/games/agim'];
        yield 'shkoda' => ['/shkoda', '/games/shkoda'];
        yield 'crossword' => ['/crossword', '/games/crossword'];
    }

    #[Test]
    #[DataProvider('shortLinks')]
    public function short_link_301s_to_canonical(string $short, string $canonical): void
    {
        $response = $this->send('GET', $short);
        self::assertSame(Response::HTTP_MOVED_PERMANENTLY, $response->getStatusCode(), "{$short} must 301");
        self::assertSame($canonical, $response->headers->get('Location'));
    }

    /** @return iterable<string, array{string}> */
    public static function canonicalLinks(): iterable
    {
        yield 'matcher' => ['/games/matcher'];
        yield 'agim' => ['/games/agim'];
        yield 'shkoda' => ['/games/shkoda'];
        yield 'crossword' => ['/games/crossword'];
        yield 'journey' => ['/games/journey'];
    }

    #[Test]
    #[DataProvider('canonicalLinks')]
    public function canonical_game_url_serves(string $canonical): void
    {
        self::assertSame(Response::HTTP_OK, $this->send('GET', $canonical)->getStatusCode(), "{$canonical} must 200");
    }

    #[Test]
    public function legacy_ishkode_still_redirects_to_shkoda(): void
    {
        $response = $this->send('GET', '/games/ishkode');
        self::assertContains($response->getStatusCode(), [Response::HTTP_MOVED_PERMANENTLY, Response::HTTP_PERMANENTLY_REDIRECT]);
        self::assertStringContainsString('/games/shkoda', (string) $response->headers->get('Location'));
    }
}
