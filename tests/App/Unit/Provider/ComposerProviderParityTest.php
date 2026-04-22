<?php

declare(strict_types=1);

namespace App\Tests\Unit\Provider;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Foundation\Discovery\PackageManifestCompiler;

final class ComposerProviderParityTest extends TestCase
{
    #[Test]
    public function composer_extra_providers_match_compiled_manifest(): void
    {
        $root = dirname(__DIR__, 4);
        $composer = json_decode((string) file_get_contents($root . '/composer.json'), true, 512, JSON_THROW_ON_ERROR);
        $expected = $composer['extra']['waaseyaa']['providers'] ?? [];
        $this->assertIsArray($expected);

        $compiler = new PackageManifestCompiler(
            basePath: $root,
            storagePath: $root . '/storage',
        );
        $manifest = $compiler->compile();

        $n = \count($expected);
        $suffix = \array_slice($manifest->providers, -$n);
        $this->assertSame(
            $expected,
            $suffix,
            'Root composer extra.waaseyaa.providers must match the trailing segment of the compiled manifest (app providers are appended after package providers).',
        );
    }
}
