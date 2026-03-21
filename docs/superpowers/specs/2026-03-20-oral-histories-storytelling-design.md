# Oral Histories & Storytelling — Design Spec

**Milestone:** #37 — Oral Histories & Storytelling
**Date:** 2026-03-20
**Status:** Approved (brainstorm complete)
**Issues:** #352, #358, #364, #367, #371, #373

## Overview

Dedicated space for oral histories, traditional stories, and Elder narratives with proper attribution, cultural protocol awareness, and media support. Built on Minoo's existing entity architecture, following the teaching/cultural_collection pattern.

This milestone introduces:

- A unified `contributor` entity (migrated from `speaker`)
- `oral_history` and `oral_history_collection` content entities
- `oral_history_type` config entity
- Hybrid browse UX (collections + flat listing)
- Self-hosted audio/video with external embed fallback
- Cultural protocol system (soft guidance + living records for v1)

## Design Decisions

| Decision | Choice | Rationale |
|---|---|---|
| Collection model | Dedicated `oral_history_collection` | Oral history collections carry culturally distinct metadata (season, ceremony, story cycles) that `cultural_collection` was not designed for |
| Cultural protocols (v1) | Soft guidance + living records | Soft guidance respects knowledge without overstepping governance. Living records honor stories that should never be digitized. Seasonal hiding and access control deferred to v2 |
| Media hosting | Hybrid (self-hosted default + external embeds) | Indigenous data sovereignty requires self-hosting capability. External embeds allowed for already-public content |
| Narrator identity | Evolve `speaker` → `contributor` | A unified entity avoids duplication, supports all domains (language, oral histories, teachings), and respects that people have roles, not fixed labels |
| Page structure | Hybrid (collections + flat browse) | Supports multiple browse paths: by collection, by type, by contributor. Works with few or many stories |
| Implementation order | Entity-first (bottom-up) | Contributor migration is riskiest — do it first. Matches existing Minoo milestone patterns |

## Section 1: Contributor Migration

Rename `speaker` → `contributor`. This is the riskiest change and executes first.

### Field Schema

| Field | Type | Notes |
|---|---|---|
| `coid` | key | Entity key (was `sid`) |
| `name` | string | Unchanged from speaker |
| `slug` | string | New — for URL routing at `/contributors/{slug}` |
| `community_id` | integer | Unchanged — optional FK |
| `cultural_group_id` | integer | New — optional FK to cultural_group |
| `dialect` | string | Unchanged |
| `bio` | text_long | Unchanged |
| `clan` | string | New — optional |
| `role` | string | New — controlled set: Elder, Knowledge Keeper, Fluent Speaker, Storyteller, Youth, Community Member |
| `lineage_notes` | text_long | New — optional |
| `photo` | string | New — optional, file path |
| `consent_public` | boolean | New — default 0. Must be true for public profile visibility |
| `consent_record` | boolean | New — default 0. Consent to digital recording |
| `status` | integer | Unchanged — default 1 |

**Entity keys:** `['id' => 'coid', 'uuid' => 'uuid', 'label' => 'name']`

### Migration Steps

1. `ALTER TABLE speakers RENAME TO contributors`
2. Rename primary key column `sid` → `coid`
3. Update any foreign key indexes referencing `sid`
4. Add new columns: `slug`, `cultural_group_id`, `clan`, `role`, `lineage_notes`, `photo`, `consent_public`, `consent_record`
5. Backfill slugs from existing names
6. Update `LanguageServiceProvider`: register `contributor` instead of `speaker`
7. Update `LanguageAccessPolicy` attribute: replace `'speaker'` with `'contributor'` — full array becomes `['dictionary_entry', 'example_sentence', 'word_part', 'contributor', 'dialect_region']`
8. Update language templates: replace speaker references with contributor (field names for `name`, `community_id`, `dialect`, `bio` remain unchanged)

**Note:** `ContributorAccessPolicy` is the sole access policy for the `contributor` entity. `LanguageAccessPolicy` retains `contributor` in its array only because the Language domain still needs to register it as a known entity type — it does NOT duplicate the access gates. If the framework resolves policies per-entity-type (not per-provider), then remove `contributor` from `LanguageAccessPolicy` entirely and rely solely on `ContributorAccessPolicy`.

### What Does NOT Change

- `dictionary_entry`, `example_sentence`, `word_part` — untouched
- `dialect_region` — untouched
- Language template field names — same names, richer entity

## Section 2: Entity Types

