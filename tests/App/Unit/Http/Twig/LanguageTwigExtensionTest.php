<?php

declare(strict_types=1);

namespace App\Tests\Unit\Http\Twig;

use App\Http\Twig\LanguageTwigExtension;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(LanguageTwigExtension::class)]
final class LanguageTwigExtensionTest extends TestCase
{
    private LanguageTwigExtension $ext;

    protected function setUp(): void
    {
        $this->ext = new LanguageTwigExtension();
    }

    #[Test]
    public function cleanDefinitionUnwrapsJsonArray(): void
    {
        self::assertSame('bear', $this->ext->cleanDefinition('["bear"]'));
    }

    #[Test]
    public function cleanDefinitionJoinsMultiple(): void
    {
        self::assertSame('go somewhere; go over there', $this->ext->cleanDefinition('["go somewhere","go over there"]'));
    }

    #[Test]
    public function cleanDefinitionEmptyArrayRendersEmpty(): void
    {
        self::assertSame('', $this->ext->cleanDefinition('[]'));
    }

    #[Test]
    public function cleanDefinitionEmptyStringRendersEmpty(): void
    {
        self::assertSame('', $this->ext->cleanDefinition(''));
    }

    #[Test]
    public function cleanDefinitionPlainStringPassesThrough(): void
    {
        self::assertSame('a plain definition', $this->ext->cleanDefinition('a plain definition'));
    }

    #[Test]
    public function cleanDefinitionExpandsOpdAbbreviations(): void
    {
        self::assertSame('she/he sees someone', $this->ext->cleanDefinition('["s/he sees s.o."]'));
    }

    #[Test]
    public function dialectLabelMapsOpdSourceToSouthwesternOjibwe(): void
    {
        self::assertSame(
            'Southwestern Ojibwe',
            $this->ext->dialectLabel('oj', "Ojibwe People's Dictionary, University of Minnesota"),
        );
    }

    #[Test]
    public function dialectLabelMapsEasternAndOdawaCodes(): void
    {
        self::assertSame('Eastern Ojibwe', $this->ext->dialectLabel('ojg'));
        self::assertSame('Odawa', $this->ext->dialectLabel('otw'));
    }

    #[Test]
    public function dialectLabelFallsBackToAnishinaabemowin(): void
    {
        self::assertSame('Anishinaabemowin', $this->ext->dialectLabel('oj'));
    }
}
