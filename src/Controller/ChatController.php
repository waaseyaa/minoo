<?php

declare(strict_types=1);

namespace App\Controller;

use App\Chat\AnthropicChatProvider;
use App\Chat\ChatRateLimiter;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Waaseyaa\Access\AccountInterface;

final class ChatController
{
    /** @param array<string, mixed> $params */
    /** @param array<string, mixed> $query */
    public function send(array $params, array $query, AccountInterface $account, HttpRequest $request): JsonResponse
    {
        $config = self::loadConfig();

        if (!($config['enabled'] ?? false)) {
            return new JsonResponse(['error' => 'Chat is not available.'], 503);
        }

        $body = json_decode($request->getContent(), true);
        $message = trim((string) ($body['message'] ?? ''));

        if ($message === '') {
            return new JsonResponse(['error' => 'Message is required.'], 400);
        }

        if (mb_strlen($message) > 2000) {
            return new JsonResponse(['error' => 'Message is too long (max 2000 characters).'], 400);
        }

        $rateLimit = (int) ($config['rate_limit']['max_requests_per_minute'] ?? 10);
        $rateLimiter = new ChatRateLimiter($rateLimit);

        if (!$rateLimiter->isAllowed()) {
            return new JsonResponse([
                'error' => 'You are sending messages too quickly. Please wait a moment.',
            ], 429);
        }

        $rateLimiter->record();

        $history = $body['history'] ?? [];
        $messages = [];

        foreach ($history as $entry) {
            if (!isset($entry['role'], $entry['content'])) {
                continue;
            }
            $role = $entry['role'] === 'assistant' ? 'assistant' : 'user';
            $messages[] = ['role' => $role, 'content' => trim((string) $entry['content'])];
        }

        $messages[] = ['role' => 'user', 'content' => $message];

        $anthropicConfig = $config['anthropic'] ?? [];
        $provider = new AnthropicChatProvider(
            apiKey: (string) ($anthropicConfig['api_key'] ?? ''),
            model: (string) ($anthropicConfig['model'] ?? 'claude-sonnet-4-20250514'),
            maxTokens: (int) ($anthropicConfig['max_tokens'] ?? 1024),
            apiUrl: (string) ($anthropicConfig['api_url'] ?? 'https://api.anthropic.com/v1/messages'),
        );

        $response = $provider->sendMessage($messages, (string) ($config['system_prompt'] ?? ''));

        if (!$response->success) {
            return new JsonResponse(['error' => $response->error], 502);
        }

        return new JsonResponse([
            'reply' => $response->content,
            'remaining' => $rateLimiter->remainingRequests(),
        ]);
    }

    /** @return array<string, mixed> */
    private static function loadConfig(): array
    {
        $path = dirname(__DIR__, 2) . '/config/ai-chat.php';

        if (!file_exists($path)) {
            return ['enabled' => false];
        }

        return require $path;
    }
}
