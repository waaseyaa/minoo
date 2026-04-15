<?php

declare(strict_types=1);

namespace App\Tests\Unit\Support;

use App\Support\IcsBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Entity\ContentEntityBase;

#[CoversClass(IcsBuilder::class)]
final class IcsBuilderTest extends TestCase
{
    private function makeEvent(array $fields): ContentEntityBase
    {
        $mock = $this->createMock(ContentEntityBase::class);
        $mock->method('get')->willReturnCallback(
            static fn (string $k) => $fields[$k] ?? null
        );
        $mock->method('id')->willReturn($fields['__id'] ?? 1);
        return $mock;
    }

    #[Test]
    public function builds_a_valid_vcalendar_envelope(): void
    {
        $event = $this->makeEvent([
            'uuid'        => 'abc-123',
            'title'       => 'Powwow',
            'slug'        => 'powwow',
            'starts_at'   => '2026-04-15 13:00:00',
            'ends_at'     => '2026-04-15 17:00:00',
            'description' => 'A gathering.',
            'location'    => 'Sudbury',
        ]);

        $ics = IcsBuilder::buildForEvent($event, 'minoo.live');

        $this->assertStringContainsString("BEGIN:VCALENDAR\r\n", $ics);
        $this->assertStringContainsString("END:VCALENDAR\r\n", $ics);
        $this->assertStringContainsString('VERSION:2.0', $ics);
        $this->assertStringContainsString('PRODID:-//Minoo//Events//EN', $ics);
        $this->assertStringContainsString('CALSCALE:GREGORIAN', $ics);
        $this->assertStringContainsString('METHOD:PUBLISH', $ics);
        $this->assertStringContainsString('BEGIN:VEVENT', $ics);
        $this->assertStringContainsString('END:VEVENT', $ics);
    }

    #[Test]
    public function uses_utc_timestamp_format_for_dtstart_and_dtend(): void
    {
        $event = $this->makeEvent([
            'uuid'      => 'abc-123',
            'title'     => 'Test',
            'slug'      => 'test',
            'starts_at' => '2026-04-15T13:00:00+00:00',
            'ends_at'   => '2026-04-15T14:00:00+00:00',
        ]);

        $ics = IcsBuilder::buildForEvent($event, 'minoo.live');

        $this->assertMatchesRegularExpression('/DTSTART:20260415T130000Z/', $ics);
        $this->assertMatchesRegularExpression('/DTEND:20260415T140000Z/', $ics);
        $this->assertMatchesRegularExpression('/DTSTAMP:\d{8}T\d{6}Z/', $ics);
    }

    #[Test]
    public function uid_format_combines_uuid_and_host(): void
    {
        $event = $this->makeEvent([
            'uuid'      => 'abc-123',
            'title'     => 'X',
            'slug'      => 'x',
            'starts_at' => '2026-04-15 13:00:00',
        ]);

        $ics = IcsBuilder::buildForEvent($event, 'example.org');

        $this->assertStringContainsString('UID:abc-123@example.org', $ics);
    }

    #[Test]
    public function escapes_commas_semicolons_backslashes_and_newlines(): void
    {
        $event = $this->makeEvent([
            'uuid'        => 'abc',
            'title'       => 'Ceremony',
            'slug'        => 'c',
            'starts_at'   => '2026-04-15 13:00:00',
            'description' => "Line one, with; special\\chars\nNew line",
        ]);

        $ics = IcsBuilder::buildForEvent($event, 'h');

        $this->assertStringContainsString(
            'DESCRIPTION:Line one\\, with\\; special\\\\chars\\nNew line',
            $ics
        );
    }

    #[Test]
    public function uses_crlf_line_endings(): void
    {
        $event = $this->makeEvent([
            'uuid'      => 'abc',
            'title'     => 'T',
            'slug'      => 't',
            'starts_at' => '2026-04-15 13:00:00',
        ]);

        $ics = IcsBuilder::buildForEvent($event, 'h');

        // Every line terminator must be CRLF.
        $this->assertSame(0, substr_count($ics, "\n") - substr_count($ics, "\r\n"));
        $this->assertGreaterThan(0, substr_count($ics, "\r\n"));
    }

    #[Test]
    public function folds_lines_longer_than_75_octets(): void
    {
        $long = str_repeat('a', 200);
        $event = $this->makeEvent([
            'uuid'        => 'abc',
            'title'       => 'T',
            'slug'        => 't',
            'starts_at'   => '2026-04-15 13:00:00',
            'description' => $long,
        ]);

        $ics = IcsBuilder::buildForEvent($event, 'h');

        // Folded continuations must begin with CRLF + single space.
        $this->assertStringContainsString("\r\n ", $ics);

        foreach (explode("\r\n", $ics) as $line) {
            $this->assertLessThanOrEqual(75, strlen($line), 'Line too long: ' . $line);
        }
    }
}
