<?php

declare(strict_types=1);

namespace App\Tests\Unit\Support;

use App\Support\OgImageRenderer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(OgImageRenderer::class)]
final class OgImageRendererTest extends TestCase
{
    private static function resolveDejaVuBold(): ?string
    {
        foreach ([
            '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
            '/usr/share/fonts/ttf-dejavu/DejaVuSans-Bold.ttf',
        ] as $path) {
            if (is_readable($path)) {
                return $path;
            }
        }

        return null;
    }

    #[Test]
    public function renderPng_produces_valid_png_signature(): void
    {
        if (!extension_loaded('gd')) {
            self::markTestSkipped('ext-gd not loaded');
        }
        $font = self::resolveDejaVuBold();
        if ($font === null) {
            self::markTestSkipped('DejaVu Sans Bold TTF not found');
        }

        $renderer = new OgImageRenderer(
            dirname(__DIR__, 4),
            $font,
        );
        $png = $renderer->renderPng('Test Title For Open Graph', 'Business', [200, 80, 60]);
        $pngEmergency = $renderer->renderPng(
            'Test Title For Open Graph',
            'Business',
            [200, 80, 60],
            OgImageRenderer::STYLE_EMERGENCY,
        );

        self::assertStringStartsWith("\x89PNG\r\n\x1a\n", $png);
        self::assertGreaterThan(5000, strlen($png));
        self::assertStringStartsWith("\x89PNG\r\n\x1a\n", $pngEmergency);
        self::assertGreaterThan(5000, strlen($pngEmergency));
    }
}
