<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Games;

use App\Domain\Games\CrosswordThemes;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(CrosswordThemes::class)]
final class CrosswordThemesTest extends TestCase
{
    #[Test]
    public function registry_is_non_empty_so_the_themes_tab_is_never_blank(): void
    {
        $this->assertNotEmpty(CrosswordThemes::all());
        $this->assertNotEmpty(CrosswordThemes::slugs());
    }

    #[Test]
    public function every_theme_has_a_name_and_keywords(): void
    {
        foreach (CrosswordThemes::all() as $slug => $info) {
            $this->assertIsString($slug);
            $this->assertArrayHasKey('name', $info);
            $this->assertNotSame('', $info['name']);
            $this->assertArrayHasKey('keywords', $info);
            $this->assertNotEmpty($info['keywords'], "theme {$slug} has no keywords");
        }
    }

    #[Test]
    public function exists_distinguishes_known_from_unknown_slugs(): void
    {
        $this->assertTrue(CrosswordThemes::exists('animals'));
        $this->assertFalse(CrosswordThemes::exists('not-a-theme'));
    }

    #[Test]
    public function accessors_return_registry_values_and_fall_back_safely(): void
    {
        $this->assertSame('Animals', CrosswordThemes::name('animals'));
        $this->assertNotEmpty(CrosswordThemes::keywords('animals'));
        $this->assertSame([], CrosswordThemes::keywords('not-a-theme'));
        // Unknown name falls back to ucfirst of the slug.
        $this->assertSame('Mystery', CrosswordThemes::name('mystery'));
    }
}
