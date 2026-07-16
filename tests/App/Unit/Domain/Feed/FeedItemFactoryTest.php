<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Feed;

use App\Domain\Feed\FeedItemFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Entity\ContentEntityBase;

/**
 * Regression lock for the community_group rename (#923 spec §7 item 7).
 *
 * FeedItemFactory::buildGroup() is a flagged conflation hazard in the rename's
 * code-sweep table: the type id and feed-item id prefix change to
 * 'community_group', but the '/groups/{slug}' URL and the 'Group' badge label
 * are product surface and must NOT change. No test previously existed for
 * this factory.
 */
#[CoversClass(FeedItemFactory::class)]
final class FeedItemFactoryTest extends TestCase
{
    #[Test]
    public function community_group_entity_produces_the_expected_feed_item_shape(): void
    {
        $entity = $this->makeEntity([
            'name' => 'Anishinaabemowin Language Circle',
            'slug' => 'anishinaabemowin-language-circle',
            'description' => 'A group for language learners.',
            'community_id' => null,
            'created_at' => 1700000000,
        ], id: 7);

        $factory = new FeedItemFactory();
        $item = $factory->fromEntity('community_group', $entity, 0);

        self::assertSame('community_group', $item->type);
        self::assertStringStartsWith('community_group:', $item->id);
        self::assertSame('community_group:7', $item->id);
        // KEEP — URL surface unchanged by the rename (#923 spec §5).
        self::assertSame('/groups/anishinaabemowin-language-circle', $item->url);
        // KEEP — badge label unchanged by the rename (#923 spec §6 item 3
        // only changes the entity type's registration label, not this badge).
        self::assertSame('Group', $item->badge);
    }

    /** @param array<string, mixed> $fields */
    private function makeEntity(array $fields, int|string|null $id = null): ContentEntityBase
    {
        $mock = $this->createMock(ContentEntityBase::class);
        $mock->method('id')->willReturn($id);
        $mock->method('get')->willReturnCallback(static fn (string $f) => $fields[$f] ?? null);

        return $mock;
    }
}
