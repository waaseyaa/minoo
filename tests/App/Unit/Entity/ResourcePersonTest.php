<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\ResourcePerson;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ResourcePerson::class)]
final class ResourcePersonTest extends TestCase
{
    #[Test]
    public function it_creates_with_required_fields(): void
    {
        $person = new ResourcePerson([
            'name' => 'Mary Trudeau',
            'slug' => 'mary-trudeau',
        ]);

        $this->assertSame('Mary Trudeau', $person->get('name'));
        $this->assertSame('mary-trudeau', $person->get('slug'));
        $this->assertSame('resource_person', $person->getEntityTypeId());
    }

    #[Test]
    public function it_defaults_status_to_published(): void
    {
        $person = new ResourcePerson(['name' => 'Test']);

        $this->assertSame(1, $person->get('status'));
    }

    #[Test]
    public function it_supports_all_optional_fields(): void
    {
        $person = new ResourcePerson([
            'name' => 'John Beaucage',
            'slug' => 'john-beaucage',
            'bio' => 'Elder and knowledge keeper.',
            'community' => 'Sagamok Anishnawbek',
            'email' => 'john@example.com',
            'phone' => '705-555-1234',
            'business_name' => 'Beaucage Consulting',
            'media_id' => 5,
        ]);

        $this->assertSame('Elder and knowledge keeper.', $person->get('bio'));
        $this->assertSame('Sagamok Anishnawbek', $person->get('community'));
        $this->assertSame('john@example.com', $person->get('email'));
        $this->assertSame('705-555-1234', $person->get('phone'));
        $this->assertSame('Beaucage Consulting', $person->get('business_name'));
        $this->assertSame(5, $person->get('media_id'));
    }
}
