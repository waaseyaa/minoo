<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\CulturalCollection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(CulturalCollection::class)]
final class CulturalCollectionTest extends TestCase
{
    #[Test]
    public function it_creates_with_required_fields(): void
    {
        $item = new CulturalCollection([
            'title' => 'Loon',
            'media_id' => 1,
        ]);

        $this->assertSame('Loon', $item->get('title'));
        $this->assertSame(1, $item->get('media_id'));
        $this->assertSame('cultural_collection', $item->getEntityTypeId());
        $this->assertSame(1, $item->get('status'));
    }

    #[Test]
    public function it_supports_source_attribution(): void
    {
        $item = new CulturalCollection([
            'title' => 'Bandolier Bag',
            'media_id' => 2,
            'source_url' => 'https://ojibwe.lib.umn.edu/collection/bandolier-bag',
            'source_attribution' => 'Copyright Minnesota Historical Society',
        ]);

        $this->assertSame('https://ojibwe.lib.umn.edu/collection/bandolier-bag', $item->get('source_url'));
        $this->assertSame('Copyright Minnesota Historical Society', $item->get('source_attribution'));
    }
}
