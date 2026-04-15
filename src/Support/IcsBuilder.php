<?php

declare(strict_types=1);

namespace App\Support;

use Waaseyaa\Entity\ContentEntityBase;

/**
 * Builds RFC 5545 compliant iCalendar (.ics) strings for events.
 */
final class IcsBuilder
{
    private const CRLF = "\r\n";

    /**
     * Build a full VCALENDAR + single VEVENT for the given event entity.
     */
    public static function buildForEvent(ContentEntityBase $event, string $host): string
    {
        $uuid = (string) ($event->get('uuid') ?? $event->id() ?? 'event');
        $host = $host !== '' ? $host : 'minoo.live';

        $title       = (string) ($event->get('title') ?? '');
        $description = (string) ($event->get('description') ?? '');
        $location    = (string) ($event->get('location') ?? '');
        $slug        = (string) ($event->get('slug') ?? '');

        $startsAt = self::toUtcStamp((string) ($event->get('starts_at') ?? ''));
        $endsAt   = self::toUtcStamp((string) ($event->get('ends_at') ?? ''));
        if ($endsAt === '' && $startsAt !== '') {
            // Default to +1 hour if no end provided.
            $endTs  = strtotime((string) ($event->get('starts_at') ?? '')) + 3600;
            $endsAt = gmdate('Ymd\THis\Z', $endTs);
        }

        $dtstamp = gmdate('Ymd\THis\Z');
        $url     = 'https://' . $host . '/events/' . $slug;

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Minoo//Events//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            'UID:' . $uuid . '@' . $host,
            'DTSTAMP:' . $dtstamp,
        ];

        if ($startsAt !== '') {
            $lines[] = 'DTSTART:' . $startsAt;
        }
        if ($endsAt !== '') {
            $lines[] = 'DTEND:' . $endsAt;
        }

        $lines[] = 'SUMMARY:' . self::escapeText($title);
        if ($description !== '') {
            $lines[] = 'DESCRIPTION:' . self::escapeText($description);
        }
        if ($location !== '') {
            $lines[] = 'LOCATION:' . self::escapeText($location);
        }
        if ($slug !== '') {
            $lines[] = 'URL:' . $url;
        }

        $lines[] = 'END:VEVENT';
        $lines[] = 'END:VCALENDAR';

        $folded = array_map([self::class, 'foldLine'], $lines);

        return implode(self::CRLF, $folded) . self::CRLF;
    }

    /**
     * Convert a datetime string to UTC stamp format (e.g. 20260415T130000Z).
     */
    private static function toUtcStamp(string $value): string
    {
        if ($value === '') {
            return '';
        }
        $ts = strtotime($value);
        if ($ts === false) {
            return '';
        }
        return gmdate('Ymd\THis\Z', $ts);
    }

    /**
     * Escape commas, semicolons, backslashes, and newlines per RFC 5545.
     */
    private static function escapeText(string $value): string
    {
        $value = str_replace("\r\n", "\n", $value);
        $value = str_replace(['\\', ';', ','], ['\\\\', '\\;', '\\,'], $value);
        $value = str_replace(["\r", "\n"], ['\\n', '\\n'], $value);
        return $value;
    }

    /**
     * Fold lines longer than 75 octets with CRLF + single space.
     */
    private static function foldLine(string $line): string
    {
        if (strlen($line) <= 75) {
            return $line;
        }

        $out       = '';
        $remaining = $line;
        // First chunk: up to 75 octets.
        $out      .= substr($remaining, 0, 75);
        $remaining = substr($remaining, 75);
        // Subsequent chunks: CRLF + space + up to 74 octets.
        while ($remaining !== '') {
            $out      .= self::CRLF . ' ' . substr($remaining, 0, 74);
            $remaining = substr($remaining, 74);
        }
        return $out;
    }
}
