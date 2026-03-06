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
            'name' => 'Larry Smallwood',
            'code' => 'ls',
        ]);

        $this->assertSame('Larry Smallwood', $speaker->get('name'));
        $this->assertSame('ls', $speaker->get('code'));
        $this->assertSame('speaker', $speaker->getEntityTypeId());
    }

    #[Test]
    public function it_supports_bio_and_media(): void
    {
        $speaker = new Speaker([
            'name' => 'Eugene Stillday',
            'code' => 'es',
            'bio' => 'Elder and language keeper.',
            'media_id' => 10,
        ]);

        $this->assertSame('Elder and language keeper.', $speaker->get('bio'));
        $this->assertSame(10, $speaker->get('media_id'));
    }
}
