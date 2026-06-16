<?php

declare(strict_types=1);

namespace App\Tests\Unit\I18n;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * EN/OJ parity guard (#805). Anishinaabemowin copy needs fluent speakers, so we
 * do NOT assert oj.php is fully translated — missing/empty OJ values fall back
 * to English at runtime (verified). What we DO lock:
 *  1. every trans() key used in a template exists in en.php (else it renders the
 *     raw key — a real, user-visible bug);
 *  2. oj.php has no keys that en.php lacks (oj must not drift ahead of source).
 */
#[CoversNothing]
final class TranslationParityTest extends TestCase
{
    /** @return array<string, true> */
    private function translationKeysUsedInTemplates(): array
    {
        $root = dirname(__DIR__, 4);
        $used = [];
        $rii = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root . '/templates'));
        foreach ($rii as $file) {
            if (!$file->isFile() || !str_ends_with($file->getFilename(), '.twig')) {
                continue;
            }
            $contents = (string) file_get_contents($file->getPathname());
            // Match only static keys: trans('key') or trans('key', ...). Requiring a
            // closing ) or , after the literal skips dynamic concatenations such as
            // trans('feed.posted_' ~ item.type), whose runtime keys (feed.posted_event,
            // feed.posted_group, ...) are defined directly in en.php and cannot be
            // verified by a static scan.
            if (preg_match_all("/trans\(\s*'([^']+)'\s*[),]/", $contents, $matches) > 0) {
                foreach ($matches[1] as $key) {
                    $used[$key] = true;
                }
            }
        }
        return $used;
    }

    #[Test]
    public function every_translation_key_used_in_a_template_exists_in_english(): void
    {
        $en = require dirname(__DIR__, 4) . '/resources/lang/en.php';
        $missing = array_keys(array_diff_key($this->translationKeysUsedInTemplates(), $en));
        sort($missing);

        $this->assertSame(
            [],
            $missing,
            "Template trans() keys missing from resources/lang/en.php (would render the raw key): \n  " . implode("\n  ", $missing),
        );
    }

    #[Test]
    public function oj_does_not_define_keys_that_english_lacks(): void
    {
        $en = require dirname(__DIR__, 4) . '/resources/lang/en.php';
        $oj = require dirname(__DIR__, 4) . '/resources/lang/oj.php';
        $extra = array_keys(array_diff_key($oj, $en));
        sort($extra);

        $this->assertSame([], $extra, "oj.php keys absent from en.php: \n  " . implode("\n  ", $extra));
    }
}
