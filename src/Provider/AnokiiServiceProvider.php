<?php

declare(strict_types=1);

namespace App\Provider;

use Anokii\Config\DistributionConfig;
use App\Anokii\Ingest\MediaFetcher;
use App\Anokii\Ingest\YtDlpMediaFetcher;
use Twig\Environment;
use Twig\Loader\ChainLoader;
use Twig\Loader\FilesystemLoader;
use Waaseyaa\SSR\SsrServiceProvider;
use Waaseyaa\SSR\ThemeServiceProvider;

/**
 * Anokii distribution wiring (Tier 3-lite, #851).
 *
 * Minoo embeds only the Anokii *shell* (the themeable admin chrome) and
 * deliberately does NOT register Anokii\Provider\CoIntelligenceServiceProvider.
 * That keeps Anokii's graph entity types (incl. a `community` type that would
 * collide with Minoo's own), its keyword-RAG chat routes, and its single-admin
 * auth out of the build. Minoo owns auth (role-gated routes) and the entities.
 *
 * This provider has three jobs:
 *   1. Register the Anokii distribution switch ({@see DistributionConfig}) as a
 *      singleton so module providers added in later milestone issues can resolve
 *      it. Minoo does not register Anokii\Provider\CoIntelligenceServiceProvider,
 *      so it owns this binding itself, matching the contract that provider
 *      expects.
 *   2. Make the package's Twig templates resolvable under the `@anokii`
 *      namespace, so Minoo pages can `{% extends "@anokii/_shell.html.twig" %}`
 *      without forking the shell.
 *   3. Apply Minoo theming by exposing the brand identity and a block of
 *      `--anokii-*` / `--color-*` token overrides (the Minoo palette) as Twig
 *      globals, which the extending template injects into the shell's
 *      `shell_styles` block. The shell is token-driven, so rebranding is purely
 *      an override of these custom properties; the template is never forked.
 *
 * No routes, entity types, modules, commands, or middleware are mounted here; the
 * `/admin/anokii` surface is mounted by {@see Routing\AnokiiRouteProvider}, and
 * config gating (job 1) does not turn anything on by itself.
 */
final class AnokiiServiceProvider extends AppCoreServiceProvider
{
    /** Twig namespace the Anokii package templates are exposed under. */
    private const string TEMPLATE_NAMESPACE = 'anokii';

    /**
     * Register the Anokii distribution switch as a singleton.
     *
     * A missing config/anokii.yaml resolves to the sovereign safe default, so
     * this binding is correct whether or not the file is present. It does not
     * mount or enable anything: `DistributionConfig::moduleEnabled('language')`
     * stays false until a later issue flips the flag.
     */
    public function register(): void
    {
        $this->singleton(
            DistributionConfig::class,
            static fn (): DistributionConfig => DistributionConfig::fromFile(
                dirname(__DIR__, 2) . '/config/anokii.yaml',
            ),
        );

        // The reel-import media fetcher (#904), resolved by the Ingest controller.
        // yt-dlp-backed and fail-closed; swap the binding to change the extractor.
        $this->singleton(MediaFetcher::class, static fn (): MediaFetcher => new YtDlpMediaFetcher());
    }

    public function boot(): void
    {
        $templateDir = dirname(__DIR__, 2) . '/vendor/waaseyaa/anokii/templates/anokii';
        if (!is_dir($templateDir)) {
            // Package not installed (or layout changed), fail soft so the rest
            // of the app still boots; the route provider's render will surface
            // the missing template clearly if the surface is hit.
            return;
        }

        $themeCss = $this->minooThemeTokens();

        foreach ($this->twigEnvironments() as $twig) {
            $this->registerTemplateNamespace($twig, $templateDir);
            $this->registerPackageRoot($twig, dirname($templateDir));
            // Brand identity + theme tokens consumed by templates/pages/anokii/*.
            $twig->addGlobal('anokii_brand_title', 'Minoo');
            $twig->addGlobal('anokii_brand_tag', 'Language Workspace');
            $twig->addGlobal('anokii_theme_css', $themeCss);
        }
    }

