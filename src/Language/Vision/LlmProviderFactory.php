<?php

declare(strict_types=1);

namespace App\Language\Vision;

use Waaseyaa\AI\Agent\Provider\AnthropicProvider;
use Waaseyaa\AI\Agent\Provider\NullLlmProvider;
use Waaseyaa\AI\Agent\Provider\ProviderInterface;

/**
 * Chooses the LLM provider for the corpus vision step (issue #908).
 *
 * The whiteboard reader ({@see \App\Ingestion\Corpus\LlmWhiteboardReader}, run by
 * `anokii:process-reels` and `ingest:corpus`) resolves the framework
 * `ProviderInterface`, which defaults to {@see NullLlmProvider}. Minoo
 * deliberately does NOT register Anokii's `CoIntelligenceServiceProvider` (it
 * would pull in graph entity types, RAG chat routes, and single-admin auth), and
 * that provider is the only thing that otherwise rebinds the provider to
 * Anthropic. So without a binding the vision step always gets the null provider
 * and every upload drafts blank with `notes=vision_unavailable`.
 *
 * This factory is the narrow replacement: a real {@see AnthropicProvider} when a
 * server-side `ANTHROPIC_API_KEY` is configured, and the framework default
 * otherwise, so local dev and CI (no key) behave exactly as before.
 */
final class LlmProviderFactory
{
    /** Vision-capable, cost-appropriate model (matches the Anokii CoIntelligence default). */
    public const string MODEL = 'claude-sonnet-4-6';

    public static function make(?string $apiKey, string $model = self::MODEL): ProviderInterface
    {
        $apiKey = $apiKey !== null ? trim($apiKey) : '';

        return $apiKey !== ''
            ? new AnthropicProvider($apiKey, $model)
            : new NullLlmProvider();
    }
}
