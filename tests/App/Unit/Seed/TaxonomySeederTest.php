<?php

declare(strict_types=1);

namespace App\Tests\Unit\Seed;

use App\Seed\TaxonomySeeder;
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

    #[Test]
    public function it_provides_person_roles_vocabulary_with_terms(): void
    {
        $data = TaxonomySeeder::personRolesVocabulary();

        $this->assertSame('person_roles', $data['vocabulary']['vid']);
        $this->assertSame('Person Roles', $data['vocabulary']['name']);
        $this->assertCount(15, $data['terms']);
        $this->assertSame('Elder', $data['terms'][0]['name']);
    }

    #[Test]
    public function it_provides_person_offerings_vocabulary_with_terms(): void
    {
        $data = TaxonomySeeder::personOfferingsVocabulary();

        $this->assertSame('person_offerings', $data['vocabulary']['vid']);
        $this->assertSame('Person Offerings', $data['vocabulary']['name']);
        $this->assertCount(15, $data['terms']);
        $this->assertSame('Food', $data['terms'][0]['name']);
    }

    #[Test]
    public function personRolesIncludesArtist(): void
    {
        $data = TaxonomySeeder::personRolesVocabulary();
        $names = array_column($data['terms'], 'name');
        $this->assertContains('Artist', $names);
    }

    #[Test]
    public function personOfferingsIncludesHairServices(): void
    {
        $data = TaxonomySeeder::personOfferingsVocabulary();
        $names = array_column($data['terms'], 'name');
        $this->assertContains('Hair Services', $names);
    }
}
