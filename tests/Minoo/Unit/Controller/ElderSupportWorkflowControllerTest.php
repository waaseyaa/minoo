<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Controller;

use Minoo\Controller\ElderSupportWorkflowController;
use Minoo\Entity\ElderSupportRequest;
use Minoo\Entity\Volunteer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Entity\Storage\EntityQueryInterface;
use Waaseyaa\Entity\Storage\EntityStorageInterface;

#[CoversClass(ElderSupportWorkflowController::class)]
final class ElderSupportWorkflowControllerTest extends TestCase
{
    private EntityTypeManager $entityTypeManager;
    private EntityStorageInterface $requestStorage;
    private EntityStorageInterface $volunteerStorage;
    private EntityQueryInterface $query;
    private HttpRequest $request;

    protected function setUp(): void
    {
        $this->query = $this->createMock(EntityQueryInterface::class);
        $this->query->method('condition')->willReturnSelf();

        $this->requestStorage = $this->createMock(EntityStorageInterface::class);
        $this->requestStorage->method('getQuery')->willReturn($this->query);

        $this->volunteerStorage = $this->createMock(EntityStorageInterface::class);

        $this->entityTypeManager = $this->createMock(EntityTypeManager::class);
        $this->entityTypeManager->method('getStorage')->willReturnCallback(
            fn (string $type) => match ($type) {
                'elder_support_request' => $this->requestStorage,
                'volunteer' => $this->volunteerStorage,
                default => throw new \RuntimeException("Unexpected type: $type"),
            },
        );

        $this->request = HttpRequest::create('/');
    }

    #[Test]
    public function assign_sets_status_and_volunteer(): void
    {
        $entity = new ElderSupportRequest(['esrid' => 1, 'name' => 'Mary', 'phone' => '555', 'type' => 'ride', 'status' => 'open']);
        $this->requestStorage->method('load')->with(1)->willReturn($entity);
        $this->requestStorage->expects($this->once())->method('save');

        $volunteer = new Volunteer(['vid' => 5, 'name' => 'John']);
        $this->volunteerStorage->method('load')->with(5)->willReturn($volunteer);

        $account = $this->createCoordinatorAccount();
        $this->request = HttpRequest::create('/elders/request/1/assign', 'POST', ['volunteer_id' => '5']);

        $controller = new ElderSupportWorkflowController($this->entityTypeManager);
        $response = $controller->assignVolunteer(['esrid' => '1'], [], $account, $this->request);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('assigned', $entity->get('status'));
        $this->assertSame(5, $entity->get('assigned_volunteer'));
    }

    #[Test]
    public function start_transitions_assigned_to_in_progress(): void
    {
        $entity = new ElderSupportRequest(['esrid' => 1, 'name' => 'Mary', 'phone' => '555', 'type' => 'ride', 'status' => 'assigned', 'assigned_volunteer' => 5]);
        $this->requestStorage->method('load')->with(1)->willReturn($entity);
        $this->requestStorage->expects($this->once())->method('save');

        $account = $this->createVolunteerAccount(5);

        $controller = new ElderSupportWorkflowController($this->entityTypeManager);
        $response = $controller->startRequest(['esrid' => '1'], [], $account, $this->request);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('in_progress', $entity->get('status'));
    }

    #[Test]
    public function complete_transitions_in_progress_to_completed(): void
    {
        $entity = new ElderSupportRequest(['esrid' => 1, 'name' => 'Mary', 'phone' => '555', 'type' => 'ride', 'status' => 'in_progress', 'assigned_volunteer' => 5]);
        $this->requestStorage->method('load')->with(1)->willReturn($entity);
        $this->requestStorage->expects($this->once())->method('save');

        $account = $this->createVolunteerAccount(5);

        $controller = new ElderSupportWorkflowController($this->entityTypeManager);
        $response = $controller->completeRequest(['esrid' => '1'], [], $account, $this->request);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('completed', $entity->get('status'));
    }

