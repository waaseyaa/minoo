<?php

declare(strict_types=1);

namespace App\Tests\Unit\Ingest\EntityMapper;

use App\Ingestion\EntityMapper\SpeakerMapper;
use App\Ingestion\ValueObject\SpeakerFields;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(SpeakerMapper::class)]
#[CoversClass(SpeakerFields::class)]
final class SpeakerMapperTest extends TestCase
{
    #[Test]
    public function it_maps_full_speaker_payload(): void
    {
        $mapper = new SpeakerMapper();
        $data = ['name' => 'Eugene Stillday', 'code' => 'es', 'bio' => 'Ponemah, Minnesota.'];

        $result = $mapper->map($data);

        $this->assertInstanceOf(SpeakerFields::class, $result);
        $this->assertSame('Eugene Stillday', $result->name);
        $this->assertSame('es', $result->code);
        $this->assertSame('Ponemah, Minnesota.', $result->bio);
        $this->assertSame('eugene-stillday', $result->slug);
        $this->assertSame(1, $result->status);
    }

    #[Test]
    public function it_creates_minimal_speaker_from_code(): void
    {
        $result = SpeakerMapper::fromCode('es');

        $this->assertInstanceOf(SpeakerFields::class, $result);
        $this->assertSame('es', $result->name);
        $this->assertSame('es', $result->code);
        $this->assertNull($result->bio);
        $this->assertSame(1, $result->status);
    }

    #[Test]
    public function it_converts_to_array(): void
    {
        $result = SpeakerMapper::fromCode('es');
        $array = $result->toArray();

        $this->assertSame('es', $array['name']);
        $this->assertSame('es', $array['code']);
        $this->assertNull($array['bio']);
    }
}
