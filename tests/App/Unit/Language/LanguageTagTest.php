<?php

declare(strict_types=1);

namespace App\Tests\Unit\Language;

use App\Language\LanguageTag;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(LanguageTag::class)]
final class LanguageTagTest extends TestCase
{
    /** @var list<string> */
    private const array SUBTAGS = ['ojg', 'otw', 'ojb'];

    #[Test]
    public function it_parses_the_bare_language(): void
    {
        $tag = LanguageTag::parse('oj', self::SUBTAGS);

        self::assertNotNull($tag);
        self::assertSame('oj', $tag->language);
        self::assertNull($tag->dialectSubtag);
        self::assertNull($tag->community);
        self::assertSame('oj', $tag->canonical);
    }

    #[Test]
    public function it_parses_a_community_tag(): void
    {
        $tag = LanguageTag::parse('oj-x-sagamok', self::SUBTAGS);

        self::assertNotNull($tag);
        self::assertNull($tag->dialectSubtag);
        self::assertSame('sagamok', $tag->community);
        self::assertSame('oj-x-sagamok', $tag->canonical);
    }

    #[Test]
    public function it_parses_a_dialect_and_a_dialect_plus_community_tag(): void
    {
        $dialect = LanguageTag::parse('oj-ojg', self::SUBTAGS);
        self::assertNotNull($dialect);
        self::assertSame('ojg', $dialect->dialectSubtag);
        self::assertNull($dialect->community);

        $both = LanguageTag::parse('oj-otw-x-sagamok', self::SUBTAGS);
        self::assertNotNull($both);
        self::assertSame('otw', $both->dialectSubtag);
        self::assertSame('sagamok', $both->community);
        self::assertSame('oj-otw-x-sagamok', $both->canonical);
    }

    #[Test]
    public function it_is_case_insensitive_and_canonicalizes_to_lowercase(): void
    {
        $tag = LanguageTag::parse('OJ-X-SAGAMOK', self::SUBTAGS);

        self::assertNotNull($tag);
        self::assertSame('oj-x-sagamok', $tag->canonical);
    }

    /**
     * @return list<array{0: string}>
     */
    public static function malformedProvider(): array
    {
        return [
            ['oji-east'],   // the old custom code, not a tag
            ['klingon'],    // wrong language
            [''],           // empty
            ['oj-x'],       // dangling private-use singleton
            ['x'],          // bare private-use singleton
            ['oj-zz'],      // unknown dialect subtag
            ['oj-x-toolongsubtag'],  // private-use subtag over 8 chars
            ['oj-ojg-otw'], // more than one subtag before -x-
        ];
    }

    #[Test]
    #[\PHPUnit\Framework\Attributes\DataProvider('malformedProvider')]
    public function it_rejects_malformed_tags(string $raw): void
    {
        self::assertNull(LanguageTag::parse($raw, self::SUBTAGS));
    }
}