    #[Test]
    public function confirm_transitions_completed_to_confirmed(): void
    {
        $entity = new ElderSupportRequest(['esrid' => 1, 'name' => 'Mary', 'phone' => '555', 'type' => 'ride', 'status' => 'completed']);
        $this->requestStorage->method('load')->with(1)->willReturn($entity);
        $this->requestStorage->expects($this->once())->method('save');

        $account = $this->createCoordinatorAccount();

        $controller = new ElderSupportWorkflowController($this->entityTypeManager);
        $response = $controller->confirmRequest(['esrid' => '1'], [], $account, $this->request);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('confirmed', $entity->get('status'));
    }

    #[Test]
    public function assign_returns_403_for_non_coordinator(): void
    {
        $entity = new ElderSupportRequest(['esrid' => 1, 'name' => 'Mary', 'phone' => '555', 'type' => 'ride', 'status' => 'open']);
        $this->requestStorage->method('load')->with(1)->willReturn($entity);

        $account = $this->createVolunteerAccount(5);
        $this->request = HttpRequest::create('/elders/request/1/assign', 'POST', ['volunteer_id' => '5']);

        $controller = new ElderSupportWorkflowController($this->entityTypeManager);
        $response = $controller->assignVolunteer(['esrid' => '1'], [], $account, $this->request);

        $this->assertSame(403, $response->getStatusCode());
    }

    #[Test]
    public function start_returns_403_for_wrong_volunteer(): void
    {
        $entity = new ElderSupportRequest(['esrid' => 1, 'name' => 'Mary', 'phone' => '555', 'type' => 'ride', 'status' => 'assigned', 'assigned_volunteer' => 5]);
        $this->requestStorage->method('load')->with(1)->willReturn($entity);

        $account = $this->createVolunteerAccount(99);

        $controller = new ElderSupportWorkflowController($this->entityTypeManager);
        $response = $controller->startRequest(['esrid' => '1'], [], $account, $this->request);

        $this->assertSame(403, $response->getStatusCode());
    }

    #[Test]
    public function cancel_transitions_open_to_cancelled(): void
    {
        $entity = new ElderSupportRequest(['esrid' => 1, 'name' => 'Mary', 'phone' => '555', 'type' => 'ride', 'status' => 'open']);
        $this->requestStorage->method('load')->with(1)->willReturn($entity);
        $this->requestStorage->expects($this->once())->method('save');

        $account = $this->createCoordinatorAccount();
        $this->request = HttpRequest::create('/elders/request/1/cancel', 'POST', ['reason' => 'Elder no longer needs help']);

        $controller = new ElderSupportWorkflowController($this->entityTypeManager);
        $response = $controller->cancelRequest(['esrid' => '1'], [], $account, $this->request);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('cancelled', $entity->get('status'));
        $this->assertSame('Elder no longer needs help', $entity->get('cancelled_reason'));
    }

    #[Test]
    public function cancel_transitions_assigned_to_cancelled(): void
    {
        $entity = new ElderSupportRequest(['esrid' => 1, 'name' => 'Mary', 'phone' => '555', 'type' => 'ride', 'status' => 'assigned']);
        $this->requestStorage->method('load')->with(1)->willReturn($entity);
        $this->requestStorage->expects($this->once())->method('save');

        $account = $this->createCoordinatorAccount();
        $this->request = HttpRequest::create('/elders/request/1/cancel', 'POST', ['reason' => 'Duplicate']);

        $controller = new ElderSupportWorkflowController($this->entityTypeManager);
        $response = $controller->cancelRequest(['esrid' => '1'], [], $account, $this->request);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('cancelled', $entity->get('status'));
    }

    #[Test]
    public function cancel_rejects_completed_request(): void
    {
        $entity = new ElderSupportRequest(['esrid' => 1, 'name' => 'Mary', 'phone' => '555', 'type' => 'ride', 'status' => 'completed']);
        $this->requestStorage->method('load')->with(1)->willReturn($entity);

        $account = $this->createCoordinatorAccount();
        $this->request = HttpRequest::create('/elders/request/1/cancel', 'POST', ['reason' => 'test']);

        $controller = new ElderSupportWorkflowController($this->entityTypeManager);
        $response = $controller->cancelRequest(['esrid' => '1'], [], $account, $this->request);

        $this->assertSame(422, $response->getStatusCode());
    }

