<?php

declare(strict_types=1);

namespace Minoo\Tests\Integration;

use Minoo\Provider\I18nServiceProvider;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Foundation\Kernel\AbstractKernel;
use Waaseyaa\Foundation\Kernel\HttpKernel;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;
use Waaseyaa\I18n\LanguageManagerInterface;
use Waaseyaa\I18n\TranslatorInterface;

#[CoversNothing]
final class I18nSmokeTest extends TestCase
{
    private static string $projectRoot;
    private static HttpKernel $kernel;
    private static I18nServiceProvider $i18nProvider;

    public static function setUpBeforeClass(): void
    {
        self::$projectRoot = dirname(__DIR__, 3);

        $cachePath = self::$projectRoot . '/storage/framework/packages.php';
        if (is_file($cachePath)) {
            unlink($cachePath);
        }

        putenv('WAASEYAA_DB=:memory:');

        self::$kernel = new HttpKernel(self::$projectRoot);
        $boot = new \ReflectionMethod(AbstractKernel::class, 'boot');
        $boot->invoke(self::$kernel);

        // Access providers via reflection to find I18nServiceProvider
        $prop = new \ReflectionProperty(AbstractKernel::class, 'providers');
        /** @var list<ServiceProvider> $providers */
        $providers = $prop->getValue(self::$kernel);

        foreach ($providers as $provider) {
            if ($provider instanceof I18nServiceProvider) {
                self::$i18nProvider = $provider;
                break;
            }
        }
    }

    public static function tearDownAfterClass(): void
    {
        putenv('WAASEYAA_DB');

        $cachePath = self::$projectRoot . '/storage/framework/packages.php';
        if (is_file($cachePath)) {
            unlink($cachePath);
        }
    }

    #[Test]
    public function language_manager_is_registered(): void
    {
        $manager = self::$i18nProvider->resolve(LanguageManagerInterface::class);
        $this->assertNotNull($manager);
        $this->assertInstanceOf(LanguageManagerInterface::class, $manager);
    }

    #[Test]
    public function translator_is_registered(): void
    {
        $translator = self::$i18nProvider->resolve(TranslatorInterface::class);
        $this->assertNotNull($translator);
        $this->assertInstanceOf(TranslatorInterface::class, $translator);
    }

    #[Test]
    public function english_translation_returns_value(): void
    {
        /** @var TranslatorInterface $translator */
        $translator = self::$i18nProvider->resolve(TranslatorInterface::class);
        $result = $translator->trans('nav.communities', locale: 'en');
        $this->assertSame('Communities', $result);
    }

    #[Test]
    public function ojibwe_translation_falls_back_to_english_when_empty(): void
    {
        /** @var TranslatorInterface $translator */
        $translator = self::$i18nProvider->resolve(TranslatorInterface::class);

        // chat.toggle_label is '' in oj.php — should fall back to English
        $result = $translator->trans('chat.toggle_label', locale: 'oj');
        $this->assertNotEmpty($result, 'Empty oj translation should fall back to English');
    }

    #[Test]
    public function default_language_is_english(): void
    {
        /** @var LanguageManagerInterface $manager */
        $manager = self::$i18nProvider->resolve(LanguageManagerInterface::class);
        $default = $manager->getDefaultLanguage();
        $this->assertSame('en', $default->id);
    }

    #[Test]
    public function ojibwe_language_is_available(): void
    {
        /** @var LanguageManagerInterface $manager */
        $manager = self::$i18nProvider->resolve(LanguageManagerInterface::class);
        $languages = $manager->getLanguages();
        $this->assertArrayHasKey('oj', $languages);
    }
}
