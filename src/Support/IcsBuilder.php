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
     *
     * RFC 5545 §3.1 folds on 75-OCTET boundaries. Naively slicing at byte 75
     * can split a multibyte UTF-8 sequence mid-character, corrupting content
     * (e.g. Ojibwe syllabics like ᐃ/ᒥ). This implementation walks back from
     * the cut position until it lands on a UTF-8 start byte — either ASCII
     * (0xxxxxxx) or a leading byte (11xxxxxx) — never a continuation
     * (10xxxxxx).
     */
    private static function foldLine(string $line): string
    {
        if (strlen($line) <= 75) {
            return $line;
        }

        $out = '';
        // First chunk: up to 75 octets, continuation chunks: up to 74 octets
        // (one octet is consumed by the leading space on continuation lines).
        $first = true;
        while (strlen($line) > ($first ? 75 : 74)) {
            $limit = $first ? 75 : 74;
            $cut   = $limit;
            // Walk back while the byte AT $cut is a UTF-8 continuation byte.
            // Continuation bytes match 0b10xxxxxx (i.e. (byte & 0xC0) === 0x80).
            // We never want to cut such that the next chunk starts with one.
            while ($cut > 0 && (ord($line[$cut]) & 0xC0) === 0x80) {
                $cut--;
            }
            // Defensive: if we somehow walked back to 0, fall through with
            // a plain byte cut to guarantee forward progress.
            if ($cut === 0) {
                $cut = $limit;
            }
            $chunk = substr($line, 0, $cut);
            $out  .= $first ? $chunk : (self::CRLF . ' ' . $chunk);
            $line  = substr($line, $cut);
            $first = false;
        }
        // Remainder. Loop ran at least once (only reached when line > 75 octets),
        // so $first is always false here — always emit as a continuation.
        if ($line !== '') {
            $out .= self::CRLF . ' ' . $line;
        }
        return $out;
    }
}
