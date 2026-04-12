<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\OralHistory;
use App\Provider\AppServiceProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(OralHistory::class)]
final class OralHistoryTest extends TestCase
{
    #[Test]
    public function it_creates_with_required_fields(): void
    {
        $story = new OralHistory([
            'title' => 'The Seven Fires Prophecy',
            'type' => 'prophecy',
            'content' => 'Long ago, seven prophets came to the Anishinaabe...',
        ]);

        $this->assertSame('The Seven Fires Prophecy', $story->get('title'));
        $this->assertSame('prophecy', $story->bundle());
        $this->assertSame('oral_history', $story->getEntityTypeId());
        $this->assertSame(1, $story->get('status'));
    }

    #[Test]
    public function it_sets_default_values(): void
    {
        $story = new OralHistory([
            'title' => 'Creation Story',
            'type' => 'creation',
        ]);

        $this->assertSame(1, $story->get('status'));
        $this->assertSame('open', $story->get('protocol_level'));
        $this->assertSame(0, $story->get('is_living_record'));
        $this->assertSame(0, $story->get('created_at'));
        $this->assertSame(0, $story->get('updated_at'));
    }

    #[Test]
    public function it_supports_collection_reference(): void
    {
        $story = new OralHistory([
            'title' => 'A Teaching',
            'type' => 'teaching',
            'collection_id' => 5,
            'story_order' => 3,
        ]);

        $this->assertSame(5, $story->get('collection_id'));
        $this->assertSame(3, $story->get('story_order'));
    }

    #[Test]
    public function it_supports_protocol_levels(): void
    {
        $story = new OralHistory([
            'title' => 'Restricted Story',
            'type' => 'ceremony',
            'protocol_level' => 'restricted',
        ]);

        $this->assertSame('restricted', $story->get('protocol_level'));
    }

    #[Test]
    public function it_defines_community_id_field(): void
    {
        $provider = new AppServiceProvider();
        $provider->register();

        $types = $provider->getEntityTypes();
        $ohType = array_values(array_filter($types, fn($t) => $t->id() === 'oral_history'))[0];
        $fields = $ohType->getFieldDefinitions();

        $this->assertArrayHasKey('community_id', $fields);
        $this->assertSame('entity_reference', $fields['community_id']['type']);
        $this->assertSame('community', $fields['community_id']['settings']['target_type']);
    }
}
