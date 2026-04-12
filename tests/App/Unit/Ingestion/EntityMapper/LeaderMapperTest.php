<?php

declare(strict_types=1);

namespace App\Tests\Unit\Ingestion\EntityMapper;

use App\Ingestion\EntityMapper\LeaderMapper;
use App\Ingestion\ValueObject\LeaderFields;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(LeaderMapper::class)]
#[CoversClass(LeaderFields::class)]
final class LeaderMapperTest extends TestCase
{
    #[Test]
    public function it_maps_full_leader_data(): void
    {
        $mapper = new LeaderMapper();
        $result = $mapper->map([
            'name' => 'Chief Example',
            'role' => 'Chief',
            'email' => 'chief@example.com',
            'phone' => '705-555-0001',
        ], 'nc-uuid-123');

        $this->assertInstanceOf(LeaderFields::class, $result);
        $this->assertSame('Chief Example', $result->name);
        $this->assertSame('Chief', $result->role);
        $this->assertSame('chief@example.com', $result->email);
        $this->assertSame('705-555-0001', $result->phone);
        $this->assertSame('nc-uuid-123', $result->communityId);
        $this->assertSame(1, $result->status);
    }

    #[Test]
    public function it_maps_minimal_leader_data(): void
    {
        $mapper = new LeaderMapper();
        $result = $mapper->map([
            'name' => 'Councillor One',
            'role' => 'Councillor',
        ], 'nc-uuid-456');

        $this->assertSame('Councillor One', $result->name);
        $this->assertSame('Councillor', $result->role);
        $this->assertNull($result->email);
        $this->assertNull($result->phone);
        $this->assertSame('nc-uuid-456', $result->communityId);
    }

    #[Test]
    public function it_falls_back_to_role_title_when_role_missing(): void
    {
        $mapper = new LeaderMapper();
        $result = $mapper->map([
            'name' => 'Elder',
            'role_title' => 'Elder Advisor',
        ], 'nc-uuid-789');

        $this->assertSame('Elder Advisor', $result->role);
    }

    #[Test]
    public function to_array_returns_expected_keys(): void
    {
        $mapper = new LeaderMapper();
        $result = $mapper->map([
            'name' => 'Chief',
            'role' => 'Chief',
            'email' => 'chief@example.com',
            'phone' => '705-555-0001',
        ], 'nc-uuid-123');

        $array = $result->toArray();

        $this->assertArrayHasKey('name', $array);
        $this->assertArrayHasKey('role', $array);
        $this->assertArrayHasKey('email', $array);
        $this->assertArrayHasKey('phone', $array);
        $this->assertArrayHasKey('community_id', $array);
        $this->assertArrayHasKey('status', $array);
        $this->assertArrayHasKey('created_at', $array);
        $this->assertArrayHasKey('updated_at', $array);
    }
}