    #[Test]
    public function cancel_returns_403_for_non_coordinator(): void
    {
        $account = $this->createVolunteerAccount(5);
        $this->request = HttpRequest::create('/elders/request/1/cancel', 'POST', ['reason' => 'test']);

        $controller = new ElderSupportWorkflowController($this->entityTypeManager);
        $response = $controller->cancelRequest(['esrid' => '1'], [], $account, $this->request);

        $this->assertSame(403, $response->getStatusCode());
    }

    #[Test]
    public function decline_clears_volunteer_and_sets_open(): void
    {
        $entity = new ElderSupportRequest(['esrid' => 1, 'name' => 'Mary', 'phone' => '555', 'type' => 'ride', 'status' => 'assigned', 'assigned_volunteer' => 5]);
        $this->requestStorage->method('load')->with(1)->willReturn($entity);
        $this->requestStorage->expects($this->once())->method('save');

        $account = $this->createVolunteerAccount(5);

        $controller = new ElderSupportWorkflowController($this->entityTypeManager);
        $response = $controller->declineRequest(['esrid' => '1'], [], $account, $this->request);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('open', $entity->get('status'));
        $this->assertNull($entity->get('assigned_volunteer'));
        $this->assertNull($entity->get('assigned_at'));
    }

    #[Test]
    public function decline_returns_403_for_wrong_volunteer(): void
    {
        $entity = new ElderSupportRequest(['esrid' => 1, 'name' => 'Mary', 'phone' => '555', 'type' => 'ride', 'status' => 'assigned', 'assigned_volunteer' => 5]);
        $this->requestStorage->method('load')->with(1)->willReturn($entity);

        $account = $this->createVolunteerAccount(99);

        $controller = new ElderSupportWorkflowController($this->entityTypeManager);
        $response = $controller->declineRequest(['esrid' => '1'], [], $account, $this->request);

        $this->assertSame(403, $response->getStatusCode());
    }

    #[Test]
    public function decline_returns_422_for_wrong_status(): void
    {
        $entity = new ElderSupportRequest(['esrid' => 1, 'name' => 'Mary', 'phone' => '555', 'type' => 'ride', 'status' => 'in_progress', 'assigned_volunteer' => 5]);
        $this->requestStorage->method('load')->with(1)->willReturn($entity);

        $account = $this->createVolunteerAccount(5);

        $controller = new ElderSupportWorkflowController($this->entityTypeManager);
        $response = $controller->declineRequest(['esrid' => '1'], [], $account, $this->request);

        $this->assertSame(422, $response->getStatusCode());
    }

    #[Test]
    public function decline_returns_404_for_missing_request(): void
    {
        $this->requestStorage->method('load')->with(99)->willReturn(null);

        $account = $this->createVolunteerAccount(5);

        $controller = new ElderSupportWorkflowController($this->entityTypeManager);
        $response = $controller->declineRequest(['esrid' => '99'], [], $account, $this->request);

        $this->assertSame(404, $response->getStatusCode());
    }

    private function createCoordinatorAccount(): AccountInterface
    {
        return new class implements AccountInterface {
            public function id(): int { return 1; }
            public function hasPermission(string $permission): bool { return true; }
            public function getRoles(): array { return ['elder_coordinator']; }
            public function isAuthenticated(): bool { return true; }
        };
    }

    private function createVolunteerAccount(int $uid): AccountInterface
    {
        return new class($uid) implements AccountInterface {
            public function __construct(private readonly int $uid) {}
            public function id(): int { return $this->uid; }
            public function hasPermission(string $permission): bool
            {
                return in_array($permission, ['view own assignments', 'update assignment status'], true);
            }
            public function getRoles(): array { return ['volunteer']; }
            public function isAuthenticated(): bool { return true; }
        };
    }
}
