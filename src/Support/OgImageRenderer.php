<?php

declare(strict_types=1);

namespace App\Support;

use RuntimeException;

/**
 * Renders 1200×630 Open Graph card PNGs (GD + FreeType).
 */
final class OgImageRenderer
{
    public const WIDTH = 1200;

    public const HEIGHT = 630;

    public const STYLE_DEFAULT = 'default';

    public const STYLE_EMERGENCY = 'emergency';

    /** @var list<string> */
    private array $fontCandidates;

    public function __construct(
        private readonly string $projectRoot,
        private readonly ?string $boldFontOverride = null,
    ) {
        $this->fontCandidates = array_values(array_filter([
            $this->boldFontOverride,
            getenv('MINOO_OG_FONT_BOLD') ?: null,
            '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
            '/usr/share/fonts/ttf-dejavu/DejaVuSans-Bold.ttf',
            $this->projectRoot . '/storage/fonts/DejaVuSans-Bold.ttf',
        ], static fn (?string $p): bool => $p !== null && $p !== ''));
    }

    /**
     * @param array{0: int, 1: int, 2: int} $accentRgb
     * @param string|null $imageCta Optional line(s) above the domain footer (emergency style only).
     */
    public function renderPng(
        string $title,
        string $subtitle,
        array $accentRgb,
        string $style = self::STYLE_DEFAULT,
        ?string $imageCta = null,
    ): string {
        if (!extension_loaded('gd')) {
            throw new RuntimeException('PHP GD extension is required for Open Graph images.');
        }

        $font = $this->resolveFontPath();
        if ($font === null) {
            throw new RuntimeException(
                'No TrueType font found for OG images. Install ttf-dejavu or set MINOO_OG_FONT_BOLD.',
            );
        }

        $im = imagecreatetruecolor(self::WIDTH, self::HEIGHT);
        if ($im === false) {
            throw new RuntimeException('imagecreatetruecolor failed.');
        }

        $isEmergency = $style === self::STYLE_EMERGENCY;
        $bgRgb = $isEmergency ? [22, 8, 8] : [10, 10, 10];
        $accentBarWidth = $isEmergency ? 22 : 14;

        $bg = imagecolorallocate($im, $bgRgb[0], $bgRgb[1], $bgRgb[2]);
        $textPrimary = imagecolorallocate($im, 240, 236, 230);
        $textMuted = imagecolorallocate($im, 160, 160, 150);
        $accent = imagecolorallocate(
            $im,
            max(0, min(255, $accentRgb[0])),
            max(0, min(255, $accentRgb[1])),
            max(0, min(255, $accentRgb[2])),
        );

        imagefilledrectangle($im, 0, 0, self::WIDTH, self::HEIGHT, $bg);
        imagefilledrectangle($im, 0, 0, $accentBarWidth, self::HEIGHT, $accent);

        $paddingX = 72;
        $paddingY = 64;
        $maxTextWidth = self::WIDTH - $paddingX * 2;

        $subtitleSize = 26;
        $titleSize = 52;
        $footerSize = 20;

        $y = $paddingY + $subtitleSize;
        $this->drawTtfLine($im, $font, $subtitleSize, $paddingX, $y, $textMuted, $subtitle);

        $y += (int) round($subtitleSize * 1.85);
        if ($isEmergency) {
            $y += 18;
        }

        // Baseline advance between lines: imagettftext() y is the baseline; 1.15× pt size
        // was too tight (descenders collided with the line below). ~1.38–1.42 reads cleanly.
        $titleLineAdvance = (int) round($titleSize * ($isEmergency ? 1.42 : 1.32));

        $lines = $this->wrapTitle($font, $title, $titleSize, $maxTextWidth, 4);
        foreach ($lines as $line) {
            $this->drawTtfLine($im, $font, $titleSize, $paddingX, $y, $textPrimary, $line);
            $y += $titleLineAdvance;
        }

        $ctaText = $imageCta !== null ? trim($imageCta) : '';
        if ($ctaText !== '' && $isEmergency) {
            $ctaSize = 24;
            $ctaColor = imagecolorallocate($im, 255, 196, 188);
            $ctaLines = $this->wrapTitle($font, $ctaText, $ctaSize, $maxTextWidth, 2);
            $ctaLineAdvance = (int) round($ctaSize * 1.28);
            $footerReserve = 56;
            $ctaBaseline = self::HEIGHT - $footerReserve - $ctaSize
                - ($ctaLineAdvance * max(0, count($ctaLines) - 1));
            foreach ($ctaLines as $i => $ctaLine) {
                $this->drawTtfLine(
                    $im,
                    $font,
                    $ctaSize,
                    $paddingX,
                    $ctaBaseline + $i * $ctaLineAdvance,
                    $ctaColor,
                    $ctaLine,
                );
            }
        }

        $footer = 'minoo.live';
        $bbox = imagettfbbox($footerSize, 0, $font, $footer);
        if ($bbox !== false) {
            $fw = abs($bbox[2] - $bbox[0]);
            $fx = self::WIDTH - $paddingX - $fw;
            $fy = self::HEIGHT - 48;
            imagettftext($im, $footerSize, 0, $fx, $fy, $textMuted, $font, $footer);
        }

        ob_start();
        imagepng($im, null, 6);
        $binary = (string) ob_get_clean();
        imagedestroy($im);

        return $binary;
    }

