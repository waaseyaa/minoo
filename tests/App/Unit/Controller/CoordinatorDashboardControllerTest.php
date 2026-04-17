<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller;

use App\Controller\CoordinatorDashboardController;
use App\Entity\Community;
use App\Entity\ElderSupportRequest;
use App\Entity\Volunteer;
use App\Domain\Geo\Service\VolunteerRanker;
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

#[CoversClass(CoordinatorDashboardController::class)]
final class CoordinatorDashboardControllerTest extends TestCase
{
    private EntityTypeManager $entityTypeManager;
    private Environment $twig;
    private EntityStorageInterface $requestStorage;
    private EntityStorageInterface $volunteerStorage;
    private EntityStorageInterface $communityStorage;
    private EntityQueryInterface $requestQuery;
    private EntityQueryInterface $volunteerQuery;
    private AccountInterface $account;
    private HttpRequest $request;

    protected function setUp(): void
    {
        $this->requestQuery = $this->createMock(EntityQueryInterface::class);
        $this->requestQuery->method('condition')->willReturnSelf();
        $this->requestQuery->method('sort')->willReturnSelf();

        $this->volunteerQuery = $this->createMock(EntityQueryInterface::class);
        $this->volunteerQuery->method('condition')->willReturnSelf();
        $this->volunteerQuery->method('sort')->willReturnSelf();

        $this->requestStorage = $this->createMock(EntityStorageInterface::class);
        $this->requestStorage->method('getQuery')->willReturn($this->requestQuery);

        $this->volunteerStorage = $this->createMock(EntityStorageInterface::class);
        $this->volunteerStorage->method('getQuery')->willReturn($this->volunteerQuery);

        $communityQuery = $this->createMock(EntityQueryInterface::class);
        $communityQuery->method('condition')->willReturnSelf();
        $communityQuery->method('sort')->willReturnSelf();

        $this->communityStorage = $this->createMock(EntityStorageInterface::class);
        $this->communityStorage->method('getQuery')->willReturn($communityQuery);
        $this->communityStorage->method('loadMultiple')->willReturn([]);

        $this->entityTypeManager = $this->createMock(EntityTypeManager::class);
        $this->entityTypeManager->method('getStorage')->willReturnCallback(
            fn (string $type) => match ($type) {
                'elder_support_request' => $this->requestStorage,
                'volunteer' => $this->volunteerStorage,
                'community' => $this->communityStorage,
                default => throw new \RuntimeException("Unexpected: $type"),
            },
        );

        $this->twig = new Environment(new ArrayLoader([
            'pages/dashboard/coordinator.html.twig' => 'pending:{{ pending_application_count }}{% for r in open_requests %}|{{ r.get("name") }}{% endfor %}{% for rv in ranked_by_request[open_requests[0].id()] ?? [] %}|{{ rv.volunteer.get("name") }}{% if rv.hasDistance() %}:{{ rv.formattedDistance() }}{% endif %}{% endfor %}',
        ]));

        $this->account = $this->createMock(AccountInterface::class);
        $this->account->method('getRoles')->willReturn(['elder_coordinator']);

        $this->request = HttpRequest::create('/');
    }

