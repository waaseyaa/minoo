<?php

declare(strict_types=1);

namespace App\Provider;

use App\Domain\Geo\Service\LocationService;
use App\Http\Twig\AccountDisplayTwigExtension;
use App\Http\Twig\DateTwigExtension;
use App\Http\Twig\LanguageTwigExtension;
use Twig\Environment;
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
            /** @var SearchProviderInterface $searchProvider */
            $searchProvider = $this->resolve(SearchProviderInterface::class);
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

        // Chat surface is cut; templates still read the global.
        $twigForChat = ThemeServiceProvider::getTwigEnvironment();
        if ($twigForChat !== null) {
            $twigForChat->addGlobal('chat_enabled', false);
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
    }
}
