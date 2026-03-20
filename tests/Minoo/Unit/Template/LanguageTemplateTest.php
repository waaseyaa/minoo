<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Template;

use Minoo\Entity\DictionaryEntry;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Loader\ChainLoader;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;

#[CoversNothing]
final class LanguageTemplateTest extends TestCase
{
    private Environment $twig;

    protected function setUp(): void
    {
        $templateRoot = dirname(__DIR__, 4) . '/templates';
        $loader = new ChainLoader([
            new ArrayLoader([
                'base.html.twig' => '{% block title %}{% endblock %}{% block content %}{% endblock %}',
            ]),
            new FilesystemLoader($templateRoot),
        ]);

        $this->twig = new Environment($loader);
        $this->twig->addFunction(new TwigFunction('trans', static fn (string $key): string => $key));
        $this->twig->addFunction(new TwigFunction('lang_url', static fn (string $path): string => $path));
    }

    #[Test]
    public function it_renders_language_detail_definition_and_inflected_forms_once(): void
    {
        $entry = new DictionaryEntry([
            'word' => 'makwa',
            'slug' => 'makwa',
            'definition' => 'bear',
            'part_of_speech' => 'na',
            'attribution_source' => "Ojibwe People's Dictionary, University of Minnesota",
        ]);

        $html = $this->twig->render('language.html.twig', [
            'path' => '/language/makwa',
            'entry' => $entry,
            'inflected_forms' => ['plural: makwag', 'diminutive: makoons'],
        ]);

        $this->assertSame(1, substr_count($html, '>bear<'));
        $this->assertSame(1, substr_count($html, 'plural: makwag · diminutive: makoons'));
    }
}
