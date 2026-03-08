<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Geo;

use Minoo\Entity\Community;
use Minoo\Entity\ElderSupportRequest;
use Minoo\Entity\Volunteer;
use Minoo\Geo\RankedVolunteer;
use Minoo\Geo\VolunteerRanker;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Entity\Storage\EntityStorageInterface;

#[CoversClass(VolunteerRanker::class)]
#[CoversClass(RankedVolunteer::class)]
final class VolunteerRankerTest extends TestCase
{
    private EntityTypeManager $entityTypeManager;
    private EntityStorageInterface $communityStorage;

    protected function setUp(): void
    {
        $this->communityStorage = $this->createMock(EntityStorageInterface::class);

        $this->entityTypeManager = $this->createMock(EntityTypeManager::class);
        $this->entityTypeManager->method('getStorage')->willReturnCallback(
            fn (string $type) => match ($type) {
                'community' => $this->communityStorage,
                default => throw new \RuntimeException("Unexpected: $type"),
            },
        );
    }

    #[Test]
    public function volunteers_sorted_by_distance_nearest_first(): void
    {
        $sagamok = new Community(['cid' => 1, 'name' => 'Sagamok', 'latitude' => 46.15, 'longitude' => -81.72]);
        $sudbury = new Community(['cid' => 2, 'name' => 'Sudbury', 'latitude' => 46.49, 'longitude' => -80.99]);
        $ssm = new Community(['cid' => 3, 'name' => 'Sault Ste. Marie', 'latitude' => 46.52, 'longitude' => -84.35]);

        $this->communityStorage->method('load')->willReturnCallback(
            fn (int $id) => match ($id) {
                1 => $sagamok,
                2 => $sudbury,
                3 => $ssm,
                default => null,
            },
        );

        $request = new ElderSupportRequest(['esrid' => 1, 'name' => 'Elder', 'community' => 1]);

        $volFar = new Volunteer(['vid' => 1, 'name' => 'Far Vol', 'community' => 3]);
        $volNear = new Volunteer(['vid' => 2, 'name' => 'Near Vol', 'community' => 2]);
        $volSame = new Volunteer(['vid' => 3, 'name' => 'Same Vol', 'community' => 1]);

        $ranker = new VolunteerRanker($this->entityTypeManager);
        $ranked = $ranker->rank([$volFar, $volNear, $volSame], $request);

        $this->assertCount(3, $ranked);
        $this->assertSame('Same Vol', $ranked[0]->volunteer->get('name'));
        $this->assertSame('Near Vol', $ranked[1]->volunteer->get('name'));
        $this->assertSame('Far Vol', $ranked[2]->volunteer->get('name'));

        $this->assertEqualsWithDelta(0.0, $ranked[0]->distanceKm, 0.01);
        $this->assertEqualsWithDelta(68.0, $ranked[1]->distanceKm, 5.0);
        $this->assertEqualsWithDelta(206.0, $ranked[2]->distanceKm, 10.0);

        // No max_travel_km set — exceedsMaxTravel should be false
        $this->assertFalse($ranked[0]->exceedsMaxTravel);
        $this->assertFalse($ranked[1]->exceedsMaxTravel);
        $this->assertFalse($ranked[2]->exceedsMaxTravel);
    }

