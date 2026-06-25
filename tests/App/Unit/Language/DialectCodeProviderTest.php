<?php

declare(strict_types=1);

namespace App\Tests\Unit\Language;

use App\Language\DialectCodeProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(DialectCodeProvider::class)]
final class DialectCodeProviderTest extends TestCase
{
    #[Test]
    public function is_valid_accepts_bcp47_tags_and_rejects_the_old_codes(): void
    {
        $p = new DialectCodeProvider();

        self::assertTrue($p->isValid('oj'));
        self::assertTrue($p->isValid('oj-x-sagamok'));
        self::assertTrue($p->isValid('oj-ojg'));
        self::assertTrue($p->isValid('OJ-X-SAGAMOK'), 'Case-insensitive.');

        self::assertFalse($p->isValid('oji-east'), 'The old custom dialect code is not a valid tag.');
        self::assertFalse($p->isValid('klingon'));
        self::assertFalse($p->isValid(''));
        self::assertFalse($p->isValid('oj-x'), 'Dangling private-use is malformed.');
    }

    #[Test]
    public function codes_are_tag_aware_and_drop_the_old_codes(): void
    {
        $codes = (new DialectCodeProvider())->codes();

        self::assertContains('oj', $codes);
        self::assertContains('oj-ojg', $codes);
        self::assertNotContains('oji-east', $codes, 'No custom dialect codes; only BCP 47 tags.');
    }

    #[Test]
    public function all_exposes_groupings_with_their_tags(): void
    {
        $byCode = [];
        foreach ((new DialectCodeProvider())->all() as $row) {
            $byCode[$row['code']] = $row;
        }

        self::assertArrayHasKey('oji-east', $byCode);
        self::assertSame('oj-ojg', $byCode['oji-east']['tag']);
        self::assertSame('Eastern Ojibwe', $byCode['oji-east']['display_name']);
        self::assertSame('ojg', $byCode['oji-east']['iso_639_3']);
    }

    #[Test]
    public function dialect_is_derived_from_a_community_or_dialect_tag(): void
    {
        $p = new DialectCodeProvider();

        self::assertSame('oji-east', $p->dialectCodeForTag('oj-x-sagamok'));
        self::assertSame('oji-east', $p->dialectCodeForTag('oj-ojg'));
        self::assertNull($p->dialectCodeForTag('oj'), 'The bare language derives no grouping.');
        self::assertNull($p->dialectCodeForTag('oj-x-sagamok2'), 'An unmapped community derives no grouping.');

        $grouping = $p->dialectFor('oj-x-sagamok');
        self::assertNotNull($grouping);
        self::assertSame('Nishnaabemwin', $grouping['name']);
        self::assertSame('Eastern Ojibwe', $grouping['display_name']);
    }

    #[Test]
    public function label_reads_as_grouping_and_community(): void
    {
        $p = new DialectCodeProvider();

        self::assertSame('Nishnaabemwin (Sagamok)', $p->label('oj-x-sagamok'));
        self::assertSame('Nishnaabemwin (Serpent River)', $p->label('oj-x-serpent-river'));
        self::assertSame('Eastern Ojibwe', $p->label('oj-ojg'));
        self::assertSame('Anishinaabemowin', $p->label('oj'));
    }
}
