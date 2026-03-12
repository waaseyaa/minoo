<?php

declare(strict_types=1);

namespace Minoo\Service;

final class FlashMessageService
{
    private const string SESSION_KEY = 'flash_messages';

    public function addSuccess(string $message): void
    {
        $this->add('success', $message);
    }

    public function addError(string $message): void
    {
        $this->add('error', $message);
    }

    public function addInfo(string $message): void
    {
        $this->add('info', $message);
    }

    /**
     * @return list<array{type: string, message: string}>
     */
    public function consumeAll(): array
    {
        if (!isset($_SESSION[self::SESSION_KEY]) || !is_array($_SESSION[self::SESSION_KEY])) {
            return [];
        }

        $messages = $_SESSION[self::SESSION_KEY];
        unset($_SESSION[self::SESSION_KEY]);

        return array_values(array_filter($messages, static function (mixed $msg): bool {
            return is_array($msg)
                && isset($msg['type'], $msg['message'])
                && is_string($msg['type'])
                && is_string($msg['message'])
                && $msg['message'] !== '';
        }));
    }

    private function add(string $type, string $message): void
    {
        if (!isset($_SESSION[self::SESSION_KEY]) || !is_array($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = [];
        }

        $_SESSION[self::SESSION_KEY][] = ['type' => $type, 'message' => $message];
    }
}
