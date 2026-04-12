<?php

declare(strict_types=1);

namespace App\Provider;

use App\Search\NorthCloudSearchProvider;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;
use Waaseyaa\Search\SearchProviderInterface;
use Waaseyaa\Search\Twig\SearchTwigExtension;
use Waaseyaa\SSR\SsrServiceProvider;

final class SearchServiceProvider extends ServiceProvider
{
    private ?NorthCloudSearchProvider $provider = null;

    public function register(): void
    {
        $searchConfig = $this->config['search'] ?? [];

        $this->provider = new NorthCloudSearchProvider(
            baseUrl: (string) ($searchConfig['base_url'] ?? 'https://northcloud.one'),
            timeout: (int) ($searchConfig['timeout'] ?? 5),
            cacheTtl: (int) ($searchConfig['cache_ttl'] ?? 60),
        );

        $this->singleton(SearchProviderInterface::class, fn(): SearchProviderInterface => $this->provider);
    }

    public function boot(): void
    {
        $twig = SsrServiceProvider::getTwigEnvironment();
        if ($twig === null || $this->provider === null) {
            return;
        }

        $baseTopics = (array) ($this->config['search']['base_topics'] ?? []);
        $twig->addExtension(new SearchTwigExtension($this->provider, $baseTopics));
    }
}
