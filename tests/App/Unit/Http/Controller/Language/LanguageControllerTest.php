<?php

declare(strict_types=1);

namespace App\Tests\Unit\Http\Controller\Language;

use App\Entity\Language\DictionaryEntry;
use App\Http\Controller\Language\LanguageController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Entity\Repository\EntityRepositoryInterface;
use Waaseyaa\Entity\Storage\EntityQueryInterface;

#[CoversClass(LanguageController::class)]
final class LanguageControllerTest extends TestCase
{
    private EntityTypeManager $entityTypeManager;
    private Environment $twig;
    private EntityRepositoryInterface $repository;
    private EntityQueryInterface $query;
    private AccountInterface $account;
    private HttpRequest $request;

    protected function setUp(): void
    {
        $this->query = $this->createMock(EntityQueryInterface::class);
        $this->query->method('setAccount')->willReturnSelf();
        $this->query->method('accessCheck')->willReturnSelf();
        $this->query->method('condition')->willReturnSelf();
        $this->query->method('sort')->willReturnSelf();
        $this->query->method('range')->willReturnSelf();

        $this->repository = $this->createMock(EntityRepositoryInterface::class);
        $this->repository->method('getQuery')->willReturn($this->query);

        // example_sentence repository backs entry-detail examples (#788); empty
        // by default so the detail tests exercise the dictionary path only.
        // When the consent-gated query yields no ids, examplesFor() must return
        // early WITHOUT calling findMany() — findMany([]) is fail-closed
        // upstream now, but the consent-lock INTENT stands: an empty id set
        // must never widen into the gated corpus (#788 leak class).
        $exampleQuery = $this->createMock(EntityQueryInterface::class);
        $exampleQuery->method('setAccount')->willReturnSelf();
        $exampleQuery->method('condition')->willReturnSelf();
        $exampleQuery->method('range')->willReturnSelf();
        $exampleQuery->method('execute')->willReturn([]);
        $exampleRepository = $this->createMock(EntityRepositoryInterface::class);
        $exampleRepository->method('getQuery')->willReturn($exampleQuery);
        $exampleRepository->expects($this->never())->method('findMany');

        $this->entityTypeManager = $this->createMock(EntityTypeManager::class);
        $this->entityTypeManager->method('getRepository')->willReturnCallback(
            fn (string $type): EntityRepositoryInterface => $type === 'example_sentence' ? $exampleRepository : $this->repository,
        );

        $this->twig = new Environment(new ArrayLoader([
            'pages/language/index.html.twig' => '{{ path }}{% for e in entries|default([]) %}|{{ e.get("word") }}{% endfor %}{% if entry is defined and entry %}|{{ entry.get("word") }}{% endif %}{% for form in inflected_forms|default([]) %}|{{ form }}{% endfor %}',
            'pages/language/show.html.twig' => '{{ path }}{% for e in entries|default([]) %}|{{ e.get("word") }}{% endfor %}{% if entry is defined and entry %}|{{ entry.get("word") }}{% endif %}{% for form in inflected_forms|default([]) %}|{{ form }}{% endfor %}',
            'pages/language/search.html.twig' => '{{ path }}{% for e in entries|default([]) %}|{{ e.get("word") }}{% endfor %}{% if entry is defined and entry %}|{{ entry.get("word") }}{% endif %}{% for form in inflected_forms|default([]) %}|{{ form }}{% endfor %}',
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
        // findMany() returns a list, not the old id-keyed map.
        $this->repository->method('findMany')
            ->with([1, 2])
            ->willReturn([$makwa, $miigwech]);

        $controller = new LanguageController($this->entityTypeManager, $this->twig);
        $response = $controller->list([], [], $this->account, $this->request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('makwa', $response->getContent());
        $this->assertStringContainsString('miigwech', $response->getContent());
    }

    #[Test]
    public function list_returns_200_when_empty(): void
    {
        $this->query->method('execute')->willReturn([]);
        // findMany([]) is fail-closed: an empty page yields no entries.
        $this->repository->method('findMany')->with([])->willReturn([]);

        $controller = new LanguageController($this->entityTypeManager, $this->twig);
        $response = $controller->list([], [], $this->account, $this->request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('/language', $response->getContent());
    }

    #[Test]
    public function list_filters_by_consent_public_via_query(): void
    {
        // Verify the query builder receives the consent_public condition.
        // The mock returns self for all condition() calls; we verify that
        // entries returned by the query (already filtered) are rendered.
        $entry = new DictionaryEntry(['deid' => 1, 'word' => 'aniin', 'slug' => 'aniin', 'consent_public' => 1]);

        $this->query->method('execute')->willReturn([1]);
        $this->repository->method('findMany')
            ->with([1])
            ->willReturn([$entry]);

        $controller = new LanguageController($this->entityTypeManager, $this->twig);
        $response = $controller->list([], [], $this->account, $this->request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('aniin', $response->getContent());
    }

    #[Test]
    public function show_returns_200_for_existing_entry(): void
    {
        $entry = new DictionaryEntry([
            'deid' => 1,
            'word' => 'makwa',
            'slug' => 'makwa',
            'consent_public' => 1,
            'inflected_forms' => json_encode([
                ['form' => 'makwag', 'label' => 'plural'],
                ['form' => 'makoons', 'label' => 'diminutive'],
            ], JSON_THROW_ON_ERROR),
        ]);

        $this->query->method('execute')->willReturn([1]);
        // find() is string-typed on the repository contract; the controller casts.
        $this->repository->method('find')
            ->with('1')
            ->willReturn($entry);

        $controller = new LanguageController($this->entityTypeManager, $this->twig);
        $response = $controller->show(['slug' => 'makwa'], [], $this->account, $this->request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('makwa', $response->getContent());
        $this->assertStringContainsString('plural: makwag', $response->getContent());
        $this->assertStringContainsString('diminutive: makoons', $response->getContent());
    }

    #[Test]
    public function show_returns_404_for_missing_entry(): void
    {
        $this->query->method('execute')->willReturn([]);

        $controller = new LanguageController($this->entityTypeManager, $this->twig);
        $response = $controller->show(['slug' => 'nonexistent'], [], $this->account, $this->request);

        $this->assertSame(404, $response->getStatusCode());
    }

    #[Test]
    public function show_renders_plain_string_inflected_forms_when_payload_is_not_json(): void
    {
        $entry = new DictionaryEntry([
            'deid' => 1,
            'word' => 'makwa',
            'slug' => 'makwa',
            'consent_public' => 1,
            'inflected_forms' => 'makwag pl; makoons dim',
        ]);

        $this->query->method('execute')->willReturn([1]);
        $this->repository->method('find')
            ->with('1')
            ->willReturn($entry);

        $controller = new LanguageController($this->entityTypeManager, $this->twig);
        $response = $controller->show(['slug' => 'makwa'], [], $this->account, $this->request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('makwag pl; makoons dim', $response->getContent());
    }

    #[Test]
    public function show_filters_by_consent_public_via_query(): void
    {
        // An entry without consent_public would not be returned by the query,
        // so an empty result set gives a 404.
        $this->query->method('execute')->willReturn([]);

        $controller = new LanguageController($this->entityTypeManager, $this->twig);
        $response = $controller->show(['slug' => 'secret-word'], [], $this->account, $this->request);

        $this->assertSame(404, $response->getStatusCode());
    }
}
