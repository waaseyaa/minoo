<?php

declare(strict_types=1);

namespace Minoo\Provider;

use Minoo\Search\NorthCloudSearchProvider;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;
use Waaseyaa\Search\SearchProviderInterface;
use Waaseyaa\Search\Twig\SearchTwigExtension;
use Waaseyaa\SSR\SsrServiceProvider;

final class SearchServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $searchConfig = $this->config['search'] ?? [];

        $this->singleton(SearchProviderInterface::class, fn(): SearchProviderInterface => new NorthCloudSearchProvider(
            baseUrl: (string) ($searchConfig['base_url'] ?? 'https://northcloud.one'),
            timeout: (int) ($searchConfig['timeout'] ?? 5),
            cacheTtl: (int) ($searchConfig['cache_ttl'] ?? 60),
        ));
    }

    public function boot(): void
    {
        $twig = SsrServiceProvider::getTwigEnvironment();
        if ($twig === null) {
            return;
        }

        $searchConfig = $this->config['search'] ?? [];
        $provider = new NorthCloudSearchProvider(
            baseUrl: (string) ($searchConfig['base_url'] ?? 'https://northcloud.one'),
            timeout: (int) ($searchConfig['timeout'] ?? 5),
            cacheTtl: (int) ($searchConfig['cache_ttl'] ?? 60),
        );

        $twig->addExtension(new SearchTwigExtension($provider));
    }
}
