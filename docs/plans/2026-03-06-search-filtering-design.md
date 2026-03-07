# Search & Filtering Across Entity Types

**Issue:** waaseyaa/minoo#10
**Date:** 2026-03-06
**Status:** Approved

## Problem

Minoo's public listing pages (events, groups, teachings, language) use hardcoded data in Twig templates. There is no search, filtering, or pagination. North Cloud production (`northcloud.one`) has 46,000+ indexed items including 7,000+ Indigenous-relevant articles from dozens of sources (MKO, Southern Chiefs Organization, ITK, NCTR, Nipissing First Nation, etc.).

## Architecture

Waaseyaa is the CMS/SSR layer (structure, rendering, admin, ingestion). NorthCloud is the search/classification layer (full-text search, facets, highlights, ranking, 46k+ items). They are complementary — Waaseyaa does not need to build a search engine, and NorthCloud does not need to be a CMS.

```
User -> /search?q=indigenous&topic=education&page=2
         |
    Waaseyaa SSR (RenderController)
         |
    NorthCloudSearchProvider (Minoo)
         | (checks 60s TTL cache first)
    northcloud.one/api/search (production API)
         |
    SearchResult DTO -> Twig template -> HTML
```

## Design Decisions

| Decision | Choice | Rationale |
|----------|--------|-----------|
| Search backend | NorthCloud production API | 46k+ indexed items, faceted search, highlights, already running |
| API consumption | Server-side PHP proxy | Keeps API URL internal, enables caching, fits SSR stack |
| Interface location | New `waaseyaa/packages/search/` | Clean separation; other apps can implement their own providers |
| Implementation location | `minoo/src/Search/` | NorthCloud-specific logic stays in the app, not the framework |
| Page interaction | Traditional form submission | Query params, no JS required, fully SSR, bookmarkable URLs |
| Caching | 60-second TTL, keyed by query+filters hash | API responses take 3-5s; cache dramatically improves UX |

## Layer 1: Waaseyaa Search Package

New package: `waaseyaa/packages/search/src/`

### SearchProviderInterface

```php
interface SearchProviderInterface
{
    public function search(SearchRequest $request): SearchResult;
}
```

### Value Objects

**SearchRequest** — query string, filters, page, page size

**SearchFilters** — topics (string[]), content_type (string), source (string), min_quality (int), from_date (DateTimeImmutable), to_date (DateTimeImmutable), sort_field (string), sort_order (string)

**SearchResult** — total_hits (int), total_pages (int), current_page (int), page_size (int), took_ms (int), hits (SearchHit[]), facets (SearchFacet[])

**SearchHit** — id, title, url, source_name, crawled_at, quality_score, content_type, topics (string[]), score, og_image, highlight

**SearchFacet** — name (string), buckets (FacetBucket[])

**FacetBucket** — key (string), count (int)

The framework defines the contract. Apps provide the implementation. This follows the same pattern as storage adapters, cache backends, and mailer drivers.

## Layer 2: Minoo Implementation

### minoo/src/Search/

**NorthCloudSearchProvider** (`implements SearchProviderInterface`)
- HTTP client (`file_get_contents` or cURL) to `northcloud.one/api/search`
- POST request with JSON body mapped from SearchRequest
- Parses JSON response into SearchResult DTOs
- 60-second TTL cache keyed by `sha256(serialize($request))`
- Error handling: returns empty SearchResult on API failure

**SearchServiceProvider**
- Registers `NorthCloudSearchProvider` as the `SearchProviderInterface` implementation in the container
- Reads API base URL from config

### Configuration

```php
// config/search.php
return [
    'provider' => 'northcloud',
    'northcloud' => [
        'base_url' => 'https://northcloud.one',
        'timeout' => 5,
        'cache_ttl' => 60,
    ],
];
```

## Layer 3: Frontend

### Templates

**templates/search.html.twig** (extends base.html.twig)
- Search form: text input + submit button (GET form to /search)
- Active filters display (removable badges)
- Facet filters (rendered from search results):
  - Topics: checkboxes (top 20 from facets)
  - Content type: checkboxes (article, page, event, etc.)
  - Sources: dropdown or checkboxes (top 20)
- Results: card grid using `search-result-card` component
- Result count and timing ("7,008 results in 3.2s")
- Pagination: prev/next links + page numbers, all as query param links

**templates/components/search-result-card.html.twig**
- Title (linked to original URL)
- Source name + date
- Excerpt or highlight snippet
- Topic badges
- Content type badge
- Thumbnail (og_image) if available

### CSS (public/css/minoo.css)

New components in `@layer components`:
- `.search-form` — input + button inline layout
- `.search-filters` — filter section (sidebar on wide screens, stacked on narrow)
- `.search-results` — results area with count header
- `.search-result-card` — extends card pattern for search-specific layout
- `.pagination` — page nav with current page indicator
- `.filter-badge` — active filter with remove link

### Navigation

Add "Search" link to `base.html.twig` nav.

### URL Structure

```
/search                                              -> empty search page
/search?q=indigenous                                 -> search results
/search?q=indigenous&topic=education&page=2           -> filtered + paginated
/search?q=indigenous&content_type=article             -> type filtered
/search?q=indigenous&source=Inuit+Tapiriit+Kanatami   -> source filtered
```

All state lives in query params. No JS required. Bookmarkable, shareable, back-button friendly.

## NorthCloud API Reference

The search API already provides everything needed:

```
POST https://northcloud.one/api/search
{
  "query": "indigenous",
  "filters": {
    "topics": ["education"],
    "content_type": "article",
    "min_quality_score": 50
  },
  "pagination": { "page": 1, "size": 20 },
  "options": {
    "include_facets": true,
    "include_highlights": true
  }
}
```

Response includes: hits with title/url/source/date/quality/topics/score/image, facets for topics/content_types/sources/quality_ranges, pagination metadata.

## Phase 2 (Separate Issues)

These are follow-up work, not part of this implementation:

1. **Convert listing pages to dynamic** — Replace hardcoded Twig data with NorthCloud API calls filtered by topic/content_type
2. **Per-page filters** — Add search/filter bars to events, groups, teachings, language pages
3. **Progressive enhancement** — Optional JS for instant filtering without page reload

## Testing

- Unit tests for all DTOs (SearchRequest, SearchResult, SearchHit, SearchFacet)
- Unit test for NorthCloudSearchProvider with mocked HTTP responses
- Integration test: boot kernel, resolve SearchProviderInterface from container
- Manual: verify /search page renders, query params work, pagination works, filters work
