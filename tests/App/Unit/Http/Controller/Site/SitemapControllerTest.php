<?php

declare(strict_types=1);

namespace App\Tests\Unit\Http\Controller\Site;

use App\Http\Controller\Site\SitemapController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\ContentEntityBase;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Entity\Repository\EntityRepositoryInterface;
use Waaseyaa\Entity\Storage\EntityQueryInterface;

#[CoversClass(SitemapController::class)]
final class SitemapControllerTest extends TestCase
{
    #[Test]
    public function emits_valid_xml_with_section_community_and_entry_urls(): void
    {
        $twig = new Environment(new ArrayLoader([
            'sitemap.xml.twig' => '<?xml version="1.0" encoding="UTF-8"?>'
                . '<urlset>{% for p in paths %}<url><loc>{{ site_base_url }}{{ p }}</loc></url>{% endfor %}</urlset>',
        ]));
        $twig->addGlobal('site_base_url', 'https://minoo.live');

        $entry = $this->createMock(ContentEntityBase::class);
        $entry->method('get')->willReturnCallback(static fn (string $f) => $f === 'slug' ? 'makwa' : null);

        $query = $this->createMock(EntityQueryInterface::class);
        $query->method('accessCheck')->willReturnSelf();
        $query->method('condition')->willReturnSelf();
        $query->method('sort')->willReturnSelf();
        $query->method('range')->willReturnSelf();
        $query->method('execute')->willReturn([1]);

        $repository = $this->createMock(EntityRepositoryInterface::class);
        $repository->method('getQuery')->willReturn($query);
        // findMany() returns a list, not the old id-keyed map.
        $repository->method('findMany')->willReturn([$entry]);

        $etm = $this->createMock(EntityTypeManager::class);
        $etm->method('getRepository')->willReturn($repository);

        $controller = new SitemapController($twig, $etm);
        $response = $controller->xml([], [], $this->createMock(AccountInterface::class), HttpRequest::create('/sitemap.xml'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('application/xml', (string) $response->headers->get('Content-Type'));

        $body = $response->getContent();
        $this->assertStringContainsString('https://minoo.live/', $body);
        $this->assertStringContainsString('https://minoo.live/lessons', $body);
        $this->assertStringContainsString('https://minoo.live/lessons/the-kitchen', $body);
        $this->assertStringContainsString('https://minoo.live/communities/sagamok-anishnawbek', $body);
        $this->assertStringContainsString('https://minoo.live/language/makwa', $body);
        // Search results are noindex, so they must not be in the sitemap.
        $this->assertStringNotContainsString('/language/search', $body);

        // Well-formed XML.
        $doc = new \DOMDocument();
        $this->assertTrue($doc->loadXML($body));
    }
}
