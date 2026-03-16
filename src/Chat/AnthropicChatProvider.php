<?php

declare(strict_types=1);

namespace Minoo\Chat;

final class AnthropicChatProvider implements ChatProviderInterface
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $model,
        private readonly int $maxTokens,
        private readonly string $apiUrl,
    ) {}

    public function sendMessage(array $messages, string $systemPrompt): ChatResponse
    {
        if ($this->apiKey === '') {
            return ChatResponse::fail('AI chat is not configured. ANTHROPIC_API_KEY is missing.');
        }

        $payload = json_encode([
            'model' => $this->model,
            'max_tokens' => $this->maxTokens,
            'system' => $systemPrompt,
            'messages' => $messages,
        ], JSON_THROW_ON_ERROR);

        $ch = curl_init($this->apiUrl);
        if ($ch === false) {
            return ChatResponse::fail('Failed to initialize HTTP client.');
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'x-api-key: ' . $this->apiKey,
                'anthropic-version: 2023-06-01',
            ],
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            return ChatResponse::fail('Connection failed: ' . $curlError);
        }

        if ($httpCode !== 200) {
            return ChatResponse::fail('API returned HTTP ' . $httpCode);
        }

        $data = json_decode((string) $response, true, 512, JSON_THROW_ON_ERROR);
        $content = $data['content'][0]['text'] ?? '';

        if ($content === '') {
            return ChatResponse::fail('Empty response from AI.');
        }

        return ChatResponse::ok($content);
    }
}
