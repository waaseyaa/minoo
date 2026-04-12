<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\OralHistoryCollection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(OralHistoryCollection::class)]
final class OralHistoryCollectionTest extends TestCase
{
    #[Test]
    public function it_creates_with_required_fields(): void
    {
        $collection = new OralHistoryCollection([
            'title' => 'Stories of the North Shore',
        ]);

        $this->assertSame('Stories of the North Shore', $collection->get('title'));
        $this->assertSame('oral_history_collection', $collection->getEntityTypeId());
    }

    #[Test]
    public function it_sets_default_values(): void
    {
        $collection = new OralHistoryCollection([
            'title' => 'Elder Teachings',
        ]);

        $this->assertSame(1, $collection->get('status'));
        $this->assertSame('open', $collection->get('protocol_level'));
        $this->assertSame(0, $collection->get('created_at'));
        $this->assertSame(0, $collection->get('updated_at'));
    }

    #[Test]
    public function it_supports_protocol_levels(): void
    {
        $collection = new OralHistoryCollection([
            'title' => 'Community Only Stories',
            'protocol_level' => 'community',
        ]);

        $this->assertSame('community', $collection->get('protocol_level'));
    }
}