### `oral_history_type` (config entity — extends `ConfigEntityBase`)

Bundle entity for `oral_history`. Same pattern as `teaching_type`.

| Field | Type | Notes |
|---|---|---|
| `type` | key | Entity key |
| `name` | string | Type label |
| `description` | text | Optional description |

**Entity keys:** `['id' => 'type', 'label' => 'name']`

**Seed values:** Creation Story, Historical Account, Personal Narrative, Land Teaching, Family Story.

### `oral_history_collection` (extends `ContentEntityBase`)

Standard content entity fields (`uuid`, `created_at`, `updated_at`) are inherited from `ContentEntityBase` and not listed below.

| Field | Type | Notes |
|---|---|---|
| `ohcid` | key | Entity key |
| `title` | string | Collection name |
| `slug` | string | URL-safe identifier |
| `description` | text_long | What this collection represents |
| `curator_notes` | text_long | Internal notes about curation decisions |
| `season` | string | Optional — controlled set: winter, spring, summer, fall, all |
| `ceremony_context` | string | Optional — which ceremony this relates to |
| `protocol_level` | string | "open", "guidance", "living_record" (v1). "seasonal", "restricted" reserved for v2 |
| `protocol_notes` | text_long | Displayed to visitors as soft guidance |
| `story_cycle_order_type` | string | "sequential" or "unordered" — whether stories have a prescribed telling order |
| `contributor_id` | integer | Optional FK — curator/Knowledge Keeper who assembled this collection |
| `community_id` | integer | Optional FK |
| `cultural_group_id` | integer | Optional FK |
| `status` | integer | Default 1 |

**Entity keys:** `['id' => 'ohcid', 'uuid' => 'uuid', 'label' => 'title']`

### `oral_history` (extends `ContentEntityBase`)

Standard content entity fields (`uuid`, `created_at`, `updated_at`) are inherited from `ContentEntityBase` and not listed below.

| Field | Type | Notes |
|---|---|---|
| `ohid` | key | Entity key |
| `title` | string | Story title |
| `slug` | string | URL-safe identifier |
| `type` | string | Bundle key → `oral_history_type` |
| `content` | text_long | Transcript text (empty for living records) |
| `summary` | text | Short description for cards and living-record pages |
| `contributor_id` | integer | Optional FK → `contributor` (primary narrator) |
| `narrator_name` | string | Fallback text attribution when no contributor entity exists |
| `collection_id` | integer | Optional FK → `oral_history_collection` |
| `story_order` | integer | Position within collection (null if standalone) |
| `community_id` | integer | Optional FK |
| `cultural_group_id` | integer | Optional FK |
| `season` | string | Optional — inherits from collection or overrides |
| `protocol_level` | string | "open", "guidance", "living_record" |
| `protocol_notes` | text_long | Soft guidance text shown to visitors |
| `is_living_record` | boolean | True = no transcript, no media, placeholder only |
| `media_type` | string | "self_hosted", "external", null |
| `media_path` | string | Path to self-hosted file (e.g., `storage/media/oral-histories/{ohid}-{slug}.mp3`) |
| `media_url` | string | External embed URL |
| `media_duration` | integer | Duration in seconds, for display |
| `media_format` | string | "audio" or "video" |
| `recorded_date` | string | When the recording was made (string to support partial dates like "Winter 2021", "circa 1980s") |
| `consent_public` | boolean | Narrator consented to public display |
| `consent_record` | boolean | Narrator consented to digital recording |
| `tags` | string | Comma-separated, for filtering |
| `status` | integer | Default 1 |

**Entity keys:** `['id' => 'ohid', 'uuid' => 'uuid', 'label' => 'title', 'bundle' => 'type']`

### Table Names

Following Minoo's pluralized convention: `oral_histories`, `oral_history_collections`, `oral_history_types`, `contributors`.

**Slug uniqueness:** `slug` fields on `contributor`, `oral_history`, and `oral_history_collection` carry a unique constraint per entity type.

### Relationships

- `oral_history` → `oral_history_collection` (many-to-one, optional)
- `oral_history` → `oral_history_type` (bundle, required)
- `oral_history` → `contributor` (many-to-one, optional with `narrator_name` fallback)
- `oral_history_collection` → `contributor` (many-to-one, optional curator)

## Section 3: Access Policies

### `OralHistoryAccessPolicy`

**Covers:** `['oral_history', 'oral_history_type', 'oral_history_collection']`

