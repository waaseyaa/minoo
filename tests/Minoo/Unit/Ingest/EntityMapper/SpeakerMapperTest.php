<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Ingest\EntityMapper;

use Minoo\Ingest\EntityMapper\SpeakerMapper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(SpeakerMapper::class)]
final class SpeakerMapperTest extends TestCase
{
    #[Test]
    public function it_maps_full_speaker_payload(): void
    {
        $mapper = new SpeakerMapper();
        $data = ['name' => 'Eugene Stillday', 'code' => 'es', 'bio' => 'Ponemah, Minnesota.'];

        $result = $mapper->map($data);

        $this->assertSame('Eugene Stillday', $result['name']);
        $this->assertSame('es', $result['code']);
        $this->assertSame('Ponemah, Minnesota.', $result['bio']);
        $this->assertSame('eugene-stillday', $result['slug']);
        $this->assertSame(1, $result['status']);
    }

    #[Test]
    public function it_creates_minimal_speaker_from_code(): void
    {
        $result = SpeakerMapper::fromCode('es');

        $this->assertSame('es', $result['name']);
        $this->assertSame('es', $result['code']);
        $this->assertNull($result['bio']);
        $this->assertSame(1, $result['status']);
    }
}
