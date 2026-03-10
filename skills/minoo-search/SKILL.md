---
name: minoo:search
description: Use when working on Minoo search, autocomplete, or NorthCloud search integration in src/Search/ or SearchServiceProvider
---

# Minoo Search Specialist

## Scope

Files: `src/Search/`, `src/Provider/SearchServiceProvider.php`
Tests: `tests/Minoo/Unit/Search/`

## Architecture

Minoo delegates search to NorthCloud — no local indexing. Two clients handle different search needs:

- **NorthCloudSearchProvider** — full-text search with facets and highlights
- **CommunityAutocompleteClient** — community name suggestions for location bar

## NorthCloudSearchProvider

```php
final class NorthCloudSearchProvider implements SearchProviderInterface
{
    public function __construct(
        string $baseUrl,
        int $timeout = 15,
        int $cacheTtl = 60,
        ?callable $httpClient = null,  // injectable for testing
    );

    public function search(SearchRequest $request): SearchResult;
}
```

**URL construction:**
```
GET {baseUrl}/api/v1/search?q={query}&page={page}&page_size={pageSize}
  &include_facets=1&include_highlights=1
  &content_type={filter}        // optional
  &min_quality_score={score}    // optional
  &topics[]={topic}             // array params with bracket notation
  &source_names[]={source}      // array params with bracket notation
```

**Response model:**
- `SearchResult`: totalHits, totalPages, currentPage, pageSize, tookMs, hits[], facets[]
- `SearchHit`: id, title, url, sourceName, crawledAt, qualityScore, contentType, topics[], score, ogImage, highlight
- `SearchFacet`: name, buckets[] (key + count)
- Highlight extraction: prefers `body[0]`, falls back to `raw_text[0]`, then `title[0]`

**Caching:** In-memory TTL cache keyed by `SearchRequest::cacheKey()`. Duplicate requests within TTL skip HTTP.

**Error handling:** HTTP failures return `SearchResult::empty()` — graceful degradation, never throws.

## CommunityAutocompleteClient

```php
final class CommunityAutocompleteClient
{
    public function suggest(string $query, int $limit = 10): array;
    // Returns: [{id, name, community_type, province}, ...]
    // Endpoint: GET {baseUrl}/api/communities/search?q={query}&page_size={limit}
}
```

Same caching and error handling pattern as search provider.

## Registration

**SearchServiceProvider:**
- `register()`: Creates `NorthCloudSearchProvider`, binds to `SearchProviderInterface` singleton
- `boot()`: Registers `SearchTwigExtension` — adds `search(q, {content_type, source}, page)` Twig function

**Configuration (config/waaseyaa.php):**
```php
'search' => [
    'base_url'    => env('NORTHCLOUD_SEARCH_URL', 'https://northcloud.one'),
    'timeout'     => env('NORTHCLOUD_SEARCH_TIMEOUT', 15),
    'cache_ttl'   => env('NORTHCLOUD_SEARCH_CACHE_TTL', 60),
    'base_topics' => ['indigenous'],  // always included, overrides user filters
],
```

## Testing Patterns

Injectable `httpClient` callable for deterministic tests:
```php
$provider = new NorthCloudSearchProvider(
    baseUrl: 'https://example.com',
    httpClient: fn(string $url): string|false => json_encode($fixture),
);
```

**Key test scenarios:**
1. Response parsing — JSON fixture → SearchResult/SearchHit assertions
2. URL construction — capture URL in callable, parse query string, assert params
3. Error handling — `httpClient` returns `false` → `SearchResult::empty()`
4. Caching — track call count, second call with same request doesn't invoke httpClient
5. Array params — assert URL contains `topics%5B%5D=` (bracket encoding)

## Common Mistakes

- **Forgetting `base_topics`**: Config `base_topics` are always prepended — don't duplicate in request
- **Bracket notation in URLs**: Array params use `topics[]=value` not `topics=value`
- **Cache key collisions**: Different SearchRequest instances with same params share cache — this is intentional
- **HTML in highlights**: Highlights may contain `<em>` tags — templates must use `|raw` filter
- **Empty query**: Empty search string is valid — returns recent/popular results

## Related Specs

- `docs/specs/search.md` — full search architecture, facet types, config reference
- Framework: `waaseyaa_get_spec api-layer` — routing, controller wiring
