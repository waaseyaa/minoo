<?php

declare(strict_types=1);

namespace App\Provider;

use App\Domain\Geo\Service\LocationService;
use App\Http\Twig\AccountDisplayTwigExtension;
use App\Http\Twig\DateTwigExtension;
use App\Http\Twig\LanguageTwigExtension;
use App\Search\LazySearchProvider;
use Twig\Environment;
use Twig\TwigFunction;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Entity\Event\EntityEvent;
use Waaseyaa\Entity\Event\EntityEvents;
use Waaseyaa\I18n\LanguageManagerInterface;
use Waaseyaa\I18n\TranslatorInterface;
use Waaseyaa\I18n\Twig\TranslationTwigExtension;
use Waaseyaa\Media\UploadHandler;
use Waaseyaa\Search\SearchProviderInterface;
use Waaseyaa\Search\Twig\SearchTwigExtension;
use Waaseyaa\SSR\SsrServiceProvider;
use Waaseyaa\SSR\ThemeServiceProvider;

/**
 * Language-platform slimming (2026-06): group business-bundle fields,
 * genealogy field merges, chat globals, and feed affinity-cache listeners
 * removed alongside their surfaces.
 */
class AppBootServiceProvider extends AppCoreServiceProvider
{
    public function register(): void
    {
        // Location-as-vantage-point (#816): resolves a member's chosen place into
        // a coarse community centroid for the feed + graph.
        $this->singleton(LocationService::class, fn (): LocationService => new LocationService(
            $this->resolve(EntityTypeManager::class),
        ));

        // Image uploads for post creation (#813).
        $this->singleton(UploadHandler::class, fn (): UploadHandler => new UploadHandler(
            dirname(__DIR__, 2) . '/storage/uploads',
        ));
    }

    public function boot(): void
    {
        // =====================================================================
        // --- I18n: Translation Twig extension ---
        // =====================================================================

        /** @var TranslatorInterface $translator */
        $translator = $this->resolve(TranslatorInterface::class);
        /** @var LanguageManagerInterface $manager */
        $manager = $this->resolve(LanguageManagerInterface::class);

        $extension = new TranslationTwigExtension($translator, $manager);
        $twig = SsrServiceProvider::getTwigEnvironment();
        if ($twig !== null) {
            $twig->addExtension($extension);
            $twig->addExtension(new LanguageTwigExtension());
        }

        // =====================================================================
        // --- Search: SearchTwigExtension (framework FTS5 provider) ---
        // =====================================================================

        $twigForSearch = SsrServiceProvider::getTwigEnvironment();
        if ($twigForSearch !== null) {
            // alpha.248: the framework search provider is access-checked, so its
            // factory resolves EntityAccessHandler — which the kernel only composes
            // in discoverAccessPolicies(), AFTER bootProviders(). Defer the provider
            // (and that dependency) to first query via LazySearchProvider so the
            // extension can still be registered here at boot.
            $searchProvider = new LazySearchProvider(
                fn (): SearchProviderInterface => $this->resolve(SearchProviderInterface::class),
            );
            $baseTopics = (array) ($this->config['search']['base_topics'] ?? []);
            $twigForSearch->addExtension(new SearchTwigExtension($searchProvider, $baseTopics));
        }

        // =====================================================================
        // --- Flash: DateTwigExtension + AccountDisplayTwigExtension ---
        // =====================================================================

        $twigForFlash = ThemeServiceProvider::getTwigEnvironment();
        if ($twigForFlash !== null) {
            $twigForFlash->addExtension(new DateTwigExtension());
            $twigForFlash->addExtension(new AccountDisplayTwigExtension());
        }

        // =====================================================================
        // --- Games: game_session updated_at on PRE_SAVE ---
        // =====================================================================

        $dispatcher = $this->resolve(\Symfony\Contracts\EventDispatcher\EventDispatcherInterface::class);
        $dispatcher->addListener(EntityEvents::PRE_SAVE->value, static function (EntityEvent $event): void {
            if ($event->entity->getEntityTypeId() === 'game_session') {
                $event->entity->set('updated_at', time());
            }
        });

        // =====================================================================
        // --- Twig: site_base_url for og:image / og:url absolute URLs ---
        // =====================================================================

        $siteBase = rtrim((string) ($this->config['mail']['base_url'] ?? 'https://minoo.live'), '/');
        /** @var array<int, Environment> $twigTargets */
        $twigTargets = [];
        foreach ([SsrServiceProvider::getTwigEnvironment(), ThemeServiceProvider::getTwigEnvironment()] as $env) {
            if ($env instanceof Environment) {
                $twigTargets[spl_object_id($env)] = $env;
            }
        }
        foreach ($twigTargets as $env) {
            $env->addGlobal('site_base_url', $siteBase);
        }

        // Per-page OG card by convention: og_image_path('/games') returns
        // /img/og/games.png?v=<hash> when that card exists (rendered by
        // scripts/generate-og.js), else /img/og-default.png. The slug rule
        // mirrors the generator ('/' => 'home', '/' => '-'). The ?v content hash
        // busts the CDN edge cache when a card is regenerated. base.html.twig
        // calls this for og:image, so a static page gets its own card without
        // per-template wiring.
        $ogPublicDir = dirname(__DIR__, 2) . '/public';
        $ogImagePath = new TwigFunction('og_image_path', static function (string $seoPath) use ($ogPublicDir): string {
            $slug = trim(strtolower((string) preg_replace('#[^a-zA-Z0-9/_-]+#', '', $seoPath)), '/');
            $slug = $slug === '' ? 'home' : str_replace('/', '-', $slug);
            $card = $ogPublicDir . '/img/og/' . $slug . '.png';
            if (is_file($card)) {
                $version = substr((string) hash_file('crc32b', $card), 0, 8);

                return '/img/og/' . $slug . '.png?v=' . $version;
            }

            return '/img/og-default.png';
        });
        foreach ($twigTargets as $env) {
            $env->addFunction($ogImagePath);
        }

        // =====================================================================
        // --- Twig: current_path() for sidebar nav active-state (#922) ---
        // =====================================================================

        // Registered as a render-time function — NOT a boot-time global —
        // because boot() runs once per kernel while one booted kernel can
        // serve many requests (the integration harness does exactly that), so
        // the value must be derived per render. Same lazy per-request pattern
        // as lang_url(). A language prefix is stripped (/oj/communities →
        // /communities) so template comparisons match in every language.
        $currentPath = new TwigFunction('current_path', static function () use ($manager): string {
            $path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
            if (!is_string($path) || $path === '') {
                return '/';
            }
            $firstSegment = explode('/', ltrim($path, '/'))[0];
            if ($firstSegment !== '' && array_key_exists($firstSegment, $manager->getLanguages())) {
                $stripped = substr($path, strlen('/' . $firstSegment));
                $path = $stripped === '' ? '/' : $stripped;
            }

            return $path;
        });
        foreach ($twigTargets as $env) {
            $env->addFunction($currentPath);
        }
    }
}
