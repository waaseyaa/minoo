<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Seed;

use Minoo\Seed\TaxonomySeeder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(TaxonomySeeder::class)]
final class TaxonomySeederTest extends TestCase
{
    #[Test]
    public function it_provides_gallery_vocabulary_with_terms(): void
    {
        $data = TaxonomySeeder::galleryVocabulary();

        $this->assertSame('gallery', $data['vocabulary']['vid']);
        $this->assertSame('Gallery', $data['vocabulary']['name']);
        $this->assertCount(6, $data['terms']);
        $this->assertSame('fishing', $data['terms'][0]['name']);
    }

    #[Test]
    public function it_provides_teaching_tags_vocabulary_with_terms(): void
    {
        $data = TaxonomySeeder::teachingTagsVocabulary();

        $this->assertSame('teaching_tags', $data['vocabulary']['vid']);
        $this->assertCount(6, $data['terms']);
        $this->assertSame('ceremony', $data['terms'][0]['name']);
    }
}
