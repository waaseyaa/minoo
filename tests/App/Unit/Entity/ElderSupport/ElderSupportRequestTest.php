<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity\ElderSupport;

use App\Entity\ElderSupport\ElderSupportRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ElderSupportRequest::class)]
final class ElderSupportRequestTest extends TestCase
{
    #[Test]
    public function defaults_a_new_request_to_open_and_unassigned(): void
    {
        $request = new ElderSupportRequest(['name' => 'Test Person', 'message' => 'Needs a ride']);

        $this->assertSame('elder_support_request', $request->getEntityTypeId());
        $this->assertSame('open', $request->get('status'));
        $this->assertNull($request->get('assigned_to'));
    }

    #[Test]
    public function preserves_explicit_status(): void
    {
        $request = new ElderSupportRequest(['name' => 'Test', 'status' => 'closed']);
        $this->assertSame('closed', $request->get('status'));
    }
}
