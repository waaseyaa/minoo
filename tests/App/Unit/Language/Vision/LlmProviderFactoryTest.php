<?php

declare(strict_types=1);

namespace App\Tests\Unit\Language\Vision;

use App\Language\Vision\LlmProviderFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\AI\Agent\Provider\AnthropicProvider;
use Waaseyaa\AI\Agent\Provider\NullLlmProvider;

#[CoversClass(LlmProviderFactory::class)]
final class LlmProviderFactoryTest extends TestCase
{
    #[Test]
    public function a_configured_key_selects_the_real_anthropic_provider(): void
    {
        self::assertInstanceOf(AnthropicProvider::class, LlmProviderFactory::make('sk-ant-test-key'));
    }

    #[Test]
    public function no_key_falls_back_to_the_framework_null_provider(): void
    {
        self::assertInstanceOf(NullLlmProvider::class, LlmProviderFactory::make(null));
    }

    #[Test]
    public function an_empty_or_whitespace_key_is_treated_as_unset(): void
    {
        self::assertInstanceOf(NullLlmProvider::class, LlmProviderFactory::make(''));
        self::assertInstanceOf(NullLlmProvider::class, LlmProviderFactory::make('   '));
    }
}
