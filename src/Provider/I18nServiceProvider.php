<?php

declare(strict_types=1);

namespace Minoo\Provider;

use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;
use Waaseyaa\I18n\Language;
use Waaseyaa\I18n\LanguageManager;
use Waaseyaa\I18n\LanguageManagerInterface;
use Waaseyaa\I18n\Translator;
use Waaseyaa\I18n\TranslatorInterface;
use Waaseyaa\I18n\Twig\TranslationTwigExtension;
use Waaseyaa\Routing\Language\UrlPrefixNegotiator;
use Waaseyaa\SSR\SsrServiceProvider;

final class I18nServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Register available languages
        $this->singleton(LanguageManagerInterface::class, function (): LanguageManagerInterface {
            return new LanguageManager([
                new Language('en', 'English', isDefault: true),
                new Language('oj', 'Anishinaabemowin'),
            ]);
        });

        // Register translator
        $this->singleton(TranslatorInterface::class, function (): TranslatorInterface {
            $langPath = dirname(__DIR__, 2) . '/resources/lang';
            /** @var LanguageManagerInterface $manager */
            $manager = $this->resolve(LanguageManagerInterface::class);
            return new Translator($langPath, $manager);
        });

        // Register URL prefix negotiator
        $this->singleton(UrlPrefixNegotiator::class, fn() => new UrlPrefixNegotiator());
    }

    public function boot(): void
    {
        // Add the translation Twig extension
        /** @var TranslatorInterface $translator */
        $translator = $this->resolve(TranslatorInterface::class);
        /** @var LanguageManagerInterface $manager */
        $manager = $this->resolve(LanguageManagerInterface::class);

        $extension = new TranslationTwigExtension($translator, $manager);
        $twig = SsrServiceProvider::getTwigEnvironment();
        if ($twig !== null) {
            $twig->addExtension($extension);
        }
    }
}
