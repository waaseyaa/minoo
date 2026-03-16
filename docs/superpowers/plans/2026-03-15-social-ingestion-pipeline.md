# Minoo Social Ingestion Pipeline — Milestone & Issue Plan

> Facebook/Instagram → Minoo ingestion pipeline. Meta-compliant, production-grade.

## Milestone 1: Meta Integration Layer

**Goal:** Implement OAuth flow, token lifecycle, and permission management for Meta Graph API.

---

### Issue 1.1: Meta OAuth login flow

**Title:** `feat: implement Meta OAuth login flow`

**Description:**
Add a Meta OAuth controller that redirects to Facebook Login, handles the callback, exchanges the authorization code for a short-lived token, and stores the authenticated user's meta_user_id.

**Acceptance Criteria:**
- [ ] `/auth/meta/login` redirects to Facebook OAuth dialog with correct `client_id`, `redirect_uri`, and `scope`
- [ ] `/auth/meta/callback` exchanges authorization code for short-lived token via Graph API
- [ ] Error states handled: user denies permissions, invalid code, network failure
- [ ] Returns to admin dashboard with success/failure flash message

**Technical Notes:**
- Controller: `src/Controller/MetaAuthController.php`
- Use `curl` or Symfony HttpClient — no Meta SDK dependency
- Graph API endpoint: `https://graph.facebook.com/v21.0/oauth/access_token`
- Config: `meta.app_id`, `meta.app_secret`, `meta.redirect_uri` in `config/waaseyaa.php`
- CSRF state parameter stored in session

**Dependencies:** None

---

### Issue 1.2: Request required Meta permissions

**Title:** `feat: request required Meta Graph API permissions in OAuth scope`

**Description:**
Configure the OAuth login flow to request all required permissions for Page and Instagram ingestion.

**Acceptance Criteria:**
- [ ] OAuth scope includes: `instagram_basic`, `instagram_manage_insights`, `instagram_manage_comments`, `pages_show_list`, `pages_read_engagement`, `pages_manage_posts`
- [ ] Granted permissions stored in `social_accounts.permissions_granted` as JSON array
- [ ] Partial permission grants detected and flagged (user deselects some)

**Technical Notes:**
- Graph API `me/permissions` endpoint returns granted vs. declined
- Store as JSON array for drift detection later

**Dependencies:** 1.1

---

### Issue 1.3: Token exchange — short-lived to long-lived

**Title:** `feat: exchange short-lived Meta token for long-lived token`

**Description:**
After OAuth callback, exchange the short-lived token (~1 hour) for a long-lived token (~60 days) via the Graph API token exchange endpoint.

**Acceptance Criteria:**
- [ ] Short-lived token exchanged for long-lived token via `GET /oauth/access_token?grant_type=fb_exchange_token`
- [ ] Long-lived token and `expires_at` stored in `social_accounts` table
- [ ] Exchange failure logged and surfaced to user

**Technical Notes:**
- Endpoint: `https://graph.facebook.com/v21.0/oauth/access_token?grant_type=fb_exchange_token&client_id={app_id}&client_secret={app_secret}&fb_exchange_token={short_lived}`
- Response includes `access_token` and `expires_in` (seconds)
- Compute `expires_at` as `now() + expires_in`

**Dependencies:** 1.1

---

### Issue 1.4: Token refresh scheduler

**Title:** `feat: implement Meta token refresh CLI command`

**Description:**
Create a CLI command `bin/refresh-meta-tokens` that refreshes long-lived tokens before they expire. Long-lived tokens can be refreshed by re-exchanging them while still valid.

**Acceptance Criteria:**
- [ ] `bin/refresh-meta-tokens` finds all tokens expiring within 7 days
- [ ] Re-exchanges each valid token for a new long-lived token
- [ ] Updates `access_token` and `expires_at` in database
- [ ] Supports `--dry-run` flag
- [ ] Logs refresh success/failure per account

**Technical Notes:**
- Follow existing CLI pattern from `bin/sync-dictionary`
- Long-lived tokens can only be refreshed while still valid — once expired, user must re-authenticate
- Designed to run as cron job (daily)

**Dependencies:** 1.3, 5.1

---

### Issue 1.5: Token expiry detection and alerts

**Title:** `feat: detect expired Meta tokens and surface warnings`

**Description:**
Add token health checking that detects expired or soon-to-expire tokens, and surfaces warnings in the admin dashboard.

