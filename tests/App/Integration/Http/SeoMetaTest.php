<?php

declare(strict_types=1);

namespace App\Tests\Integration\Http;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;

/**
 * SEO head (#865): self-referential canonical + hreflang alternates + robots on
 * every page; search results are noindex; lessons carry Course/LearningResource
 * JSON-LD and a BreadcrumbList.
 */
#[CoversNothing]
final class SeoMetaTest extends HttpKernelTestCase
{
    #[Test]
    public function homepage_has_canonical_hreflang_and_indexable_robots(): void
    {
        $body = (string) $this->send('GET', '/')->getContent();

        self::assertStringContainsString('<link rel="canonical" href="', $body);
        self::assertMatchesRegularExpression('/<link rel="canonical" href="https?:\/\/[^"]+\/"/', $body);
        self::assertStringContainsString('rel="alternate" hreflang="oj"', $body);
        self::assertStringContainsString('rel="alternate" hreflang="x-default"', $body);
        self::assertStringContainsString('name="robots" content="index, follow"', $body);
    }

    #[Test]
    public function search_results_are_noindex(): void
    {
        $body = (string) $this->send('GET', '/language/search', ['q' => 'nibi'])->getContent();
        self::assertStringContainsString('name="robots" content="noindex, follow"', $body);
    }

    #[Test]
    public function lesson_has_course_jsonld_and_breadcrumb_and_canonical(): void
    {
        $response = $this->send('GET', '/lessons/the-kitchen');
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $body = (string) $response->getContent();

        self::assertStringContainsString('"@type":["Course","LearningResource"]', $body);
        self::assertStringContainsString('"@type":"BreadcrumbList"', $body);
        self::assertStringContainsString('/lessons/the-kitchen"', $body);
    }

    #[Test]
    public function games_index_has_breadcrumb_jsonld(): void
    {
        $body = (string) $this->send('GET', '/games')->getContent();
        self::assertStringContainsString('"@type":"BreadcrumbList"', $body);
        self::assertStringContainsString('name="robots" content="index, follow"', $body);
    }
}