    /**
     * The SSR and theme Twig environments, de-duplicated by object identity.
     *
     * SsrServiceProvider re-uses the ThemeServiceProvider environment, so this
     * is usually a single instance; the dedup keeps the loop correct either way.
     *
     * @return list<Environment>
     */
    private function twigEnvironments(): array
    {
        $environments = [];
        foreach ([SsrServiceProvider::getTwigEnvironment(), ThemeServiceProvider::getTwigEnvironment()] as $env) {
            if ($env instanceof Environment) {
                $environments[spl_object_id($env)] = $env;
            }
        }

        return array_values($environments);
    }

    /**
     * Register $dir under the `@anokii` namespace on the given environment's
     * loader. Idempotent: re-registration of the same path is skipped.
     */
    private function registerTemplateNamespace(Environment $twig, string $dir): void
    {
        $loader = $twig->getLoader();

        if ($loader instanceof FilesystemLoader) {
            if (!in_array($dir, $loader->getPaths(self::TEMPLATE_NAMESPACE), true)) {
                $loader->addPath($dir, self::TEMPLATE_NAMESPACE);
            }

            return;
        }

        if ($loader instanceof ChainLoader) {
            // The SSR/theme loader is a ChainLoader of plain FilesystemLoaders
            // (default namespace only). Add a dedicated namespaced loader to the
            // chain so `@anokii/...` resolves without touching the existing ones.
            $namespaced = new FilesystemLoader();
            $namespaced->addPath($dir, self::TEMPLATE_NAMESPACE);
            $loader->addLoader($namespaced);
        }
    }

    /**
     * Add the package templates root to the main loader so the package admin
     * templates' own non-namespaced references resolve (the catalog dashboard
     * does `{% extends "anokii/admin/_base.html.twig" %}` and
     * `{% include "anokii/_dashboard_grid.html.twig" %}`). Mirrors how
     * fnpi-waaseyaa exposes the package templates as a fallback. Minoo's own
     * templates load first in the chain, so nothing of minoo's is shadowed.
     */
    private function registerPackageRoot(Environment $twig, string $dir): void
    {
        $loader = $twig->getLoader();

        if ($loader instanceof FilesystemLoader) {
            if (!in_array($dir, $loader->getPaths(), true)) {
                $loader->addPath($dir);
            }

            return;
        }

        if ($loader instanceof ChainLoader) {
            $root = new FilesystemLoader();
            $root->addPath($dir);
            $loader->addLoader($root);
        }
    }

    /**
     * Minoo palette as `--color-*` overrides for the Anokii shell.
     *
     * The shell aliases its `--anokii-shell-*` tokens to these generic
     * `--color-*` custom properties (with neutral grey fallbacks), so overriding
     * them rebrands the chrome. Light by default with a dark-scheme override,
     * mirroring Minoo's own light/dark handling. Values track public/css/tokens.css
     * (language teal accent, earth-tone surfaces). CSS is inline in the shell, so
     * there is no external stylesheet to cache-bust here.
     */
    private function minooThemeTokens(): string
    {
        return <<<'CSS'
:root{
  --color-primary: #2a9d8f;        /* Minoo "language" teal */
  --color-primary-ink: #ffffff;
  --color-bg: #f5f0eb;             /* surface-light */
  --color-card: #ffffff;
  --color-ink: #1a1a1a;
  --color-line: #e0dbd4;          /* border-light */
  --color-muted: #6b6b6b;
  --color-side-bg: #0a0a0a;       /* surface-dark sidebar */
  --color-side-line: #1e1e1e;
  --color-side-muted: #9a958d;
}
@media (prefers-color-scheme: dark){
  :root{
    --color-bg: #0a0a0a;
    --color-card: #141414;
    --color-ink: #f0ece6;
    --color-line: #1e1e1e;
    --color-muted: #888888;
  }
}
CSS;
    }
}