**Acceptance Criteria:**
- [ ] `bin/check-meta-tokens` reports token status for all social accounts
- [ ] Tokens expiring within 7 days flagged as WARNING
- [ ] Expired tokens flagged as CRITICAL
- [ ] Invalid tokens detected via Graph API `me?access_token=` probe
- [ ] Output compatible with monitoring/alerting tools (exit code 1 on critical)

**Technical Notes:**
- Probe endpoint: `GET https://graph.facebook.com/v21.0/me?access_token={token}` — returns user info or error
- Error code 190 = expired/invalid token

**Dependencies:** 1.4, 5.1

---

### Issue 1.6: Permission drift detection

**Title:** `feat: detect Meta permission drift`

**Description:**
Periodically check that all required permissions are still granted. Users can revoke permissions via Facebook settings at any time.

**Acceptance Criteria:**
- [ ] `bin/check-meta-tokens` also queries `me/permissions` to verify granted permissions
- [ ] Missing permissions logged and flagged
- [ ] `social_accounts.permissions_granted` updated to reflect current state
- [ ] Required vs. granted comparison against configured permission list

**Technical Notes:**
- Graph API `me/permissions` returns: `[{"permission": "pages_show_list", "status": "granted"}, ...]`
- Compare against required permissions defined in config
- Can be combined with token health check command (1.5)

**Dependencies:** 1.2, 1.5

---

### Issue 1.7: MetaApiClient support class

**Title:** `feat: implement MetaApiClient for Graph API calls`

**Description:**
Create a reusable HTTP client for Meta Graph API calls, following the existing `NorthCloudClient` pattern.

**Acceptance Criteria:**
- [ ] `src/Support/MetaApiClient.php` — final class with typed methods
- [ ] Handles token injection, error parsing, rate limit detection
- [ ] Methods: `get(endpoint, params)`, `getPages(token)`, `getInstagramAccounts(pageId, token)`, `getPagePosts(pageId, token, since)`, `getPageEvents(pageId, token)`, `getInstagramMedia(igUserId, token, since)`
- [ ] Rate limit headers parsed and logged (`x-app-usage`, `x-business-use-case-usage`)
- [ ] Graph API errors mapped to exceptions with error code + message

**Technical Notes:**
- Follow `NorthCloudClient` pattern: constructor takes `baseUrl`, `timeout`
- Base URL: `https://graph.facebook.com/v21.0`
- All requests include `access_token` query parameter
- JSON response parsing with error detection (`error.code`, `error.message`)

**Dependencies:** None

---

## Milestone 2: Account Discovery Layer

**Goal:** Discover and store Facebook Pages and Instagram accounts, map them to Minoo communities.

---

### Issue 2.1: Fetch Facebook Pages

**Title:** `feat: discover Facebook Pages managed by authenticated user`

**Description:**
After OAuth, query the Graph API to discover all Facebook Pages the authenticated user manages.