    private function resolveFontPath(): ?string
    {
        foreach ($this->fontCandidates as $path) {
            if (is_readable($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function wrapTitle(string $font, string $title, int $fontSize, int $maxWidth, int $maxLines): array
    {
        $title = trim($title);
        if ($title === '') {
            return ['Minoo'];
        }

        $words = preg_split('/\s+/u', $title) ?: [];
        $lines = [];
        $current = '';

        foreach ($words as $word) {
            $trial = $current === '' ? $word : $current . ' ' . $word;
            $w = $this->textWidth($font, $fontSize, $trial);
            if ($w <= $maxWidth) {
                $current = $trial;
                continue;
            }

            if ($current !== '') {
                $lines[] = $current;
                $current = $word;
            } else {
                $lines[] = $this->truncateToWidth($font, $fontSize, $word, $maxWidth);
                $current = '';
            }

            if (count($lines) >= $maxLines) {
                $lines[$maxLines - 1] = $this->appendEllipsis(
                    $font,
                    $fontSize,
                    $lines[$maxLines - 1],
                    $maxWidth,
                );

                return $lines;
            }
        }

        if ($current !== '') {
            $lines[] = $current;
        }

        return $lines !== [] ? $lines : ['Minoo'];
    }

    private function appendEllipsis(string $font, int $fontSize, string $line, int $maxWidth): string
    {
        $ellipsis = '…';
        $base = $line;
        while ($base !== '' && $this->textWidth($font, $fontSize, $base . $ellipsis) > $maxWidth) {
            $base = mb_substr($base, 0, -1);
        }

        return $base === '' ? $ellipsis : $base . $ellipsis;
    }

    private function truncateToWidth(string $font, int $fontSize, string $word, int $maxWidth): string
    {
        if ($this->textWidth($font, $fontSize, $word) <= $maxWidth) {
            return $word;
        }

        $ellipsis = '…';
        $cut = $word;
        while (mb_strlen($cut) > 1 && $this->textWidth($font, $fontSize, $cut . $ellipsis) > $maxWidth) {
            $cut = mb_substr($cut, 0, -1);
        }

        return $cut . $ellipsis;
    }

    private function textWidth(string $font, int $fontSize, string $text): int
    {
        $bbox = imagettfbbox($fontSize, 0, $font, $text);

        return $bbox !== false ? abs((int) ($bbox[2] - $bbox[0])) : 0;
    }

    private function drawTtfLine(\GdImage $im, string $font, int $size, int $x, int $y, int $color, string $text): void
    {
        imagettftext($im, $size, 0, $x, $y, $color, $font, $text);
    }
}
