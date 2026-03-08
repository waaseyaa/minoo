<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Controller;

use Minoo\Controller\VolunteerController;
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

#[CoversClass(VolunteerController::class)]
final class VolunteerControllerTest extends TestCase
{
    private EntityTypeManager $entityTypeManager;
    private Environment $twig;
    private AccountInterface $account;

    protected function setUp(): void
    {
        $this->entityTypeManager = $this->createMock(EntityTypeManager::class);

        $this->twig = new Environment(new ArrayLoader([
            'elders/volunteer.html.twig' => '{{ errors|keys|join(",") }}',
            'elders/volunteer-confirmation.html.twig' => 'ok',
        ]));

        $this->account = $this->createMock(AccountInterface::class);
    }

    #[Test]
    public function signup_form_returns_200(): void
    {
        $controller = new VolunteerController($this->entityTypeManager, $this->twig);
        $response = $controller->signupForm([], [], $this->account, HttpRequest::create('/'));

        $this->assertSame(200, $response->statusCode);
    }

    #[Test]
    public function submit_with_empty_fields_returns_422(): void
    {
        $controller = new VolunteerController($this->entityTypeManager, $this->twig);
        $request = HttpRequest::create('/elders/volunteer', 'POST');

        $response = $controller->submitSignup([], [], $this->account, $request);

        $this->assertSame(422, $response->statusCode);
    }

    #[Test]
    public function submit_with_missing_phone_returns_422(): void
    {
        $controller = new VolunteerController($this->entityTypeManager, $this->twig);
        $request = HttpRequest::create('/elders/volunteer', 'POST', [
            'name' => 'John',
        ]);

        $response = $controller->submitSignup([], [], $this->account, $request);

        $this->assertSame(422, $response->statusCode);
        $this->assertStringContainsString('phone', $response->content);
    }

    #[Test]
    public function signup_detail_with_valid_uuid_returns_200(): void
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
            ->with('volunteer')
            ->willReturn($storage);

        $controller = new VolunteerController($this->entityTypeManager, $this->twig);
        $response = $controller->signupDetail(['uuid' => $uuid], [], $this->account, HttpRequest::create('/'));

        $this->assertSame(200, $response->statusCode);
    }

    #[Test]
    public function signup_detail_with_unknown_uuid_returns_404(): void
    {
        $query = $this->createMock(EntityQueryInterface::class);
        $query->method('condition')->willReturnSelf();
        $query->method('execute')->willReturn([]);

        $storage = $this->createMock(EntityStorageInterface::class);
        $storage->method('getQuery')->willReturn($query);

        $this->entityTypeManager->method('getStorage')
            ->with('volunteer')
            ->willReturn($storage);

        $controller = new VolunteerController($this->entityTypeManager, $this->twig);
        $response = $controller->signupDetail(['uuid' => 'nonexistent'], [], $this->account, HttpRequest::create('/'));

        $this->assertSame(404, $response->statusCode);
    }

    #[Test]
    public function signup_detail_with_empty_uuid_returns_404(): void
    {
        $controller = new VolunteerController($this->entityTypeManager, $this->twig);
        $response = $controller->signupDetail([], [], $this->account, HttpRequest::create('/'));

        $this->assertSame(404, $response->statusCode);
    }
}
