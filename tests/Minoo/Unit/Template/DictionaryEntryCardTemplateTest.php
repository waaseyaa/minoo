<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Template;

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
    }

    #[Test]
    public function it_renders_attribution_link_when_provided(): void
    {
        $html = $this->twig->render('components/dictionary-entry-card.html.twig', [
            'word' => 'makwa',
            'definition' => 'bear',
            'part_of_speech' => 'na',
            'attribution_source' => "Ojibwe People's Dictionary, University of Minnesota",
            'attribution_url' => 'https://ojibwe.lib.umn.edu/main-entry/makwa-na',
        ]);

        $this->assertStringContainsString('Ojibwe People&#039;s Dictionary, University of Minnesota', $html);
        $this->assertStringContainsString('https://ojibwe.lib.umn.edu/main-entry/makwa-na', $html);
    }

    #[Test]
    public function it_omits_attribution_block_when_not_provided(): void
    {
        $html = $this->twig->render('components/dictionary-entry-card.html.twig', [
            'word' => 'makwa',
            'definition' => 'bear',
            'part_of_speech' => 'na',
        ]);

        $this->assertStringNotContainsString('card__meta', $html);
        $this->assertStringNotContainsString('Ojibwe People', $html);
    }

    #[Test]
    public function it_renders_plain_text_attribution_when_url_is_missing(): void
    {
        $html = $this->twig->render('components/dictionary-entry-card.html.twig', [
            'word' => 'makwa',
            'definition' => 'bear',
            'part_of_speech' => 'na',
            'attribution_source' => "Ojibwe People's Dictionary, University of Minnesota",
        ]);

        $this->assertStringContainsString('Ojibwe People&#039;s Dictionary, University of Minnesota', $html);
        $this->assertStringNotContainsString('<a href=', $html);
    }
}
