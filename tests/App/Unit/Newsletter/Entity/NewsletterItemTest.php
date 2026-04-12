<?php
declare(strict_types=1);

namespace App\Tests\Unit\Newsletter\Entity;

use App\Entity\NewsletterItem;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(NewsletterItem::class)]
final class NewsletterItemTest extends TestCase
{
    #[Test]
    public function source_backed_item_holds_reference(): void
    {
        $item = new NewsletterItem([
            'nitid' => 1,
            'edition_id' => 5,
            'position' => 1,
            'section' => 'events',
            'source_type' => 'event',
            'source_id' => 314,
            'inline_title' => null,
            'inline_body' => null,
            'editor_blurb' => 'Spring sugar bush at Wiikwemkoong',
            'included' => true,
        ]);

        $this->assertSame(5, $item->get('edition_id'));
        $this->assertSame('events', $item->get('section'));
        $this->assertSame('event', $item->get('source_type'));
        $this->assertSame(314, $item->get('source_id'));
        $this->assertTrue((bool) $item->get('included'));
    }

    #[Test]
    public function inline_item_carries_title_and_body(): void
    {
        $item = new NewsletterItem([
            'nitid' => 2,
            'edition_id' => 5,
            'position' => 2,
            'section' => 'community',
            'source_type' => null,
            'source_id' => null,
            'inline_title' => 'Happy 80th Birthday Edna',
            'inline_body' => 'From all your grandchildren and great-grandchildren.',
            'editor_blurb' => null,
            'included' => true,
        ]);

        $this->assertNull($item->get('source_type'));
        $this->assertSame('Happy 80th Birthday Edna', $item->get('inline_title'));
    }
}