    #[Test]
    public function volunteers_without_coords_sorted_by_name_at_end(): void
    {
        $sagamok = new Community(['cid' => 1, 'name' => 'Sagamok', 'latitude' => 46.15, 'longitude' => -81.72]);
        $sudbury = new Community(['cid' => 2, 'name' => 'Sudbury', 'latitude' => 46.49, 'longitude' => -80.99]);

        $this->communityStorage->method('load')->willReturnCallback(
            fn (int $id) => match ($id) {
                1 => $sagamok,
                2 => $sudbury,
                default => null,
            },
        );

        $request = new ElderSupportRequest(['esrid' => 1, 'name' => 'Elder', 'community' => 1]);

        $volWithCoords = new Volunteer(['vid' => 1, 'name' => 'Zara', 'community' => 2]);
        $volNoCommunity = new Volunteer(['vid' => 2, 'name' => 'Alice']);
        $volNoRef = new Volunteer(['vid' => 3, 'name' => 'Bob']);

        $ranker = new VolunteerRanker($this->entityTypeManager);
        $ranked = $ranker->rank([$volNoCommunity, $volWithCoords, $volNoRef], $request);

        $this->assertCount(3, $ranked);
        $this->assertSame('Zara', $ranked[0]->volunteer->get('name'));
        $this->assertTrue($ranked[0]->hasDistance());

        $this->assertSame('Alice', $ranked[1]->volunteer->get('name'));
        $this->assertFalse($ranked[1]->hasDistance());

        $this->assertSame('Bob', $ranked[2]->volunteer->get('name'));
        $this->assertFalse($ranked[2]->hasDistance());
    }

    #[Test]
    public function request_without_community_puts_all_in_no_distance(): void
    {
        $this->communityStorage->method('load')->willReturn(null);

        $request = new ElderSupportRequest(['esrid' => 1, 'name' => 'Elder']);
        $vol = new Volunteer(['vid' => 1, 'name' => 'Helper', 'community' => 1]);

        $ranker = new VolunteerRanker($this->entityTypeManager);
        $ranked = $ranker->rank([$vol], $request);

        $this->assertCount(1, $ranked);
        $this->assertFalse($ranked[0]->hasDistance());
    }

    #[Test]
    public function exceeds_max_travel_flagged_when_beyond_limit(): void
    {
        $sagamok = new Community(['cid' => 1, 'name' => 'Sagamok', 'latitude' => 46.15, 'longitude' => -81.72]);
        $sudbury = new Community(['cid' => 2, 'name' => 'Sudbury', 'latitude' => 46.49, 'longitude' => -80.99]);
        $ssm = new Community(['cid' => 3, 'name' => 'Sault Ste. Marie', 'latitude' => 46.52, 'longitude' => -84.35]);

        $this->communityStorage->method('load')->willReturnCallback(
            fn (int $id) => match ($id) {
                1 => $sagamok,
                2 => $sudbury,
                3 => $ssm,
                default => null,
            },
        );

        $request = new ElderSupportRequest(['esrid' => 1, 'name' => 'Elder', 'community' => 1]);

        // 50 km limit — Sudbury (~68 km) exceeds, Sagamok (same) does not
        $volNear = new Volunteer(['vid' => 1, 'name' => 'Near', 'community' => 2, 'max_travel_km' => 100]);
        $volFar = new Volunteer(['vid' => 2, 'name' => 'Far', 'community' => 3, 'max_travel_km' => 50]);
        $volSame = new Volunteer(['vid' => 3, 'name' => 'Same', 'community' => 1, 'max_travel_km' => 10]);

        $ranker = new VolunteerRanker($this->entityTypeManager);
        $ranked = $ranker->rank([$volNear, $volFar, $volSame], $request);

        // Same community (0 km, limit 10) — does not exceed
        $this->assertSame('Same', $ranked[0]->volunteer->get('name'));
        $this->assertFalse($ranked[0]->exceedsMaxTravel);

        // Sudbury (~68 km, limit 100) — does not exceed
        $this->assertSame('Near', $ranked[1]->volunteer->get('name'));
        $this->assertFalse($ranked[1]->exceedsMaxTravel);

        // SSM (~206 km, limit 50) — exceeds
        $this->assertSame('Far', $ranked[2]->volunteer->get('name'));
        $this->assertTrue($ranked[2]->exceedsMaxTravel);
    }

    #[Test]
    public function formatted_distance_display(): void
    {
        $vol = new Volunteer(['vid' => 1, 'name' => 'Test']);

        $near = new RankedVolunteer($vol, 0.5);
        $this->assertSame('< 1 km', $near->formattedDistance());

        $mid = new RankedVolunteer($vol, 68.3);
        $this->assertSame('68 km', $mid->formattedDistance());

        $none = new RankedVolunteer($vol, null);
        $this->assertSame('', $none->formattedDistance());
    }
}
