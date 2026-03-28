<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Entity;

use Minoo\Entity\Speaker;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Speaker::class)]
final class SpeakerTest extends TestCase
{
    #[Test]
    public function it_creates_with_required_fields(): void
    {
        $speaker = new Speaker([
            'name' => 'Mary Jones',
            'code' => 'mj-001',
        ]);

        $this->assertSame('Mary Jones', $speaker->get('name'));
        $this->assertSame('mj-001', $speaker->get('code'));
        $this->assertSame('speaker', $speaker->getEntityTypeId());
    }

    #[Test]
    public function it_defaults_consent_public_display_to_1(): void
    {
        $speaker = new Speaker([
            'name' => 'Mary Jones',
            'code' => 'mj-001',
        ]);

        $this->assertSame(1, $speaker->get('consent_public_display'));
    }

    #[Test]
    public function it_defaults_consent_ai_training_to_0(): void
    {
        $speaker = new Speaker([
            'name' => 'Mary Jones',
            'code' => 'mj-001',
        ]);

        $this->assertSame(0, $speaker->get('consent_ai_training'));
    }

    #[Test]
    public function it_defaults_status_to_1(): void
    {
        $speaker = new Speaker([
            'name' => 'Mary Jones',
            'code' => 'mj-001',
        ]);

        $this->assertSame(1, $speaker->get('status'));
    }

    #[Test]
    public function it_stores_optional_bio(): void
    {
        $speaker = new Speaker([
            'name' => 'Mary Jones',
            'code' => 'mj-001',
            'bio' => 'Fluent Nishnaabemwin speaker from Sagamok.',
        ]);

        $this->assertSame('Fluent Nishnaabemwin speaker from Sagamok.', $speaker->get('bio'));
    }

    #[Test]
    public function it_stores_slug(): void
    {
        $speaker = new Speaker([
            'name' => 'Mary Jones',
            'code' => 'mj-001',
            'slug' => 'mary-jones',
        ]);

        $this->assertSame('mary-jones', $speaker->get('slug'));
    }
}
