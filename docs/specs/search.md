# Search Specification

## File Map

| File | Purpose |
|------|---------|
| `src/Search/NorthCloudSearchProvider.php` | HTTP client for NorthCloud search API with in-memory cache |
| `src/Provider/SearchServiceProvider.php` | Registers search provider + Twig extension |
| `config/waaseyaa.php` (`search` key) | Search base URL, timeout, cache TTL, base topics |
| `templates/search.html.twig` | Search page with form, facet sidebar, results, pagination |
| `templates/components/search-result-card.html.twig` | Individual search result card component |

## Architecture

```
SearchServiceProvider
  register() → NorthCloudSearchProvider(baseUrl, timeout, cacheTtl)
             → singleton(SearchProviderInterface::class)
  boot()     → SearchTwigExtension(provider, baseTopics) → Twig
```

The search page uses a Twig function `search(query, filters, page)` provided by the framework's `SearchTwigExtension`. This calls `NorthCloudSearchProvider::search(SearchRequest)` which makes an HTTP GET to NorthCloud's `/api/v1/search` endpoint.

## Interface: SearchProviderInterface

```php
interface SearchProviderInterface {
    public function search(SearchRequest $request): SearchResult;
}
```

Implemented by `NorthCloudSearchProvider`.

## NorthCloudSearchProvider

```php
final class NorthCloudSearchProvider implements SearchProviderInterface
  __construct(string $baseUrl, int $timeout, int $cacheTtl, ?callable $httpClient = null)
  search(SearchRequest $request): SearchResult
```

### Constructor Parameters
- `baseUrl`: NorthCloud API base (default: `https://northcloud.one`)
- `timeout`: HTTP request timeout in seconds (default: 15)
- `cacheTtl`: In-memory cache TTL in seconds (default: 60, 0 disables)
- `httpClient`: Optional callable for testing (`fn(string $url): string|false`)

### Caching
In-memory array cache keyed by `SearchRequest::cacheKey()`. TTL-based expiration. Cache is per-request (not persistent across PHP processes).

### Query URL Building
```
GET {baseUrl}/api/v1/search?q={query}&page={page}&page_size={pageSize}&include_facets=1&include_highlights=1
  &content_type={filter}      // optional
  &min_quality_score={score}  // optional
  &topics[]={topic}           // repeated, from filters + base_topics
  &source_names[]={source}    // repeated
```

Array params use explicit bracket notation (`topics[]`, `source_names[]`) for Go's query parser.

### Response Parsing
Maps JSON response to framework value objects:
- `SearchResult(totalHits, totalPages, currentPage, pageSize, tookMs, hits[], facets[])`
- `SearchHit(id, title, url, sourceName, crawledAt, qualityScore, contentType, topics[], score, ogImage, highlight)`
- `SearchFacet(name, buckets[])` where `FacetBucket(key, count)`

### Highlight Extraction
API returns highlights as either a string or `{body: [...], raw_text: [...], title: [...]}`. Provider prefers `body[0]`, falls back to `raw_text[0]`, then `title[0]`.

## Configuration

```php
// config/waaseyaa.php
'search' => [
    'base_url'    => getenv('NORTHCLOUD_SEARCH_URL') ?: 'https://northcloud.one',
    'timeout'     => (int)(getenv('NORTHCLOUD_SEARCH_TIMEOUT') ?: 15),
    'cache_ttl'   => (int)(getenv('NORTHCLOUD_SEARCH_CACHE_TTL') ?: 60),
    'base_topics' => ['indigenous'],  // Always included in topic filters
],
```

`base_topics` is the indigenous content filter — ensures all searches are scoped to indigenous-relevant content. Passed to `SearchTwigExtension` which **overrides** (not merges) user topic selection to prevent OR-logic leaking non-Indigenous content.

**Note:** NorthCloud's GET endpoint does not yet honor the `topics[]` filter param — the filtering is wired but not yet effective server-side. See `docs/plans/2026-03-07-indigenous-content-filter.md` for the Phase 2 plan using `indigenous_relevance` field.

## Search Template

`templates/search.html.twig` — extends `base.html.twig`

### Template Variables (from query params)
- `q`: search query (`query_param('q')`)
- `content_type`: type filter (`query_param('content_type')`)
- `source`: source filter (`query_param('source')`)
- `page`: page number (`query_param('page', '1')`)

### Template Structure
1. Search form (GET `/search?q=...`)
2. Active filter badges (removable links)
3. Two-column layout:
   - Sidebar: facet filters (`content_types`, `sources`)
   - Main: result count + timing, card grid, pagination
4. Empty state when no query

### Twig Function
```twig
{% set results = search(q, {content_type: content_type, source: source}, page) %}
```
Returns `SearchResult` object. Access `results.hits`, `results.facets`, `results.totalHits`, etc.
Facet lookup: `results.getFacet('content_types')` returns `SearchFacet|null`.

## Edge Cases

- HTTP failure returns `SearchResult::empty()` (graceful degradation)
- Non-array JSON response returns `SearchResult::empty()`
- Highlight field can be string, array-of-arrays, or missing — all handled
- `base_topics` array ensures indigenous content scoping even with no user filters
- Sources facet limited to first 20 buckets in template (`sourcesFacet.buckets[:20]`)
- Pagination shows 5-page window centered on current page with ellipsis

## Testing Patterns

- Injectable `$httpClient` callable replaces real HTTP in tests
- Tests verify URL construction, response parsing, caching, and error handling
- Mock responses use static JSON fixtures matching NorthCloud API format
- Cache tests verify TTL expiration and cache key uniqueness