| Gate | Logic |
|---|---|
| view | `status === 1`. Admin bypasses. Living record logic stays in the template layer — see rationale below. |
| create | Admin only. Neutral for non-admin. |
| update | Admin only. |
| delete | Admin only. |

**Why living records are not gated at the access layer:** A living record is not "restricted access" — it is "this story exists but was never digitized." The entity is public. The absence of content is the point, not a permission decision. Putting this in the access policy would conflate "no content" with "no permission," which muddies the model for v2 when real access control (seasonal, membership-based) arrives.

### `ContributorAccessPolicy`

**Covers:** `['contributor']`

| Gate | Logic |
|---|---|
| view | `status === 1` AND `consent_public === true`. Admin bypasses. |
| create | Admin only. |
| update | Admin only. |
| delete | Admin only. |

**Consent-gated visibility:** Contributors must explicitly consent to public visibility. A contributor can exist in the system (referenced by oral histories via `contributor_id`) without being publicly browseable. When an oral history references a contributor with `consent_public === false`, the template shows `narrator_name` text instead of linking to a contributor profile.

## Service Providers

### `OralHistoryServiceProvider`

Registers in its `register()` method:
- `oral_history` entity type (content)
- `oral_history_type` entity type (config)
- `oral_history_collection` entity type (content)

Routes registered in `register()` — see Section 4 for details.

### `ContributorServiceProvider`

New provider, replacing `speaker` registration in `LanguageServiceProvider`:
- Registers `contributor` entity type (content)
- Routes: `/contributors/{slug}` — GET, allowAll, render
- Template: `templates/contributors.html.twig` (stub profile page for v1 — name, bio, community, role)

**Note:** Contributor profile pages are minimal in v1 — name, bio, community, role. Full profiles are v2.

## Section 4: Page Templates & Components

All routes handled by a single `oral-histories.html.twig` with conditional branches, matching the teachings pattern.

### Routes

| Route | Template Branch | Description |
|---|---|---|
| `/oral-histories` | listing | Featured collections + all stories grid with tabs (Recent, By Type, By Contributor) |
| `/oral-histories/collections/{slug}` | collection detail | Protocol notice, curator attribution, ordered story list |
| `/oral-histories/{slug}` | story detail | Protocol notice, audio/video player, transcript, collection prev/next nav |
| `/oral-histories/{slug}` (living record) | living record variant | Placeholder with summary, no media/transcript |

### Route Registration

In `OralHistoryServiceProvider` (register more-specific routes first):
1. `/oral-histories` — GET, allowAll, render
2. `/oral-histories/collections/{slug}` — GET, allowAll, render (slug requirement: `[a-z0-9][a-z0-9-]*[a-z0-9]`)
3. `/oral-histories/{slug}` — GET, allowAll, render (slug requirement: `[a-z0-9][a-z0-9-]*[a-z0-9]`)

**Route ordering:** `/oral-histories/collections/{slug}` must be registered before `/oral-histories/{slug}` so the framework matches the literal `collections` segment before falling through to the wildcard slug. The slug "collections" is reserved and must not be used as an oral history slug.

### Page Descriptions

**Listing page (`/oral-histories`):**
- Hero section: "Our Stories" heading + descriptive text
- Collections section: grid of `collection-card` components
- All Stories section: grid of `oral-history-card` components with tab filtering (Recent, By Type, By Contributor)
- Living record cards use dashed border + italic "This story lives in oral tradition"

**Collection detail (`/oral-histories/collections/{slug}`):**
- Breadcrumb: Oral Histories → {collection title}
- Collection header: title, description, season/protocol badges, story count, order type
- Protocol notice (if `protocol_level !== 'open'`)
- Curator attribution (links to `/contributors/{slug}` if `consent_public`)
- Ordered story list: numbered if `story_cycle_order_type === 'sequential'`, unnumbered otherwise
- Living records in the list show dashed style + italic text

**Story detail (`/oral-histories/{slug}`):**
- Breadcrumb: Oral Histories → {collection} → {story title}
- Story header: type badge, title, narrator attribution, community, recorded date
- Protocol notice (if `protocol_level !== 'open'`)
- Audio/video player (if media exists)
- Transcript section (paragraphs split by `\n\n`)
- Collection prev/next navigation (if story belongs to a collection)

**Living record detail (`/oral-histories/{slug}`, `is_living_record === true`):**
- Breadcrumb: same as story detail
- Header: "Living Record" type badge (amber), title, narrator
- Centered placeholder: feather icon, "This story lives in oral tradition" message
- Summary section: "About This Story" with the `summary` field
- Collection prev/next navigation (if applicable)

