<?php

declare(strict_types=1);

namespace App\Tests\Unit\Template;

use App\Http\Twig\LanguageTwigExtension;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

#[CoversNothing]
final class DictionaryEntryCardTemplateTest extends TestCase
{
    private Environment $twig;

    protected function setUp(): void
    {
        $loader = new FilesystemLoader(dirname(__DIR__, 4) . '/templates');
        $this->twig = new Environment($loader);
        $this->twig->addExtension(new LanguageTwigExtension());
    }

    #[Test]
    public function it_renders_clean_definition_from_json_array(): void
    {
        $html = $this->twig->render('components/domain/language/entry-card.html.twig', [
            'word' => 'makwa',
            'definition' => '["bear"]',
            'part_of_speech' => 'na',
            'language_code' => 'oj',
            'attribution_source' => "Ojibwe People's Dictionary, University of Minnesota",
        ]);

        $this->assertStringContainsString('bear', $html);
        $this->assertStringNotContainsString('["bear"]', $html);
    }

    #[Test]
    public function it_shows_dialect_label_for_opd_source(): void
    {
        $html = $this->twig->render('components/domain/language/entry-card.html.twig', [
            'word' => 'makwa',
            'definition' => '["bear"]',
            'language_code' => 'oj',
            'attribution_source' => "Ojibwe People's Dictionary, University of Minnesota",
        ]);

        $this->assertStringContainsString('card__meta', $html);
        $this->assertStringContainsString('Southwestern Ojibwe', $html);
    }

    #[Test]
    public function it_does_not_repeat_the_attribution_link_on_cards(): void
    {
        // The repeated per-card attribution link was retired in favour of a
        // single source credit at the page level (#788). Even when an
        // attribution URL is available, the card must not render it.
        $html = $this->twig->render('components/domain/language/entry-card.html.twig', [
            'word' => 'makwa',
            'definition' => '["bear"]',
            'language_code' => 'oj',
            'attribution_source' => "Ojibwe People's Dictionary, University of Minnesota",
            'attribution_url' => 'https://ojibwe.lib.umn.edu/main-entry/makwa-na',
        ]);

        $this->assertStringNotContainsString('ojibwe.lib.umn.edu', $html);
        $this->assertStringNotContainsString("Ojibwe People's Dictionary, University of Minnesota", $html);
    }

    #[Test]
    public function it_renders_empty_definition_without_raw_json(): void
    {
        $html = $this->twig->render('components/domain/language/entry-card.html.twig', [
            'word' => 'makwa',
            'definition' => '[]',
            'language_code' => 'oj',
        ]);

        $this->assertStringNotContainsString('[]', $html);
        $this->assertStringContainsString('makwa', $html);
    }
}
