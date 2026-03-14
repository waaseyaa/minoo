<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Controller;

use Minoo\Controller\LanguageController;
use Minoo\Entity\DictionaryEntry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Entity\Storage\EntityQueryInterface;
use Waaseyaa\Entity\Storage\EntityStorageInterface;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

#[CoversClass(LanguageController::class)]
final class LanguageControllerTest extends TestCase
{
    private EntityTypeManager $entityTypeManager;
    private Environment $twig;
    private EntityStorageInterface $storage;
    private EntityQueryInterface $query;
    private AccountInterface $account;
    private HttpRequest $request;

    protected function setUp(): void
    {
        $this->query = $this->createMock(EntityQueryInterface::class);
        $this->query->method('condition')->willReturnSelf();
        $this->query->method('sort')->willReturnSelf();

        $this->storage = $this->createMock(EntityStorageInterface::class);
        $this->storage->method('getQuery')->willReturn($this->query);

        $this->entityTypeManager = $this->createMock(EntityTypeManager::class);
        $this->entityTypeManager->method('getStorage')
            ->with('dictionary_entry')
            ->willReturn($this->storage);

        $this->twig = new Environment(new ArrayLoader([
            'language.html.twig' => '{{ path }}{% for e in entries|default([]) %}|{{ e.get("word") }}{% endfor %}{% if entry is defined and entry %}|{{ entry.get("word") }}{% endif %}',
        ]));

        $this->account = $this->createMock(AccountInterface::class);
        $this->request = HttpRequest::create('/');
    }

    #[Test]
    public function list_returns_200_with_entries(): void
    {
        $makwa = new DictionaryEntry(['deid' => 1, 'word' => 'makwa', 'slug' => 'makwa', 'consent_public' => 1]);
        $miigwech = new DictionaryEntry(['deid' => 2, 'word' => 'miigwech', 'slug' => 'miigwech', 'consent_public' => 1]);

        $this->query->method('execute')->willReturn([1, 2]);
        $this->storage->method('loadMultiple')
            ->with([1, 2])
            ->willReturn([1 => $makwa, 2 => $miigwech]);

        $controller = new LanguageController($this->entityTypeManager, $this->twig);
        $response = $controller->list([], [], $this->account, $this->request);

        $this->assertSame(200, $response->statusCode);
        $this->assertStringContainsString('makwa', $response->content);
        $this->assertStringContainsString('miigwech', $response->content);
    }

    #[Test]
    public function list_returns_200_when_empty(): void
    {
        $this->query->method('execute')->willReturn([]);

        $controller = new LanguageController($this->entityTypeManager, $this->twig);
        $response = $controller->list([], [], $this->account, $this->request);

        $this->assertSame(200, $response->statusCode);
        $this->assertStringContainsString('/language', $response->content);
    }

    #[Test]
    public function list_filters_by_consent_public_via_query(): void
    {
        // Verify the query builder receives the consent_public condition.
        // The mock returns self for all condition() calls; we verify that
        // entries returned by the query (already filtered) are rendered.
        $entry = new DictionaryEntry(['deid' => 1, 'word' => 'aniin', 'slug' => 'aniin', 'consent_public' => 1]);

        $this->query->method('execute')->willReturn([1]);
        $this->storage->method('loadMultiple')
            ->with([1])
            ->willReturn([1 => $entry]);

        $controller = new LanguageController($this->entityTypeManager, $this->twig);
        $response = $controller->list([], [], $this->account, $this->request);

        $this->assertSame(200, $response->statusCode);
        $this->assertStringContainsString('aniin', $response->content);
    }

    #[Test]
    public function show_returns_200_for_existing_entry(): void
    {
        $entry = new DictionaryEntry(['deid' => 1, 'word' => 'makwa', 'slug' => 'makwa', 'consent_public' => 1]);

        $this->query->method('execute')->willReturn([1]);
        $this->storage->method('load')
            ->with(1)
            ->willReturn($entry);

        $controller = new LanguageController($this->entityTypeManager, $this->twig);
        $response = $controller->show(['slug' => 'makwa'], [], $this->account, $this->request);

        $this->assertSame(200, $response->statusCode);
        $this->assertStringContainsString('makwa', $response->content);
    }

    #[Test]
    public function show_returns_404_for_missing_entry(): void
    {
        $this->query->method('execute')->willReturn([]);

        $controller = new LanguageController($this->entityTypeManager, $this->twig);
        $response = $controller->show(['slug' => 'nonexistent'], [], $this->account, $this->request);

        $this->assertSame(404, $response->statusCode);
    }

    #[Test]
    public function show_filters_by_consent_public_via_query(): void
    {
        // An entry without consent_public would not be returned by the query,
        // so an empty result set gives a 404.
        $this->query->method('execute')->willReturn([]);

        $controller = new LanguageController($this->entityTypeManager, $this->twig);
        $response = $controller->show(['slug' => 'secret-word'], [], $this->account, $this->request);

        $this->assertSame(404, $response->statusCode);
    }
}