### Reusable Components

**`components/oral-history-card.html.twig`**
- Input: title, type, narrator_name, contributor (optional), community_name, community_slug, summary, url, media_format, media_duration, is_living_record, tags
- Displays: type badge, title (linked), narrator, community (linked), duration + format indicator, excerpt from summary
- Living record variant: dashed border, italic "lives in oral tradition" instead of duration

**`components/collection-card.html.twig`**
- Input: title, description, slug, season, protocol_level, story_count, curator_name, curator_slug
- Displays: title, description excerpt, season badge, protocol badge, story count, curator (linked if consent_public), "View collection →" link

### Narrator Attribution Logic

Templates must check contributor consent before linking:
- If `contributor` exists AND `contributor.consent_public === true`: link narrator name to `/contributors/{slug}`
- If `contributor` exists but `consent_public === false`: show `contributor.name` as plain text
- If no `contributor`: show `narrator_name` as plain text

## Section 5: Media Handling

### Storage

- Self-hosted files: `storage/media/oral-histories/{ohid}-{slug}.{ext}`
- Accepted audio formats: `.mp3`, `.wav`, `.ogg`
- Accepted video formats: `.mp4`, `.webm`
- Max file size: configurable, 500MB default
- No transcoding in v1 — accept uploaded format, validate on save
- Upload is admin-only (matches create/update access policy)

### Player

- HTML5 `<audio>` / `<video>` elements — no JavaScript library, no build step
- `media_format` field determines which element to render
- Self-hosted: `<audio src="/media/oral-histories/{file}" controls>` (or `<video>`)
- External: `<iframe>` for YouTube/Vimeo, `<audio>` with direct URL for hosted audio
- Minimal custom CSS in `@layer components` for consistent styling
- No autoplay — visitor initiates playback
- No download button — intentional sovereignty decision (controlling distribution)

### What v1 Does NOT Include

- No transcoding or format conversion
- No thumbnail generation for video
- No waveform visualization
- No playback speed controls

## Section 6: Protocol Notice Component

### Component

`components/protocol-notice.html.twig`

**Input:** any entity with `protocol_level` and `protocol_notes` fields.

### Rendering Logic

| `protocol_level` | Behavior |
|---|---|
| `"open"` | No notice rendered |
| `"guidance"` | Amber left-border bar with "Cultural note:" prefix + `protocol_notes` text |
| `"living_record"` | Dashed amber border box, feather icon, "This story lives in oral tradition" + summary |

### CSS

Two modifier classes in `@layer components` in `minoo.css`:
- `.protocol-notice--guidance` — amber left border, light amber background
- `.protocol-notice--living-record` — dashed amber border, centered text

### Usage

- Collection detail page: shows collection-level protocol
- Story detail page: shows story-level protocol (may differ from collection)
- Story cards: living records get dashed border + italic text (not the full notice component)

### v2 Hooks

The `protocol_level` field accepts additional values for future enforcement:
- `"seasonal"` — date-range visibility gating (requires governance decisions + UX design)
- `"restricted"` — access-controlled viewing (requires auth + membership roles)

These values are reserved in the data model but not rendered or enforced in v1.

## Implementation Order

Following the entity-first (bottom-up) approach, aligned with existing milestone issues:

1. **Contributor migration** — rename `speaker` → `contributor`, add fields, update Language domain (#367)
2. **`oral_history_type` config entity** — bundle entity + seed data (#352)
3. **`oral_history_collection` entity** — collection with protocol + cycle fields (#358)
4. **`oral_history` entity** — full schema with media + protocol + living record fields (#352)
5. **Access policies** — `OralHistoryAccessPolicy` + `ContributorAccessPolicy` (#352)
6. **Listing + detail templates** — hybrid browse page, collection detail, story detail (#364)
7. **Attribution component** — narrator display with consent-gated linking (#367)
8. **Media upload + player** — self-hosted storage, HTML5 player, external embed support (#371)
9. **Protocol notice component** — soft guidance + living record rendering (#373)
10. **Seed data + polish** — sample oral histories, collections, types for demo

## Out of Scope (v2+)

- Seasonal time-gating (requires community governance decisions)
- Access-controlled viewing (requires auth + membership infrastructure)
- Media transcoding / format conversion
- Waveform visualization / advanced player controls
- Download controls
- Full-text search within transcripts
- Contributor self-service profiles
