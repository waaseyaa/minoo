<?php

declare(strict_types=1);

namespace App\Search;

use Waaseyaa\Search\SearchProviderInterface;
use Waaseyaa\Search\SearchRequest;
use Waaseyaa\Search\SearchResult;

/**
 * Defers resolution of the framework search provider until the first query.
 *
 * As of Waaseyaa alpha.248 the framework search provider is access-checked: the
 * SearchProviderInterface factory transitively resolves
 * Waaseyaa\Access\EntityAccessHandler, which the kernel only composes in
 * discoverAccessPolicies() — and that runs AFTER bootProviders(). Because
 * AppBootServiceProvider registers the search Twig extension during boot()
 * (Twig locks its extension set on first render, so it cannot be added later),
 * resolving the provider eagerly there throws "No binding registered for
 * Waaseyaa\Access\EntityAccessHandler". This wrapper lets the extension be
 * registered at boot while the real provider — and its access-handler
 * dependency — is resolved on first search(), by which point the handler is
 * live. Mirrors the framework's own lazy access-handler accessors
 * (SqlEntityStorage::accessHandlerResolver).
 */
final class LazySearchProvider implements SearchProviderInterface
{
    /** @var \Closure(): SearchProviderInterface */
    private readonly \Closure $resolver;

    private ?SearchProviderInterface $delegate = null;

    /**
     * @param \Closure(): SearchProviderInterface $resolver
     */
    public function __construct(\Closure $resolver)
    {
        $this->resolver = $resolver;
    }

    public function search(SearchRequest $request): SearchResult
    {
        return ($this->delegate ??= ($this->resolver)())->search($request);
    }
}
