<?php

declare(strict_types=1);

namespace App\Tests\Unit\I18n;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Regression lock for the community_group rename (#923 spec §7 item 8).
 *
 * engagement.html.twig / card.html.twig build feed i18n keys dynamically from
 * item.type (trans('feed.action_' ~ item.type), trans('feed.posted_' ~
 * item.type)), so TranslationParityTest's static template scan cannot verify
 * these specific runtime keys (see its own docblock). This test locks the
 * rename directly against resources/lang/en.php, loaded the same way
 * TranslationParityTest does.
 */
#[CoversNothing]
final class CommunityGroupI18nKeysTest extends TestCase
{
    #[Test]
    public function english_lang_file_has_the_renamed_feed_keys(): void
    {
        $en = require dirname(__DIR__, 4) . '/resources/lang/en.php';

        $this->assertArrayHasKey('feed.action_community_group', $en);
        $this->assertArrayHasKey('feed.posted_community_group', $en);
    }

    #[Test]
    public function english_lang_file_no_longer_has_the_legacy_group_keys(): void
    {
        $en = require dirname(__DIR__, 4) . '/resources/lang/en.php';

        $this->assertArrayNotHasKey('feed.action_group', $en);
        $this->assertArrayNotHasKey('feed.posted_group', $en);
    }
}
