<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Leader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Leader::class)]
final class LeaderTest extends TestCase
{
    #[Test]
    public function it_creates_with_required_fields(): void
    {
        $leader = new Leader([
            'name' => 'Chief Example',
            'role' => 'Chief',
            'community_id' => 'nc-uuid-123',
        ]);

        $this->assertSame('Chief Example', $leader->get('name'));
        $this->assertSame('Chief', $leader->get('role'));
        $this->assertSame('nc-uuid-123', $leader->get('community_id'));
        $this->assertSame('leader', $leader->getEntityTypeId());
    }

    #[Test]
    public function it_supports_optional_contact_fields(): void
    {
        $leader = new Leader([
            'name' => 'Councillor One',
            'role' => 'Councillor',
            'community_id' => 'nc-uuid-456',
            'email' => 'councillor@example.com',
            'phone' => '705-555-0001',
        ]);

        $this->assertSame('councillor@example.com', $leader->get('email'));
        $this->assertSame('705-555-0001', $leader->get('phone'));
    }

    #[Test]
    public function it_defaults_status_and_timestamps(): void
    {
        $leader = new Leader([
            'name' => 'Chief',
            'role' => 'Chief',
        ]);

        $this->assertSame(1, $leader->get('status'));
        $this->assertSame(0, $leader->get('created_at'));
        $this->assertSame(0, $leader->get('updated_at'));
    }
}
