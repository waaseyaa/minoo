<?php

declare(strict_types=1);

namespace App\Ingestion\Corpus;

use Waaseyaa\AI\Agent\Provider\MessageRequest;
use Waaseyaa\AI\Agent\Provider\ProviderInterface;

/**
 * Drafts the gloss from a whiteboard keyframe via the framework AI provider
 * (Anthropic vision when ANTHROPIC_API_KEY is set), mirroring
 * code/ingest/ingest.py's read_whiteboard.
 *
 * Degrades safely: if no real provider is configured (NullLlmProvider) or the
 * response is not the expected JSON, it returns a blank draft with a note rather
 * than throwing — the row is created as a draft for a human to complete (#853).
 */
final class LlmWhiteboardReader implements WhiteboardReaderInterface
{
    private const string PROMPT = <<<'TXT'
        You're looking at a still frame from a teaching video. The whiteboard in the
        frame should contain a single word or short phrase written in two languages:
        English and Anishinaabemowin (Ojibwe).

        Read the whiteboard. Return JSON with exactly these keys:
          - "ojibwe": the Anishinaabemowin text, exactly as written
          - "english": the English text, exactly as written
          - "confidence": "high" | "medium" | "low"
          - "notes": short note about anything unusual or empty string

        Respond with ONLY the JSON object, no prose.
        TXT;

    public function __construct(
        private readonly ProviderInterface $provider,
    ) {
    }

    public function read(string $keyframePath): array
    {
        $blank = ['ojibwe' => '', 'english' => '', 'notes' => 'vision_unavailable'];

        if (!is_file($keyframePath)) {
            return $blank;
        }

        $imageB64 = base64_encode((string) file_get_contents($keyframePath));

        try {
            $response = $this->provider->sendMessage(new MessageRequest(
                messages: [[
                    'role' => 'user',
                    'content' => [
                        ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => 'image/jpeg', 'data' => $imageB64]],
                        ['type' => 'text', 'text' => self::PROMPT],
                    ],
                ]],
                maxTokens: 400,
            ));

            $parsed = $this->decode($response->getText());
            if ($parsed === null) {
                return $blank;
            }

            return [
                // Verbatim — never normalized (ADR 0003).
                'ojibwe' => (string) ($parsed['ojibwe'] ?? ''),
                'english' => (string) ($parsed['english'] ?? ''),
                'notes' => (string) ($parsed['notes'] ?? ''),
            ];
        } catch (\Throwable) {
            return $blank;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decode(string $text): ?array
    {
        $text = trim($text);
        // Strip a ```json fenced block if the model added one.
        if (str_starts_with($text, '```')) {
            $text = (string) preg_replace('/^```[a-z]*\n?|\n?```$/i', '', $text);
            $text = trim($text);
        }

        $decoded = json_decode($text, true);

        return is_array($decoded) ? $decoded : null;
    }
}
