<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Ingest\EntityMapper;

use Minoo\Ingest\EntityMapper\CulturalCollectionMapper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(CulturalCollectionMapper::class)]
final class CulturalCollectionMapperTest extends TestCase
{
    #[Test]
    public function it_maps_cultural_collection(): void
    {
        $mapper = new CulturalCollectionMapper();
        $data = [
            'title' => 'The Bear Clan',
            'description' => '<p>Cultural significance</p>',
            'source_attribution' => 'UMN Ojibwe Program',
        ];

        $result = $mapper->map($data, 'https://example.com/bear-clan');

        $this->assertSame('The Bear Clan', $result['title']);
        $this->assertSame('Cultural significance', $result['description']);
        $this->assertSame('UMN Ojibwe Program', $result['source_attribution']);
        $this->assertSame('https://example.com/bear-clan', $result['source_url']);
        $this->assertSame('the-bear-clan', $result['slug']);
        $this->assertSame(0, $result['status']);
    }

    #[Test]
    public function it_strips_html_from_description(): void
    {
        $mapper = new CulturalCollectionMapper();
        $data = ['title' => 'Test', 'description' => '<h1>Title</h1><p>Content <b>bold</b></p>'];

        $result = $mapper->map($data, '');

        $this->assertSame('Title Content bold', $result['description']);
    }
}
