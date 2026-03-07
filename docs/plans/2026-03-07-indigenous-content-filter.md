# Indigenous Content Hard Filter

## Context

Minoo is an Indigenous knowledge platform. Its search queries NorthCloud's 46k+ item corpus which includes general news, CNET articles, recipe sites, etc. NorthCloud has a rich Indigenous classification pipeline (indigenous-ml sidecar, keyword rules, relevance levels) but Minoo wasn't using any of it.

## Decision

Hard filter — always send `topics: ['indigenous']` to NorthCloud. Users cannot disable it.

## Architecture

### NorthCloud Classification Pipeline

```
crawler → classifier → indigenous-ml sidecar → ES index
                        ├── topic: "indigenous" (keyword match + ML)
                        └── indigenous.relevance: "core_indigenous" | "peripheral" | "not_indigenous"
```

### Filter Semantics (OR problem)

NorthCloud's `topics` filter uses ES `terms` query (OR logic). Sending `topics: ['indigenous', 'education']` matches docs with EITHER topic, leaking non-Indigenous education content.

**Solution:** `baseTopics` in `SearchTwigExtension` overrides (not merges) user topic selection. Topic facet removed from UI since clicking it would be misleading.

### Cross-Repo Contract

| Layer | Repo | What |
|-------|------|------|
| `baseTopics` param | waaseyaa/search | Framework-level override mechanism |
| `base_topics` config | minoo | App-level configuration (`['indigenous']`) |
| `topics` filter | NorthCloud POST API | **Not yet implemented** — POST body `filters.topics` is silently ignored. GET `topics[]` works. |

### Current State (Phase 1)

- `baseTopics=['indigenous']` is sent in every search request via GET `topics[]=indigenous`
- Minoo switched from POST to GET API (POST `filters.topics` silently ignored, GET `topics[]` also currently ignored but is the correct contract)
- Topics facet removed from search UI
- "Showing Indigenous content only" scope indicator shown
- **NorthCloud ignores topics filter on both GET and POST** — actual filtering pending NorthCloud fix (issue to be filed)

### Phase 2: `indigenous_relevance` API Filter

Add `indigenous_relevance[]` filter to NorthCloud's search API (follows existing `crime_relevance` pattern). This enables:
- Core-only filtering (no peripheral content)
- Re-enabling topic facets (safe with AND semantics on separate field)
- Switch config: `base_topics: ['indigenous']` → `indigenous_relevance: ['core_indigenous']`

## Files Changed

| Repo | File | Change |
|------|------|--------|
| waaseyaa | `packages/search/src/Twig/SearchTwigExtension.php` | `baseTopics` constructor param |
| waaseyaa | `packages/search/tests/Unit/Twig/SearchTwigExtensionTest.php` | 3 new tests |
| minoo | `src/Provider/SearchServiceProvider.php` | Pass `baseTopics` from config |
| minoo | `config/waaseyaa.php` | `base_topics: ['indigenous']` |
| minoo | `templates/search.html.twig` | Remove topics facet, add scope indicator |
| minoo | `public/css/minoo.css` | Scope indicator styles |
