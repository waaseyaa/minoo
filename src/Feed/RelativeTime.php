<?php

declare(strict_types=1);

namespace App\Feed;

final class RelativeTime
{
    /**
     * Format a timestamp as a human-readable relative time string.
     *
     * Examples: "just now", "2m ago", "1h ago", "Yesterday", "Mar 18"
     */
    public static function format(int $timestamp, ?int $now = null): string
    {
        $now ??= time();
        $diff = $now - $timestamp;

        if ($diff < 0) {
            return self::formatDate($timestamp);
        }

        if ($diff < 60) {
            return 'just now';
        }

        if ($diff < 3600) {
            $minutes = (int) floor($diff / 60);
            return $minutes . 'm ago';
        }

        if ($diff < 86400) {
            $hours = (int) floor($diff / 3600);
            return $hours . 'h ago';
        }

        if ($diff < 172800) {
            return 'Yesterday';
        }

        return self::formatDate($timestamp);
    }

    private static function formatDate(int $timestamp): string
    {
        return date('M j', $timestamp);
    }
}
