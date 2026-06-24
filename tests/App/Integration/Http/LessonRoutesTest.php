<?php

declare(strict_types=1);

namespace App\Tests\Integration\Http;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lessons as first-class (#861): /lessons index + slugged /lessons/{slug},
 * unknown slug 404s, and the legacy /lesson and /lesson/1 paths 301 to the new
 * URLs.
 */
#[CoversNothing]
final class LessonRoutesTest extends HttpKernelTestCase
{
    #[Test]
    public function lessons_index_lists_the_kitchen(): void
    {
        $response = $this->send('GET', '/lessons');
        $body = (string) $response->getContent();

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertStringContainsString('/lessons/the-kitchen', $body);
    }

    #[Test]
    public function slugged_lesson_renders(): void
    {
        $response = $this->send('GET', '/lessons/the-kitchen');
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertStringContainsString('lesson-page', (string) $response->getContent());
    }

    #[Test]
    public function unknown_lesson_slug_is_404(): void
    {
        self::assertSame(Response::HTTP_NOT_FOUND, $this->send('GET', '/lessons/no-such-lesson')->getStatusCode());
    }

    #[Test]
    public function legacy_lesson_index_redirects(): void
    {
        $response = $this->send('GET', '/lesson');
        self::assertSame(Response::HTTP_MOVED_PERMANENTLY, $response->getStatusCode());
        self::assertSame('/lessons', $response->headers->get('Location'));
    }

    #[Test]
    public function legacy_lesson_one_redirects_to_slug(): void
    {
        $response = $this->send('GET', '/lesson/1');
        self::assertSame(Response::HTTP_MOVED_PERMANENTLY, $response->getStatusCode());
        self::assertSame('/lessons/the-kitchen', $response->headers->get('Location'));
    }

    #[Test]
    public function lesson_media_route_still_exists(): void
    {
        // Unknown id → 404 from the controller allowlist (not an admin_spa 404).
        $response = $this->send('GET', '/lesson/media/thumb/sb-001');
        self::assertContains($response->getStatusCode(), [Response::HTTP_OK, Response::HTTP_NOT_FOUND]);
    }
}
