<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\ElderSupportRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ElderSupportRequest::class)]
final class ElderSupportRequestTest extends TestCase
{
    #[Test]
    public function it_creates_with_defaults(): void
    {
        $request = new ElderSupportRequest(['name' => 'Mary Swifthawk', 'phone' => '705-555-0101', 'type' => 'ride']);

        $this->assertSame('Mary Swifthawk', $request->get('name'));
        $this->assertSame('705-555-0101', $request->get('phone'));
        $this->assertSame('ride', $request->get('type'));
        $this->assertSame('open', $request->get('status'));
    }

    #[Test]
    public function it_exposes_entity_type_id(): void
    {
        $request = new ElderSupportRequest(['name' => 'Test', 'phone' => '555-0000', 'type' => 'groceries']);

        $this->assertSame('elder_support_request', $request->getEntityTypeId());
    }

    #[Test]
    public function it_supports_optional_fields(): void
    {
        $request = new ElderSupportRequest([
            'name' => 'George Redcloud',
            'phone' => '705-555-0202',
            'type' => 'chores',
            'community' => 'Sagamok Anishnawbek',
            'notes' => 'Please bring gloves.',
        ]);

        $this->assertSame('Sagamok Anishnawbek', $request->get('community'));
        $this->assertSame('Please bring gloves.', $request->get('notes'));
    }

    #[Test]
    public function it_defaults_assignment_fields_to_null(): void
    {
        $request = new ElderSupportRequest(['name' => 'Test', 'phone' => '555', 'type' => 'ride']);

        $this->assertNull($request->get('assigned_volunteer'));
        $this->assertNull($request->get('assigned_at'));
    }
}