**Acceptance Criteria:**
- [ ] `MetaApiClient::getPages()` fetches `me/accounts` with fields: `id,name,access_token,category,fan_count`
- [ ] Each Page stored in `social_accounts` with `platform=facebook`, `external_id=page_id`
- [ ] Page-level access tokens stored (these don't expire if user has long-lived token)
- [ ] Pagination handled for users managing many Pages

**Technical Notes:**
- `me/accounts` returns Pages with individual page access tokens
- Page tokens inherit the user token's expiry — long-lived user token → long-lived page tokens
- Fields: `id`, `name`, `access_token`, `category`, `fan_count`, `picture`

**Dependencies:** 1.3, 1.7, 5.1

---

### Issue 2.2: Fetch Instagram Business/Creator accounts

**Title:** `feat: discover Instagram accounts linked to Facebook Pages`

**Description:**
For each Facebook Page, discover the linked Instagram Business or Creator account.

**Acceptance Criteria:**
- [ ] `MetaApiClient::getInstagramAccounts()` fetches `{page_id}?fields=instagram_business_account`
- [ ] Instagram account metadata stored in `social_accounts` with `platform=instagram`
- [ ] IG account fields: `id`, `username`, `name`, `profile_picture_url`, `followers_count`
- [ ] Pages without IG accounts handled gracefully (skip)

**Technical Notes:**
- Only Business/Creator IG accounts are accessible via Graph API (not personal)
- IG account uses the Page's access token for API calls
- Endpoint: `{page_id}?fields=instagram_business_account{id,username,name,profile_picture_url,followers_count}`

**Dependencies:** 2.1

---

### Issue 2.3: Store Page/IG account metadata

**Title:** `feat: store social account metadata in social_accounts table`

**Description:**
Persist discovered Page and Instagram account metadata with platform, external IDs, tokens, and sync state.

**Acceptance Criteria:**
- [ ] `social_accounts` entity type registered via `SocialAccountServiceProvider`
- [ ] Fields: `platform`, `external_id`, `name`, `access_token`, `expires_at`, `permissions_granted`, `community_id`, `metadata` (JSON), `last_synced_at`, `status`
- [ ] Entity class: `src/Entity/SocialAccount.php`
- [ ] Access policy: `SocialAccountAccessPolicy` — admin only
- [ ] Unit tests for entity and provider

**Technical Notes:**
- Follow existing entity registration pattern (Provider + Entity + AccessPolicy)
- `metadata` stores platform-specific data as JSON (fan_count, category, followers_count, etc.)
- `community_id` is the entity_reference to link the account to a Minoo community

**Dependencies:** None (schema only)

---

### Issue 2.4: Community mapping UI

**Title:** `feat: admin UI to map social accounts to communities`

**Description:**
Add an admin page where users can link discovered Facebook Pages and Instagram accounts to Minoo communities.

**Acceptance Criteria:**
- [ ] `/admin/social-accounts` lists all discovered accounts with platform, name, and current community mapping
- [ ] Each account has a dropdown to select a Minoo community (autocomplete)
- [ ] Saving updates `social_accounts.community_id`
- [ ] Unmapped accounts flagged visually
- [ ] CSRF protection on form submission

**Technical Notes:**
- Controller: `src/Controller/SocialAccountController.php`
- Template: `templates/admin/social-accounts.html.twig`
- Community autocomplete can reuse existing `/api/communities/autocomplete` endpoint
- POST handler updates community_id on the social_account entity

**Dependencies:** 2.3

---

### Issue 2.5: Auto-match accounts to communities by name similarity

**Title:** `feat: auto-suggest community matches for social accounts`

**Description:**
When a new social account is discovered, suggest matching Minoo communities based on name similarity.

**Acceptance Criteria:**
- [ ] `SocialAccountMatcher::suggestCommunity(accountName)` returns top-3 community matches
- [ ] Uses normalized string comparison (lowercase, stripped punctuation)
- [ ] Suggestions shown in the mapping UI (2.4) as pre-selected default
- [ ] No auto-assignment — user must confirm

**Technical Notes:**
- `src/Support/SocialAccountMatcher.php`
- Simple approach: `similar_text()` or Levenshtein distance on normalized names
- Compare against all active communities — acceptable for ~700 communities

**Dependencies:** 2.3, 2.4

---

## Milestone 3: Ingestion Jobs

**Goal:** Implement scheduled sync workers that pull content from Facebook and Instagram via Graph API.

---

### Issue 3.1: Facebook Page posts ingestion

**Title:** `feat: ingest Facebook Page posts via Graph API`

**Description:**
Create a sync command that fetches posts from connected Facebook Pages and stores them as raw social media items.

**Acceptance Criteria:**
- [ ] `bin/sync-social --platform=facebook --type=posts` fetches recent posts
- [ ] Uses `{page_id}/posts` endpoint with fields: `id,message,created_time,updated_time,full_picture,attachments,permalink_url,type`
- [ ] Posts stored in `social_media_items` with `external_id`, `external_source=facebook`, `item_type=post`, `raw_payload`
- [ ] Supports `--dry-run`, `--since=DATE`, `--community=SLUG`
- [ ] Pagination via Graph API cursors
- [ ] Deduplication by `external_id + external_source`

**Technical Notes:**
- Follow `bin/sync-dictionary` CLI pattern
- `since` parameter uses Unix timestamp for `since` query param
- Default: last 90 days for initial sync, last sync timestamp for incremental
- Store `raw_payload` as full JSON response for reprocessing

**Dependencies:** 1.7, 2.3, 5.2

---

### Issue 3.2: Facebook Page events ingestion

**Title:** `feat: ingest Facebook Page events via Graph API`

**Description:**
Fetch events from connected Facebook Pages and store as raw social media items.

**Acceptance Criteria:**
- [ ] `bin/sync-social --platform=facebook --type=events` fetches events
- [ ] Uses `{page_id}/events` endpoint with fields: `id,name,description,start_time,end_time,place,cover,updated_time,event_times`
- [ ] Events stored in `social_media_items` with `item_type=event`
- [ ] Recurring events handled (event_times array)
- [ ] Deduplication by `external_id + external_source`

**Technical Notes:**
- Events endpoint may require `pages_read_engagement` permission
- `place` is a nested object with `name`, `location.latitude`, `location.longitude`
- `cover` contains the event image URL

**Dependencies:** 3.1 (shares CLI infrastructure)

---

### Issue 3.3: Facebook photos and videos ingestion

**Title:** `feat: ingest Facebook Page photos and videos via Graph API`

**Description:**
Fetch photos and videos from connected Facebook Pages.

**Acceptance Criteria:**
- [ ] `bin/sync-social --platform=facebook --type=media` fetches photos and videos
- [ ] Uses `{page_id}/photos` and `{page_id}/videos` endpoints
- [ ] Photo fields: `id,name,images,created_time,updated_time,album`
- [ ] Video fields: `id,title,description,source,thumbnails,created_time,updated_time`
- [ ] Stored in `social_media_items` with `item_type=photo` or `item_type=video`

**Technical Notes:**
- `images` array contains multiple sizes — store the largest for display
- Video `source` is the direct MP4 URL (may expire)
- Consider downloading media to local storage in a separate issue

**Dependencies:** 3.1

---

### Issue 3.4: Instagram media ingestion

**Title:** `feat: ingest Instagram media via Graph API`

**Description:**
Fetch media (images, videos, carousels, reels) from connected Instagram Business accounts.

**Acceptance Criteria:**
- [ ] `bin/sync-social --platform=instagram --type=media` fetches media
- [ ] Uses `{ig_user_id}/media` endpoint with fields: `id,caption,media_type,media_url,thumbnail_url,timestamp,permalink,children{media_url,media_type}`
- [ ] Stored in `social_media_items` with `item_type=image|video|carousel|reel`
- [ ] Carousel children stored as JSON array in `raw_payload`
- [ ] Deduplication by `external_id + external_source`

**Technical Notes:**
- `media_type`: `IMAGE`, `VIDEO`, `CAROUSEL_ALBUM`
- Reels appear as `VIDEO` with specific properties
- `media_url` expires after a period — download or cache URLs
- Children endpoint for carousels: `{media_id}/children?fields=media_url,media_type`

**Dependencies:** 3.1, 2.2

---

### Issue 3.5: Incremental sync with delta detection

**Title:** `feat: implement incremental sync with delta detection`

**Description:**
After initial full sync, subsequent runs only fetch new or updated content using timestamps.

**Acceptance Criteria:**
- [ ] `social_accounts.last_synced_at` tracked per account per content type
- [ ] Incremental sync uses `since` parameter (Facebook) or timestamp comparison (Instagram)
- [ ] Updated items detected via `updated_time` comparison and re-fetched
- [ ] `--full` flag forces full re-sync (ignores last_synced_at)
- [ ] Sync state persisted even on partial failure (resume from last success)

**Technical Notes:**
- Facebook supports `since` Unix timestamp on feed endpoints
- Instagram doesn't support `since` — paginate backwards and stop when hitting already-synced items
- Store sync cursor/state in `social_accounts.metadata` JSON

**Dependencies:** 3.1, 3.4

---

### Issue 3.6: Deleted content detection

**Title:** `feat: detect deleted posts and events from Meta platforms`

**Description:**
Detect when posts/events have been deleted on Facebook/Instagram and mark them accordingly in Minoo.

**Acceptance Criteria:**
- [ ] During full sync, items in `social_media_items` not present in API response flagged as `status=deleted`
- [ ] Deleted items not removed from DB — soft-deleted with `deleted_at` timestamp
- [ ] Materialized entities (events/teachings) derived from deleted items flagged for review
- [ ] `bin/sync-social --detect-deletions` explicitly checks for removed content

**Technical Notes:**
- Compare local `external_id` set against API response set during full sync
- Individual item check: `GET /{id}` returns error 100 for deleted objects
- Don't auto-unpublish materialized entities — flag for admin review

**Dependencies:** 3.5

---

### Issue 3.7: Unified sync orchestrator command

**Title:** `feat: unified bin/sync-social command with platform and type routing`

**Description:**
Create the top-level `bin/sync-social` CLI command that routes to platform-specific sync logic.

**Acceptance Criteria:**
- [ ] `bin/sync-social` — syncs all platforms, all types
- [ ] `bin/sync-social --platform=facebook` — syncs all Facebook content
- [ ] `bin/sync-social --platform=instagram` — syncs all Instagram content
- [ ] `bin/sync-social --type=posts` — syncs posts across all platforms
- [ ] `bin/sync-social --community=SLUG` — syncs only accounts mapped to community
- [ ] `--dry-run`, `--full`, `--since=DATE` flags
- [ ] Summary output: created/updated/skipped/errors per account

**Technical Notes:**
- Follow `bin/sync-dictionary` bootstrap pattern
- Route to platform-specific sync classes: `FacebookSyncer`, `InstagramSyncer`
- Each syncer handles its own pagination and API calls
- Designed to run as cron job (every 15 minutes)

**Dependencies:** 3.1, 3.2, 3.3, 3.4, 3.5

---

### Issue 3.8: Rate limit handling and backoff

**Title:** `feat: handle Meta API rate limits with exponential backoff`

**Description:**
Implement rate limit detection and backoff to avoid hitting Meta's API limits.

**Acceptance Criteria:**
- [ ] Parse `x-app-usage` and `x-business-use-case-usage` response headers
- [ ] When usage > 80%, slow down requests (add delay)
- [ ] When usage > 95%, pause and wait for window reset
- [ ] Rate limit events logged with account ID and endpoint
- [ ] Backoff state does not persist across runs (each run starts fresh)

**Technical Notes:**
- `x-app-usage` header: `{"call_count": N, "total_cputime": N, "total_time": N}` — percentages (0-100)
- Rate limit window is rolling 1-hour
- At 100%, API returns error code 4 or 32
- Implement in `MetaApiClient` as middleware

**Dependencies:** 1.7

---

## Milestone 4: Normalization Layer

**Goal:** Map raw social media items to Minoo entities (events, teachings) using the existing ingestion pipeline.

---

### Issue 4.1: SocialMediaItemMapper base class

**Title:** `feat: create base mapper for social media items to Minoo entities`

**Description:**
Create a base mapper class that handles common social-to-Minoo mapping logic, following the existing EntityMapper pattern.

**Acceptance Criteria:**
- [ ] `src/Ingestion/EntityMapper/SocialMediaItemMapper.php` — abstract base class
- [ ] Common methods: `extractTitle(caption)`, `extractBody(caption)`, `extractHashtags(caption)`, `mapTimestamp(isoDate)`
- [ ] `extractTitle`: first line of caption, truncated to 120 chars
- [ ] `extractBody`: caption after first line
- [ ] `extractHashtags`: regex extract `#word` tokens
- [ ] Unit tests for each extraction method

**Technical Notes:**
- Title extraction: `explode("\n", $caption, 2)[0]` — first line
- If first line > 120 chars, truncate at word boundary + "..."
- Hashtags: `/(?:^|\s)#([a-zA-Z0-9_]+)/` — extract tag names without `#`

**Dependencies:** None

---

### Issue 4.2: Facebook Post → Teaching mapper

**Title:** `feat: map Facebook posts to Minoo teaching entities`

**Description:**
Create a mapper that transforms Facebook Page posts into Minoo teaching entities.

**Acceptance Criteria:**
- [ ] `src/Ingestion/EntityMapper/FacebookPostMapper.php`
- [ ] Maps: `message` → title + content, `created_time` → `created_at`, `full_picture` → media reference, `permalink_url` → `source_url`
- [ ] Sets `community_id` from the social account's community mapping
- [ ] Sets `type` to `social_post` (new teaching type, seeded)
- [ ] Sets `consent_public=1`, `copyright_status=community_owned`
- [ ] Generates slug from title via `SlugGenerator`
- [ ] Deduplication key: `external_id + external_source`
- [ ] Unit tests with sample Facebook post payloads

**Technical Notes:**
- Posts without `message` (photo-only, share-only) get title from attachment name or "Untitled Post"
- `full_picture` URL may expire — download in media pipeline (separate issue)
- Follow existing `DictionaryEntryMapper` pattern

**Dependencies:** 4.1, 5.3

---

### Issue 4.3: Facebook Event → Event mapper

**Title:** `feat: map Facebook Page events to Minoo event entities`

**Description:**
Create a mapper that transforms Facebook Page events into Minoo event entities.

**Acceptance Criteria:**
- [ ] `src/Ingestion/EntityMapper/FacebookEventMapper.php`
- [ ] Maps: `name` → `title`, `description` → `description`, `start_time` → `starts_at`, `end_time` → `ends_at`, `place.name` → `location`, `cover.source` → media reference
- [ ] Sets `community_id` from social account mapping
- [ ] Sets `type` to `social_event` (new event type, seeded)
- [ ] Generates slug from event name
- [ ] Handles recurring events: creates separate entity per occurrence
- [ ] Unit tests with sample Facebook event payloads

**Technical Notes:**
- `start_time` and `end_time` are ISO 8601 with timezone — convert to UTC
- `place` may have nested `location` with lat/lng — store in `location` field as text for now
- Recurring events: `event_times` array contains `{start_time, end_time, id}` for each occurrence

**Dependencies:** 4.1, 5.3

---

### Issue 4.4: Instagram Media → Teaching mapper

**Title:** `feat: map Instagram media to Minoo teaching entities`

**Description:**
Create a mapper that transforms Instagram posts (images, videos, carousels, reels) into Minoo teaching entities.

**Acceptance Criteria:**
- [ ] `src/Ingestion/EntityMapper/InstagramMediaMapper.php`
- [ ] Maps: `caption` → title + content, `timestamp` → `created_at`, `media_url` → media reference, `permalink` → `source_url`
- [ ] Carousel items: first image as primary media, others stored in content body as links
- [ ] Reels: treated as teaching with video media type
- [ ] Hashtags extracted from caption → tags
- [ ] Sets `community_id` from social account mapping
- [ ] Sets `type` to `social_post`
- [ ] Unit tests with sample Instagram payloads (image, video, carousel)

**Technical Notes:**
- Instagram captions don't have titles — extract from first line or first sentence
- Media URLs expire — need separate download step
- Carousel `children` array contains individual media items

**Dependencies:** 4.1, 5.3

---

### Issue 4.5: Hashtag → Tag mapping

**Title:** `feat: map social media hashtags to Minoo taxonomy tags`

**Description:**
Extract hashtags from social media captions and map them to Minoo taxonomy terms.

**Acceptance Criteria:**
- [ ] Hashtags extracted during mapping (4.2, 4.4)
- [ ] Each unique hashtag creates or finds a taxonomy_term in vocabulary `social_tags`
- [ ] Teaching entities linked to tags via existing `tags` entity_reference field
- [ ] Common stop-hashtags configurable (e.g., `#reels`, `#viral` — ignored)

**Technical Notes:**
- Follow existing get-or-create pattern from `IngestMaterializer`
- Vocabulary `social_tags` seeded in `TaxonomySeeder`
- Tags normalized: lowercase, underscores for spaces

**Dependencies:** 4.2, 4.4

---

### Issue 4.6: Social media materialization pipeline

**Title:** `feat: materialize social media items into Minoo entities`

**Description:**
Create a materializer that processes raw `social_media_items` through the appropriate mapper and creates Minoo entities.

**Acceptance Criteria:**
- [ ] `bin/materialize-social [--dry-run] [--community=SLUG] [--status=pending]`
- [ ] Reads `social_media_items` with `status=pending`
- [ ] Routes to correct mapper based on `external_source` + `item_type`
- [ ] Creates event or teaching entity via EntityTypeManager
- [ ] Updates `social_media_items.status` to `materialized` with `materialized_entity_id`
- [ ] Errors logged, item status set to `failed` with reason
- [ ] Summary output: materialized/skipped/failed counts

**Technical Notes:**
- Follow existing `IngestMaterializer` pattern
- Two-phase: import (raw storage) already done by sync, this is materialization
- Materialized entities start with `status=1` (published) — configurable per community
- Deduplication: skip if `external_id` already materialized

**Dependencies:** 4.2, 4.3, 4.4, 4.5, 5.2

---

### Issue 4.7: Media asset download pipeline

**Title:** `feat: download and store social media assets locally`

**Description:**
Download images and videos from Meta CDN URLs before they expire, storing them as Minoo media entities.

**Acceptance Criteria:**
- [ ] `bin/download-social-media [--dry-run] [--pending-only]`
- [ ] Downloads images/videos from `media_url` in `social_media_items.raw_payload`
- [ ] Stores in `public/uploads/social/` with hash-based filenames
- [ ] Creates `media` entity for each asset with `attribution_source=facebook|instagram`
- [ ] Updates teaching/event `media_id` references
- [ ] Handles download failures gracefully (retry on next run)

**Technical Notes:**
- Facebook `full_picture` URLs are generally stable
- Instagram `media_url` expires in ~24 hours — must download promptly
- Store original and generate thumbnails (future)
- Set `copyright_status=community_owned` (content from community's own page)

**Dependencies:** 4.6

---

## Milestone 5: Storage Layer

**Goal:** Add database schema for social accounts, social media items, and extend existing entity tables.

---

### Issue 5.1: social_accounts entity type

**Title:** `feat: add social_accounts entity type and provider`

**Description:**
Create the entity type for storing connected social media accounts (Facebook Pages, Instagram accounts).

**Acceptance Criteria:**
- [ ] `src/Entity/SocialAccount.php` extending `ContentEntityBase`
- [ ] `src/Provider/SocialServiceProvider.php` with entity type registration and routes
- [ ] `src/Access/SocialAccessPolicy.php` — admin-only access
- [ ] Fields: `platform` (string), `external_id` (string), `name` (string), `access_token` (text), `expires_at` (timestamp), `permissions_granted` (text/JSON), `community_id` (entity_reference→community), `metadata` (text/JSON), `last_synced_at` (timestamp), `status` (boolean)
- [ ] Entity keys: `said` (id), `uuid`
- [ ] Unit tests for entity and provider

**Technical Notes:**
- Follow existing entity registration pattern (EventServiceProvider as template)
- `access_token` is sensitive — consider encryption at rest (future)
- `metadata` stores platform-specific fields as JSON
- `platform` values: `facebook`, `instagram`

**Dependencies:** None

---

### Issue 5.2: social_media_items entity type

**Title:** `feat: add social_media_items entity type for raw ingested content`

**Description:**
Create the entity type for storing raw social media items before materialization into Minoo entities.

**Acceptance Criteria:**
- [ ] `src/Entity/SocialMediaItem.php` extending `ContentEntityBase`
- [ ] Registered in `SocialServiceProvider`
- [ ] Fields: `external_id` (string), `external_source` (string), `item_type` (string), `social_account_id` (entity_reference→social_account), `community_id` (entity_reference→community), `raw_payload` (text/JSON), `synced_at` (timestamp), `materialized_entity_type` (string, nullable), `materialized_entity_id` (integer, nullable), `deleted_at` (timestamp, nullable), `status` (string: pending/materialized/failed/deleted)
- [ ] Unique constraint on `external_id + external_source`
- [ ] Unit tests

**Technical Notes:**
- `raw_payload` stores the complete Graph API response — enables reprocessing without re-fetching
- `item_type` values: `post`, `event`, `photo`, `video`, `image`, `carousel`, `reel`
- `status` is a string enum, not boolean — tracks lifecycle state

**Dependencies:** 5.1

---

### Issue 5.3: Extend event and teaching entities with external_id fields

**Title:** `feat: add external_id and external_source fields to event and teaching entities`

**Description:**
Add fields to track the social media origin of materialized entities for deduplication and provenance.

**Acceptance Criteria:**
- [ ] `external_id` (string) and `external_source` (string) added to EventServiceProvider and TeachingServiceProvider
- [ ] `source_url` (uri) added if not already present — permalink to original post
- [ ] Unit tests verifying field definitions via provider inspection
- [ ] Schema migration documented for production

**Technical Notes:**
- Follow the pattern from this PR (#244) — add to `fieldDefinitions` in provider
- Weight: after `community_id` (12/16), before `media_id` (20)
- `external_source` values: `facebook`, `instagram`, `manual` (default null = manual)

**Dependencies:** None

---

### Issue 5.4: Seed social_post and social_event types

**Title:** `feat: seed social_post teaching type and social_event event type`

**Description:**
Add type configs for content ingested from social media, distinguishing it from manually created content.

**Acceptance Criteria:**
- [ ] `social_post` added to `ConfigSeeder::teachingTypes()`
- [ ] `social_event` added to `ConfigSeeder::eventTypes()`
- [ ] Unit tests for seeder output

**Technical Notes:**
- Follow existing seeder pattern
- These types allow filtering/querying social-sourced content separately

**Dependencies:** None

---

## Milestone 6: Observability & Drift Detection

**Goal:** Add dashboards, logging, and alerting for the social ingestion pipeline.

---

### Issue 6.1: Sync logging via ingest_log

**Title:** `feat: log social sync operations to ingest_log entity`

**Description:**
Record each sync run in the existing `ingest_log` entity type for auditability.

**Acceptance Criteria:**
- [ ] Each sync run creates an `ingest_log` entry with source=`facebook`|`instagram`
- [ ] Log includes: items_created, items_updated, items_skipped, items_failed, duration_ms
- [ ] Failed items include error reason in log payload
- [ ] `bin/sync-social` outputs summary referencing log ID

**Technical Notes:**
- Reuse existing `IngestServiceProvider` and `ingest_log` entity type
- `snapshot_type` can distinguish: `social_posts`, `social_events`, `social_media`
- Payload stores sync metadata as JSON

**Dependencies:** 3.7

---

### Issue 6.2: Sync status admin dashboard

**Title:** `feat: admin dashboard showing social sync status per community`

**Description:**
Add an admin page showing the sync health of each connected social account.

**Acceptance Criteria:**
- [ ] `/admin/social-sync` shows all social accounts with last sync time, item counts, error counts
- [ ] Accounts not synced in 24+ hours flagged as stale
- [ ] Per-account breakdown: posts synced, events synced, media synced
- [ ] Link to full sync log for each account

**Technical Notes:**
- Controller: `src/Controller/SocialSyncController.php`
- Template: `templates/admin/social-sync.html.twig`
- Query `social_media_items` grouped by `social_account_id` and `status`

**Dependencies:** 6.1, 2.4

---

### Issue 6.3: Token health monitoring endpoint

**Title:** `feat: health check endpoint for Meta token status`

**Description:**
Add a JSON endpoint that reports token health for monitoring systems.

**Acceptance Criteria:**
- [ ] `/api/admin/social-health` returns JSON with per-account token status
- [ ] Fields: `account_name`, `platform`, `token_status` (valid/expiring/expired), `expires_in_days`, `permissions_ok`, `last_sync_age_hours`
- [ ] HTTP 200 if all healthy, 503 if any critical issues
- [ ] Admin-only access

**Technical Notes:**
- Can be polled by external monitoring (UptimeRobot, etc.)
- Token status derived from `expires_at` vs. now
- Don't probe Graph API on every health check — use stored state

**Dependencies:** 1.5, 5.1

---

### Issue 6.4: Error alerting for sync failures

**Title:** `feat: detect and surface sync job failures`

**Description:**
When sync jobs fail or produce errors above threshold, surface alerts.

**Acceptance Criteria:**
- [ ] `bin/check-social-health` reports sync failures in last 24 hours
- [ ] Exit code 1 if any account has > 3 consecutive failed syncs
- [ ] Exit code 1 if any account hasn't synced in 48+ hours
- [ ] Output includes failure reasons and affected accounts
- [ ] Designed for cron + email alerting

**Technical Notes:**
- Query `ingest_log` for recent sync entries with errors
- Query `social_accounts.last_synced_at` for staleness
- Can be combined with `bin/check-meta-tokens` (1.5) into unified health check

**Dependencies:** 6.1

---

### Issue 6.5: API quota usage tracking

**Title:** `feat: track and log Meta API quota usage`

**Description:**
Track Graph API usage percentages from response headers to prevent rate limiting.

**Acceptance Criteria:**
- [ ] `MetaApiClient` logs `x-app-usage` header values after each request
- [ ] Usage stored in `social_accounts.metadata` under `api_usage` key
- [ ] `bin/check-social-health` reports current API usage percentage
- [ ] Warning at 70%, critical at 90%

**Technical Notes:**
- `x-app-usage` header: `{"call_count": 28, "total_cputime": 15, "total_time": 12}` (percentages)
- Rolling 1-hour window — values decay over time
- Only track from most recent sync run

**Dependencies:** 3.8, 6.4

---

### Issue 6.6: Per-community ingestion status report

**Title:** `feat: per-community report of ingested social content`

**Description:**
Generate a report showing what social content has been ingested for each community.

**Acceptance Criteria:**
- [ ] `bin/social-report [--community=SLUG]` outputs per-community stats
- [ ] Shows: connected accounts, total items synced, materialized count, pending count, failed count, last sync time
- [ ] Identifies communities with connected accounts but no recent content
- [ ] Output as formatted table (CLI) or JSON (--json flag)

**Technical Notes:**
- Join `social_accounts` → `social_media_items` → communities
- Group by community, aggregate counts by status

**Dependencies:** 6.1, 5.2

---

## Dependency Graph Summary

```
Milestone 5 (Storage) → no deps, start first
Milestone 1 (Meta Integration) → 5.1 for token storage
Milestone 2 (Account Discovery) → 1.3, 1.7, 5.1
Milestone 3 (Ingestion Jobs) → 1.7, 2.3, 5.2
Milestone 4 (Normalization) → 3.x, 5.x
Milestone 6 (Observability) → 3.7, 5.x
```

**Recommended implementation order:**
1. Milestone 5 (Storage) — schema foundation
2. Milestone 1 (Meta Integration) — OAuth + client
3. Milestone 2 (Account Discovery) — connect accounts
4. Milestone 3 (Ingestion Jobs) — fetch raw data
5. Milestone 4 (Normalization) — map to entities
6. Milestone 6 (Observability) — monitoring

**Total: 6 milestones, 38 issues**
