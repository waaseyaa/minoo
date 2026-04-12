<?php

declare(strict_types=1);

namespace App\Chat;

final class ChatResponse
{
    public function __construct(
        public readonly string $content,
        public readonly bool $success,
        public readonly string $error = '',
    ) {}

    public static function ok(string $content): self
    {
        return new self($content, true);
    }

    public static function fail(string $error): self
    {
        return new self('', false, $error);
    }
}
