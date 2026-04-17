<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller;

use App\Controller\ElderSupportController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\EntityInterface;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Entity\Storage\EntityQueryInterface;
use Waaseyaa\Entity\Storage\EntityStorageInterface;

#[CoversClass(ElderSupportController::class)]
final class ElderSupportControllerTest extends TestCase
{
    private EntityTypeManager $entityTypeManager;
    private Environment $twig;
    private AccountInterface $account;

    protected function setUp(): void
    {
        $this->entityTypeManager = $this->createMock(EntityTypeManager::class);

        $this->twig = new Environment(new ArrayLoader([
            'pages/elders/request.html.twig' => '{{ errors|keys|join(",") }}',
            'pages/elders/request-confirmation.html.twig' => 'ok',
        ]));

        $this->account = $this->createMock(AccountInterface::class);
    }

    #[Test]
    public function request_form_returns_200(): void
    {
        $controller = new ElderSupportController($this->entityTypeManager, $this->twig);
        $response = $controller->requestForm([], [], $this->account, HttpRequest::create('/'));

        $this->assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function submit_with_empty_fields_returns_422(): void
    {
        $controller = new ElderSupportController($this->entityTypeManager, $this->twig);
        $request = HttpRequest::create('/elders/request', 'POST');

        $response = $controller->submitRequest([], [], $this->account, $request);

        $this->assertSame(422, $response->getStatusCode());
    }

    #[Test]
    public function submit_with_missing_name_returns_422(): void
    {
        $controller = new ElderSupportController($this->entityTypeManager, $this->twig);
        $request = HttpRequest::create('/elders/request', 'POST', [
            'phone' => '555-1234',
            'type' => 'ride',
        ]);

        $response = $controller->submitRequest([], [], $this->account, $request);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertStringContainsString('name', $response->getContent());
    }

    #[Test]
    public function submit_with_invalid_type_returns_422(): void
    {
        $controller = new ElderSupportController($this->entityTypeManager, $this->twig);
        $request = HttpRequest::create('/elders/request', 'POST', [
            'name' => 'Mary',
            'phone' => '555-1234',
            'type' => 'invalid',
        ]);

        $response = $controller->submitRequest([], [], $this->account, $request);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertStringContainsString('type', $response->getContent());
    }

    #[Test]
    public function request_detail_with_valid_uuid_returns_200(): void
    {
        $uuid = '550e8400-e29b-41d4-a716-446655440000';

        $entity = $this->createMock(EntityInterface::class);

        $query = $this->createMock(EntityQueryInterface::class);
        $query->method('condition')->willReturnSelf();
        $query->method('execute')->willReturn([1]);

        $storage = $this->createMock(EntityStorageInterface::class);
        $storage->method('getQuery')->willReturn($query);
        $storage->method('load')->with(1)->willReturn($entity);

        $this->entityTypeManager->method('getStorage')
            ->with('elder_support_request')
            ->willReturn($storage);

        $controller = new ElderSupportController($this->entityTypeManager, $this->twig);
        $response = $controller->requestDetail(['uuid' => $uuid], [], $this->account, HttpRequest::create('/'));

        $this->assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function request_detail_with_unknown_uuid_returns_404(): void
    {
        $query = $this->createMock(EntityQueryInterface::class);
        $query->method('condition')->willReturnSelf();
        $query->method('execute')->willReturn([]);

        $storage = $this->createMock(EntityStorageInterface::class);
        $storage->method('getQuery')->willReturn($query);

        $this->entityTypeManager->method('getStorage')
            ->with('elder_support_request')
            ->willReturn($storage);

        $controller = new ElderSupportController($this->entityTypeManager, $this->twig);
        $response = $controller->requestDetail(['uuid' => 'nonexistent'], [], $this->account, HttpRequest::create('/'));

        $this->assertSame(404, $response->getStatusCode());
    }

    #[Test]
    public function request_detail_with_empty_uuid_returns_404(): void
    {
        $controller = new ElderSupportController($this->entityTypeManager, $this->twig);
        $response = $controller->requestDetail([], [], $this->account, HttpRequest::create('/'));

        $this->assertSame(404, $response->getStatusCode());
    }

    #[Test]
    public function representative_without_consent_returns_422(): void
    {
        $controller = new ElderSupportController($this->entityTypeManager, $this->twig);
        $request = HttpRequest::create('/elders/request', 'POST', [
            'name' => 'Jane',
            'phone' => '555-1234',
            'type' => 'ride',
            'is_representative' => '1',
            'elder_name' => 'Mary',
            'consent' => '',
        ]);

        $response = $controller->submitRequest([], [], $this->account, $request);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertStringContainsString('consent', $response->getContent());
    }

    #[Test]
    public function representative_without_elder_name_returns_422(): void
    {
        $controller = new ElderSupportController($this->entityTypeManager, $this->twig);
        $request = HttpRequest::create('/elders/request', 'POST', [
            'name' => 'Jane',
            'phone' => '555-1234',
            'type' => 'ride',
            'is_representative' => '1',
            'elder_name' => '',
            'consent' => '1',
        ]);

        $response = $controller->submitRequest([], [], $this->account, $request);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertStringContainsString('elder_name', $response->getContent());
    }
}
