<?php

declare(strict_types=1);

namespace Minoo\Support;

final class Flash
{
    public static function set(string $type, string $message): void
    {
        $_SESSION['flash'] = [
            'type' => $type,
            'message' => $message,
        ];
    }

    /**
     * @return array{type: string, message: string}|null
     */
    public static function consume(): ?array
    {
        if (!isset($_SESSION['flash']) || !is_array($_SESSION['flash'])) {
            return null;
        }

        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);

        $type = is_string($flash['type'] ?? null) ? $flash['type'] : 'success';
        $message = is_string($flash['message'] ?? null) ? $flash['message'] : '';

        if ($message === '') {
            return null;
        }

        return ['type' => $type, 'message' => $message];
    }
}
