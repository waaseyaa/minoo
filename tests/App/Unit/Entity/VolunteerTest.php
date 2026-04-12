<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Volunteer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Volunteer::class)]
final class VolunteerTest extends TestCase
{
    #[Test]
    public function it_creates_with_defaults(): void
    {
        $volunteer = new Volunteer(['name' => 'Anne Birchbark', 'phone' => '705-555-0303']);

        $this->assertSame('Anne Birchbark', $volunteer->get('name'));
        $this->assertSame('705-555-0303', $volunteer->get('phone'));
        $this->assertSame('pending', $volunteer->get('status'));
    }

    #[Test]
    public function default_status_is_pending(): void
    {
        $volunteer = new Volunteer(['name' => 'Test']);
        $this->assertSame('pending', $volunteer->get('status'));
    }

    #[Test]
    public function it_exposes_entity_type_id(): void
    {
        $volunteer = new Volunteer(['name' => 'Test', 'phone' => '555-0000']);

        $this->assertSame('volunteer', $volunteer->getEntityTypeId());
    }

    #[Test]
    public function it_supports_optional_fields(): void
    {
        $volunteer = new Volunteer([
            'name' => 'Tom Eagleheart',
            'phone' => '705-555-0404',
            'availability' => 'Weekends',
            'skills' => ['Rides', 'Groceries'],
            'notes' => 'Has a van.',
        ]);

        $this->assertSame('Weekends', $volunteer->get('availability'));
        $this->assertSame(['Rides', 'Groceries'], $volunteer->get('skills'));
        $this->assertSame('Has a van.', $volunteer->get('notes'));
    }
}