    #[Test]
    public function index_returns_200_with_requests_and_volunteers(): void
    {
        $req = new ElderSupportRequest(['esrid' => 1, 'name' => 'Elder Mary', 'phone' => '555', 'type' => 'ride', 'status' => 'open']);
        $vol = new Volunteer(['vid' => 1, 'name' => 'John Helper', 'status' => 'active']);

        $this->requestQuery->method('execute')->willReturn([1]);
        $this->requestStorage->method('loadMultiple')
            ->with([1])
            ->willReturn([1 => $req]);

        $this->volunteerQuery->method('execute')->willReturn([1]);
        $this->volunteerStorage->method('loadMultiple')
            ->with([1])
            ->willReturn([1 => $vol]);

        $controller = new CoordinatorDashboardController($this->entityTypeManager, $this->twig);
        $response = $controller->index([], [], $this->account, $this->request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('Elder Mary', $response->getContent());
        $this->assertStringContainsString('John Helper', $response->getContent());
        // pending_application_count should be an integer, not empty
        $this->assertMatchesRegularExpression('/pending:\d+/', $response->getContent());
    }

    #[Test]
    public function index_includes_ranked_volunteers_with_distance(): void
    {
        $sagamok = new Community(['cid' => 1, 'name' => 'Sagamok', 'latitude' => 46.15, 'longitude' => -81.72]);
        $sudbury = new Community(['cid' => 2, 'name' => 'Sudbury', 'latitude' => 46.49, 'longitude' => -80.99]);

        $this->communityStorage = $this->createMock(EntityStorageInterface::class);
        $communityQuery2 = $this->createMock(EntityQueryInterface::class);
        $communityQuery2->method('condition')->willReturnSelf();
        $communityQuery2->method('sort')->willReturnSelf();
        $this->communityStorage->method('getQuery')->willReturn($communityQuery2);

        $this->communityStorage->method('load')->willReturnCallback(
            fn (int $id) => match ($id) {
                1 => $sagamok,
                2 => $sudbury,
                default => null,
            },
        );
        $this->communityStorage->method('loadMultiple')->willReturnCallback(
            fn (array $ids) => array_filter(
                [1 => $sagamok, 2 => $sudbury],
                fn ($key) => in_array($key, $ids, true),
                \ARRAY_FILTER_USE_KEY,
            ),
        );

        $this->entityTypeManager = $this->createMock(EntityTypeManager::class);
        $this->entityTypeManager->method('getStorage')->willReturnCallback(
            fn (string $type) => match ($type) {
                'elder_support_request' => $this->requestStorage,
                'volunteer' => $this->volunteerStorage,
                'community' => $this->communityStorage,
                default => throw new \RuntimeException("Unexpected: $type"),
            },
        );

        $req = new ElderSupportRequest(['esrid' => 10, 'name' => 'Elder Mary', 'phone' => '555', 'type' => 'ride', 'status' => 'open', 'community' => 1]);

        $volNear = new Volunteer(['vid' => 1, 'name' => 'Near Vol', 'status' => 'active', 'community' => 2]);
        $volSame = new Volunteer(['vid' => 2, 'name' => 'Same Vol', 'status' => 'active', 'community' => 1]);

        $this->requestQuery->method('execute')->willReturn([10]);
        $this->requestStorage->method('loadMultiple')
            ->with([10])
            ->willReturn([10 => $req]);

        $this->volunteerQuery->method('execute')->willReturn([1, 2]);
        $this->volunteerStorage->method('loadMultiple')
            ->with([1, 2])
            ->willReturn([1 => $volNear, 2 => $volSame]);

        $this->twig = new Environment(new ArrayLoader([
            'pages/dashboard/coordinator.html.twig' => '{% for rv in ranked_by_request[10] %}{{ rv.volunteer.get("name") }}:{{ rv.formattedDistance()|raw }};{% endfor %}{% for id, name in community_names %}[{{ id }}={{ name }}]{% endfor %}',
        ]));

        $controller = new CoordinatorDashboardController($this->entityTypeManager, $this->twig);
        $response = $controller->index([], [], $this->account, $this->request);

        $this->assertSame(200, $response->getStatusCode());
        // Same community first (< 1 km), then near volunteer (~68 km)
        $this->assertStringContainsString('Same Vol:< 1 km;', $response->getContent());
        $this->assertStringContainsString('Near Vol:68 km;', $response->getContent());

        // Same Vol should appear before Near Vol
        $samePos = strpos($response->getContent(), 'Same Vol');
        $nearPos = strpos($response->getContent(), 'Near Vol');
        $this->assertLessThan($nearPos, $samePos);

        // Community names are resolved from IDs
        $this->assertStringContainsString('[1=Sagamok]', $response->getContent());
        $this->assertStringContainsString('[2=Sudbury]', $response->getContent());
    }
}
