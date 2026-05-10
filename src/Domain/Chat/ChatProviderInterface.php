<?php

declare(strict_types=1);

namespace App\Domain\Chat;

interface ChatProviderInterface
{
    /**
     * @param list<array{role: string, content: string}> $messages
     */
    public function sendMessage(array $messages, string $systemPrompt): ChatResponse;
}
