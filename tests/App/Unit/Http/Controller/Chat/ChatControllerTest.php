<?php

declare(strict_types=1);

namespace App\Tests\Unit\Http\Controller\Chat;

use App\Entity\Language\ExampleSentence;
use App\Http\Controller\Chat\ChatController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Entity\Storage\EntityQueryInterface;
use Waaseyaa\Entity\Storage\EntityStorageInterface;

/**
 * Locks the cite-only chat consent gate (#822): the example_sentence query
 * MUST filter consent_public = 1 AND status = 1, and MUST NOT call
 * loadMultiple() on an empty result set. The corpus rows are consent_public = 0
 * until Phase 4; if either guard regressed, those gated sentences would surface
 * on every chat answer (the #788 leak class).
 */
#[CoversClass(ChatController::class)]
final class ChatControllerTest extends TestCase
{
    private Environment $twig;
    private AccountInterface $account;

    protected function setUp(): void
    {
        // Stub renders dictionary entries (E:) and example sentences (S:) so the
        // tests can assert what actually reaches the page.
        $this->twig = new Environment(new ArrayLoader([
            'pages/chat/index.html.twig' =>
                '{{ query }}'
                . '{% for e in entries|default([]) %}|E:{{ e.word }}{% endfor %}'
                . '{% for s in examples|default([]) %}|S:{{ s.ojibwe_text }}{% endfor %}',
        ]));
        $this->account = $this->createMock(AccountInterface::class);
    }

    /** A dictionary query that yields nothing, so tests isolate the example path. */
    private function emptyDictionaryStorage(): EntityStorageInterface
    {
        $query = $this->createMock(EntityQueryInterface::class);
        $query->method('setAccount')->willReturnSelf();
        $query->method('condition')->willReturnSelf();
        $query->method('execute')->willReturn([]);

        $storage = $this->createMock(EntityStorageInterface::class);
        $storage->method('getQuery')->willReturn($query);
        // No candidate ids -> retrieveEntries() must never load the 21k dictionary.
        $storage->expects($this->never())->method('loadMultiple');

        return $storage;
    }

    #[Test]
    public function example_query_is_consent_gated_and_never_loads_all(): void
    {
        $conditions = [];
        $exampleQuery = $this->createMock(EntityQueryInterface::class);
        $exampleQuery->method('setAccount')->willReturnSelf();
        $exampleQuery->method('range')->willReturnSelf();
        $exampleQuery->method('condition')->willReturnCallback(
            function (string $field, mixed $value) use (&$conditions, $exampleQuery): EntityQueryInterface {
                $conditions[] = [$field, $value];
                return $exampleQuery;
            },
        );
        // No matching sentences. loadMultiple([]) is the framework "load all"
        // sentinel; calling it here would dump every consent-gated corpus row.
        $exampleQuery->method('execute')->willReturn([]);

        $exampleStorage = $this->createMock(EntityStorageInterface::class);
        $exampleStorage->method('getQuery')->willReturn($exampleQuery);
        $exampleStorage->expects($this->never())->method('loadMultiple');

        $dictionaryStorage = $this->emptyDictionaryStorage();

        $etm = $this->createMock(EntityTypeManager::class);
        $etm->method('hasDefinition')->willReturn(true);
        $etm->method('getStorage')->willReturnCallback(
            fn (string $type): EntityStorageInterface => $type === 'example_sentence' ? $exampleStorage : $dictionaryStorage,
        );

        $controller = new ChatController($etm, $this->twig);
        $response = $controller->index([], ['q' => 'aaniin'], $this->account, HttpRequest::create('/chat'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertContains(['status', 1], $conditions, 'example_sentence query must filter status = 1');
        $this->assertContains(['consent_public', 1], $conditions, 'example_sentence query must filter consent_public = 1');
    }

    #[Test]
    public function consent_public_example_surfaces_when_matched(): void
    {
        $sentence = new ExampleSentence([
            'esid' => 5,
            'ojibwe_text' => 'Aaniin ezhi-ayaayan?',
            'english_text' => 'How are you?',
            'consent_public' => 1,
            'status' => 1,
        ]);

        $exampleQuery = $this->createMock(EntityQueryInterface::class);
        $exampleQuery->method('setAccount')->willReturnSelf();
        $exampleQuery->method('condition')->willReturnSelf();
        $exampleQuery->method('range')->willReturnSelf();
        $exampleQuery->method('execute')->willReturn([5]);

        $exampleStorage = $this->createMock(EntityStorageInterface::class);
        $exampleStorage->method('getQuery')->willReturn($exampleQuery);
        $exampleStorage->method('loadMultiple')->with([5])->willReturn([5 => $sentence]);

        $dictionaryStorage = $this->emptyDictionaryStorage();

        $etm = $this->createMock(EntityTypeManager::class);
        $etm->method('hasDefinition')->willReturn(true);
        $etm->method('getStorage')->willReturnCallback(
            fn (string $type): EntityStorageInterface => $type === 'example_sentence' ? $exampleStorage : $dictionaryStorage,
        );

        $controller = new ChatController($etm, $this->twig);
        $response = $controller->index([], ['q' => 'aaniin'], $this->account, HttpRequest::create('/chat'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('S:Aaniin ezhi-ayaayan?', $response->getContent());
    }

    #[Test]
    public function empty_query_does_not_touch_storage(): void
    {
        $etm = $this->createMock(EntityTypeManager::class);
        $etm->expects($this->never())->method('getStorage');

        $controller = new ChatController($etm, $this->twig);
        $response = $controller->index([], ['q' => '  '], $this->account, HttpRequest::create('/chat'));

        $this->assertSame(200, $response->getStatusCode());
    }
}
