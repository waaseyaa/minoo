<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Ingest;

use Minoo\Support\SlugGenerator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(SlugGenerator::class)]
final class SlugGeneratorTest extends TestCase
{
    #[Test]
    public function it_generates_slug_from_simple_string(): void
    {
        $this->assertSame('makwa', SlugGenerator::generate('makwa'));
    }

    #[Test]
    public function it_lowercases_and_replaces_spaces(): void
    {
        $this->assertSame('hello-world', SlugGenerator::generate('Hello World'));
    }

    #[Test]
    public function it_strips_special_characters(): void
    {
        $this->assertSame('makw', SlugGenerator::generate('/makw-/'));
    }

    #[Test]
    public function it_trims_leading_and_trailing_hyphens(): void
    {
        $this->assertSame('test', SlugGenerator::generate('--test--'));
    }

    #[Test]
    public function it_returns_empty_string_for_empty_input(): void
    {
        $this->assertSame('', SlugGenerator::generate(''));
    }
}
