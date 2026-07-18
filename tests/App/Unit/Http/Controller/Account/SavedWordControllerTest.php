<?php

declare(strict_types=1);

namespace App\Tests\Unit\Http\Controller\Account;

use App\Http\Controller\Account\SavedWordController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\ContentEntityBase;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Entity\Repository\EntityRepositoryInterface;
use Waaseyaa\Entity\Storage\EntityQueryInterface;

#[CoversClass(SavedWordController::class)]
final class SavedWordControllerTest extends TestCase
{
    private Environment $twig;

    protected function setUp(): void
    {
        $this->twig = new Environment(new ArrayLoader([
            'pages/account/words.html.twig' => 'words:{{ words|length }}',
        ]));
    }

    private function account(): AccountInterface
    {
        $account = $this->createMock(AccountInterface::class);
        $account->method('id')->willReturn(5);
        $account->method('isAuthenticated')->willReturn(true);
        return $account;
    }

    private function savedWordQuery(array $executeResult): EntityQueryInterface
    {
        $query = $this->createMock(EntityQueryInterface::class);
        $query->method('setAccount')->willReturnSelf();
        $query->method('condition')->willReturnSelf();
        $query->method('range')->willReturnSelf();
        $query->method('sort')->willReturnSelf();
        $query->method('execute')->willReturn($executeResult);
        return $query;
    }

    #[Test]
    public function toggle_saves_when_not_already_saved(): void
    {
        $savedRepository = $this->createMock(EntityRepositoryInterface::class);
        $savedRepository->method('getQuery')->willReturn($this->savedWordQuery([]));
        $savedRepository->expects($this->once())->method('create')->willReturn($this->createMock(ContentEntityBase::class));
        $savedRepository->expects($this->once())->method('save');
        $savedRepository->expects($this->never())->method('delete');

        $entry = $this->createMock(ContentEntityBase::class);
        $entry->method('get')->willReturnCallback(static fn (string $f) => match ($f) {
            'word' => 'makwa', 'definition' => '["a bear"]', 'slug' => 'makwa', default => null,
        });
        $dictRepository = $this->createMock(EntityRepositoryInterface::class);
        $dictRepository->method('find')->willReturn($entry);

        $etm = $this->createMock(EntityTypeManager::class);
        $etm->method('getRepository')->willReturnCallback(
            fn (string $type): EntityRepositoryInterface => $type === 'dictionary_entry' ? $dictRepository : $savedRepository,
        );

        $controller = new SavedWordController($this->twig, $etm);
        $request = HttpRequest::create('/account/words/toggle', 'POST', ['dictionary_entry_id' => 12737, 'slug' => 'makwa']);
        $response = $controller->toggle([], [], $this->account(), $request);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('/language/makwa', $response->getTargetUrl());
    }

    #[Test]
    public function toggle_unsaves_when_already_saved(): void
    {
        $existing = $this->createMock(ContentEntityBase::class);
        $savedRepository = $this->createMock(EntityRepositoryInterface::class);
        $savedRepository->method('getQuery')->willReturn($this->savedWordQuery([42]));
        // find() is string-typed on the repository contract; the controller casts.
        $savedRepository->method('find')->with('42')->willReturn($existing);
        // delete() takes the single entity now — no array wrapper (C-22 shape lock).
        $savedRepository->expects($this->once())->method('delete')->with($existing);
        $savedRepository->expects($this->never())->method('create');

        $etm = $this->createMock(EntityTypeManager::class);
        $etm->method('getRepository')->willReturn($savedRepository);

        $controller = new SavedWordController($this->twig, $etm);
        $request = HttpRequest::create('/account/words/toggle', 'POST', ['dictionary_entry_id' => 12737, 'slug' => 'makwa']);
        $response = $controller->toggle([], [], $this->account(), $request);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('/language/makwa', $response->getTargetUrl());
    }

    #[Test]
    public function list_renders_the_members_words(): void
    {
        $savedRepository = $this->createMock(EntityRepositoryInterface::class);
        $savedRepository->method('getQuery')->willReturn($this->savedWordQuery([]));

        $etm = $this->createMock(EntityTypeManager::class);
        $etm->method('getRepository')->willReturn($savedRepository);

        $controller = new SavedWordController($this->twig, $etm);
        $response = $controller->list([], [], $this->account(), HttpRequest::create('/account/words'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('words:0', $response->getContent());
    }
}
