<?php

declare(strict_types=1);

namespace App\Tests\Unit\Http\Controller\ElderSupport;

use App\Http\Controller\ElderSupport\ElderSupportController;
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
use Waaseyaa\Entity\Storage\EntityQueryInterface;
use Waaseyaa\Entity\Storage\EntityStorageInterface;

#[CoversClass(ElderSupportController::class)]
final class ElderSupportControllerTest extends TestCase
{
    private Environment $twig;

    protected function setUp(): void
    {
        $this->twig = new Environment(new ArrayLoader([
            'pages/elder-support/form.html.twig' => 'form:{{ communities|length }}:{{ can_triage ? "triage" : "no" }}',
            'pages/elder-support/inbox.html.twig' => 'inbox:{{ requests|length }}',
        ]));
    }

    private function account(bool $admin, array $roles): AccountInterface
    {
        $account = $this->createMock(AccountInterface::class);
        $account->method('hasPermission')->willReturnCallback(
            static fn (string $p): bool => $admin && $p === 'administer content',
        );
        $account->method('getRoles')->willReturn($roles);
        $account->method('isAuthenticated')->willReturn(true);
        return $account;
    }

    #[Test]
    public function form_renders_with_the_seven_communities(): void
    {
        $controller = new ElderSupportController($this->twig, $this->createMock(EntityTypeManager::class));
        $response = $controller->form([], [], $this->account(false, ['authenticated']), HttpRequest::create('/elder-support'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('form:7:', $response->getContent());
    }

    #[Test]
    public function submit_rejects_empty_fields_without_creating(): void
    {
        $storage = $this->createMock(EntityStorageInterface::class);
        $storage->expects($this->never())->method('create');
        $etm = $this->createMock(EntityTypeManager::class);
        $etm->method('getStorage')->willReturn($storage);

        $controller = new ElderSupportController($this->twig, $etm);
        $request = HttpRequest::create('/elder-support', 'POST', ['name' => '', 'message' => '']);
        $response = $controller->submit([], [], $this->account(false, ['authenticated']), $request);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('/elder-support', $response->getTargetUrl());
    }

    #[Test]
    public function submit_creates_and_saves_a_valid_request(): void
    {
        $entity = $this->createMock(ContentEntityBase::class);
        $storage = $this->createMock(EntityStorageInterface::class);
        $storage->expects($this->once())->method('create')->willReturn($entity);
        $storage->expects($this->once())->method('save');
        $etm = $this->createMock(EntityTypeManager::class);
        $etm->method('getStorage')->willReturn($storage);

        $controller = new ElderSupportController($this->twig, $etm);
        $request = HttpRequest::create('/elder-support', 'POST', [
            'name' => 'Test Person',
            'message' => 'Needs a ride to the clinic',
            'community' => 'sagamok-anishnawbek',
        ]);
        $response = $controller->submit([], [], $this->account(false, ['authenticated']), $request);

        $this->assertInstanceOf(RedirectResponse::class, $response);
    }

    #[Test]
    public function inbox_is_forbidden_for_non_coordinators(): void
    {
        $controller = new ElderSupportController($this->twig, $this->createMock(EntityTypeManager::class));
        $response = $controller->inbox([], [], $this->account(false, ['authenticated']), HttpRequest::create('/elder-support/requests'));

        $this->assertSame(403, $response->getStatusCode());
    }

    #[Test]
    public function inbox_renders_for_coordinators(): void
    {
        $query = $this->createMock(EntityQueryInterface::class);
        $query->method('setAccount')->willReturnSelf();
        $query->method('sort')->willReturnSelf();
        $query->method('range')->willReturnSelf();
        $query->method('execute')->willReturn([]);
        $storage = $this->createMock(EntityStorageInterface::class);
        $storage->method('getQuery')->willReturn($query);
        $etm = $this->createMock(EntityTypeManager::class);
        $etm->method('getStorage')->willReturn($storage);

        $controller = new ElderSupportController($this->twig, $etm);
        $response = $controller->inbox([], [], $this->account(false, ['authenticated', 'elder_coordinator']), HttpRequest::create('/elder-support/requests'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('inbox:0', $response->getContent());
    }
}
