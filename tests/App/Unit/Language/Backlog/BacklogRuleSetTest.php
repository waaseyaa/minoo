<?php

declare(strict_types=1);

namespace App\Tests\Unit\Language\Backlog;

use App\Language\Backlog\BacklogRuleSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(BacklogRuleSet::class)]
final class BacklogRuleSetTest extends TestCase
{
    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function dropCases(): iterable
    {
        yield 'theme chrome' => ['Skip to content', true];
        yield 'divi artifact' => ['Page load link', true];
        yield 'generic link text' => ['Read more', true];
        yield 'bare here' => ['here', true];
        yield 'cloudflare email' => ['email protected', true];
        yield 'month-year' => ['June 2026', true];
        yield 'bare month' => ['May', true];
        yield 'day month' => ['12 March', true];
        yield 'real nav kept' => ['Contact', false];
        yield 'governance kept' => ['Economic Development', false];
    }

    #[Test]
    #[DataProvider('dropCases')]
    public function it_hard_drops_theme_and_date_noise(string $input, bool $dropped): void
    {
        self::assertSame($dropped, BacklogRuleSet::shouldDrop($input));
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function ojibweCases(): iterable
    {
        yield 'greeting' => ['Aanii', true];
        yield 'program word' => ['Niigaaniin', true];
        yield 'language name' => ['Anishinaabemowin', true];
        yield 'two ojibwe words' => ['Mino Bimaadiziwin', true];
        yield 'feast day' => ['Anishinaabe Giizhigad', true];
        yield 'embedded autonym is english proper noun' => ['Anishinabek Nation Governance Agreement', false];
        yield 'plain english' => ['Contact', false];
    }

    #[Test]
    #[DataProvider('ojibweCases')]
    public function it_classifies_wholly_anishinaabemowin_strings(string $input, bool $isOjibwe): void
    {
        self::assertSame($isOjibwe, BacklogRuleSet::isAnishinaabemowin($input));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function categoryCases(): iterable
    {
        yield 'chrome is global-ui' => ['Search', 'global-ui'];
        yield 'login is global-ui' => ['Log in', 'global-ui'];
        yield 'nav is governance' => ['Public Works', 'governance-nav'];
        yield 'services is governance' => ['Ontario Works', 'governance-nav'];
        yield 'social is other' => ['Facebook', 'other'];
        yield 'treaty is other' => ['Robinson Huron Treaty', 'other'];
        yield 'nation name is other' => ['Mississauga First Nation', 'other'];
    }

    #[Test]
    #[DataProvider('categoryCases')]
    public function it_categorises_governance_global_ui_and_other(string $input, string $category): void
    {
        self::assertSame($category, BacklogRuleSet::categoryFor($input));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function conceptCases(): iterable
    {
        yield 'trailing Us folds' => ['Contact Us', 'contact'];
        yield 'plain stays' => ['Contact', 'contact'];
        yield 'ampersand to and' => ['Chief & Council', 'chief and council'];
        yield 'and stays' => ['Chief and Council', 'chief and council'];
        yield 'leading Our folds' => ['Our History', 'history'];
        yield 'required marker stripped' => ['First Name*', 'first name'];
    }

    #[Test]
    #[DataProvider('conceptCases')]
    public function it_clusters_surface_forms_under_a_concept_key(string $input, string $concept): void
    {
        self::assertSame($concept, BacklogRuleSet::conceptKey($input));
    }

    #[Test]
    public function contact_and_contact_us_share_a_concept(): void
    {
        self::assertSame(BacklogRuleSet::conceptKey('Contact'), BacklogRuleSet::conceptKey('Contact Us'));
    }
}
