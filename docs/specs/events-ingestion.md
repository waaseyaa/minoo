# Events & Groups Ingestion — Lightweight Custom Pipeline (replaces NorthCloud)
> **⚠️ SUPERSEDED (same day) by [FEEDS-SERVICE-SPEC.md](FEEDS-SERVICE-SPEC.md)** — the fetcher moves out of minoo into a standalone shared worker serving minoo + rhtcircle.ca + oiatc.ca (events **and news**). The pipeline design below (§2–§6) carries forward; the placement (§1 minoo-internal, `config/event_sources.php`, minoo-side fetch command) does not.

**Date:** 2026-07-02 · **Resolves:** CLEANUP-PRS.md I-2 · **Context:** NorthCloud is down permanently; decision #3 direction was "automate."

**Decisions (Russell, this session):**
- Sources: **community websites / RSS / ICS** + **community submissions** (no Facebook scraping, no staff quick-add for now)
- Publish model: **auto-publish allowlisted trusted sources; everything else → review queue**
- Scope: **events + groups**

---

## 1. Shape

Small, first-party, no external service:

```
source registry (config) → scheduled fetch (waaseyaa/scheduler → minoo-worker)
  → parse (ICS / RSS / per-source HTML extractor)
  → normalize + dedupe → upsert as event/group rows with provenance
  → trust=auto ⇒ published · trust=review ⇒ draft → admin review queue
community submissions (logged-in form) ————————————————————↗ (same queue)
```

Reuses what exists: `waaseyaa/scheduler` (core dep), the `event`/`group` entities and taxonomy, `EventController::displayable()`'s provenance gate (kept, repointed), the Anokii review-queue pattern (`ingested → curated → published` maps directly), and the admin ingestion dashboard as UI home.

## 2. Source registry

Config-first (like `config/crisis/*` was) — an entity later only if coordinators need to edit sources without a deploy:

```php
// config/event_sources.php
return [
  ['community' => 'sagamok',       'kind' => 'ics',  'url' => '...', 'trust' => 'auto',   'scope' => ['event']],
  ['community' => 'serpent-river', 'kind' => 'rss',  'url' => '...', 'trust' => 'review', 'scope' => ['event']],
  ['community' => 'mississauga',   'kind' => 'html', 'url' => '...', 'trust' => 'review', 'scope' => ['event','group'], 'extractor' => 'mississaugi_events'],
  // ...
];
```

**Seed list:** `docs/translation-seed-crawler-recon-2026-06-25.md` §1a already validated official sites for **20 of 21 RHT nations** (canonical URLs chosen over Facebook/directories; Zhiibaahaasing has no own-domain site). Recon task #1 below discovers which of those expose event pages/feeds. Start with the 7 curated Mamaweswen nations; registry supports all 21.

**Trust rule:** `auto` is reserved for feeds *owned by the nation itself* (their ICS/RSS on their domain). Third-party or HTML-scraped sources are always `review`.

## 3. Fetcher

One console command, `events:fetch`, scheduled daily (hourly closer to event dates is a later refinement), running in `minoo-worker`:

- **ICS:** `sabre/vobject` (mature, small) — handles recurrence expansion, timezones.
- **RSS/Atom:** SimpleXML — title/date/link/description.
- **HTML:** per-source extractor classes implementing one interface (`SourceExtractor::extract(string $html): iterable<RawEvent>`); only written for sources that justify it. No generic scraping framework — that's how this stays light.
- Politeness: 1 req/s, custom UA (`MinooEventsBot/1.0 (+https://minoo.live)`), honor robots.txt, `If-Modified-Since`/ETag caching, 10s timeout, per-source failure isolation (one bad source never kills the run).
- Per-source fetch log (last run, status, items found) surfaced on the ingestion dashboard — silent rot is the #1 failure mode of feed pipelines.

## 4. Normalize, dedupe, provenance

Canonical shape: `title, starts_at, ends_at, timezone, location, community_id, description, url, source_uid`.

- **Dedupe key:** `source_uid` when the feed provides one (ICS `UID`), else `sha1(normalized_title + starts_at + community)`. Upserts update changed fields on rows still in `ingested/review` state; **never overwrite a row a human has edited** (track `curated_by` — same rule the Anokii pipeline uses).
- **Provenance fields:** keep the existing gate but repoint it — `source` = registry source id + item URL; `copyright_status` = `community_owned` (nation's own feed) / `third_party_review` (everything else pending review) / `member_submitted`. `EventController::displayable()` continues to filter on these; audit its current expected values when repointing (D §6.5).
- Past-event hygiene: fetched events auto-unpublish (not delete) after `ends_at` + 7 days.

## 5. Review queue + submissions

- **Queue:** drafts land with status `review`; coordinator approves/rejects/edits from the ingestion dashboard (reuse the transcribe/curate list UI pattern). Approve ⇒ `status=1`. Role: `coordinator` (role gate already exists).
- **Submissions:** logged-in members get a simple "Submit an event" form (title, date, location, community, description, link). Creates a `review` draft with `copyright_status=member_submitted`, `source=member:<uid>`. Throttled (depends on P0-1 trusted_proxies). Notify submitter on approve/reject via existing notification hooks when the Notifications milestone lands; silently until then.

## 6. Groups

Same registry (`scope: ['group']`), different behavior — groups are slow-moving directory data, not a feed:
- Fetcher **diffs** discovered org/program listings against existing `group` rows and files *change suggestions* into the review queue (new group found / group page gone / contact changed). **Never auto-edits** — the 15 existing rows are curated.
- Member submissions can also propose groups (same form family).

## 7. Build order (each shippable)

1. **Recon pass:** probe the 20 nation sites for event pages, RSS, ICS endpoints (half a day, informs the registry; extends the crawler recon method).
2. **PR-A:** registry config + `events:fetch` (ICS+RSS only) + normalize/dedupe/provenance + scheduler wiring. Everything lands as `review` drafts. *(Pipeline proves itself before any auto-publish.)*
3. **PR-B:** review queue UI on the ingestion dashboard + approve/reject flow. → **Content unfreezes here.**
4. **PR-C:** enable `trust=auto` for nation-owned feeds found in recon; per-source fetch-health panel.
5. **PR-D:** member submission form + throttle.
6. **PR-E:** groups diffing + HTML extractors for the highest-value sources that lack feeds.

## 8. Consequences elsewhere

- **CLEANUP-PRS PR-2 #10:** NorthCloud hold **lifted** — delete `Infrastructure/NorthCloud/*` in the sweep (skim first for a portable envelope/normalizer worth copying into the new fetcher, then delete).
- Also delete `deploy/minoo-nc-sync.service` reference cleanup already in PR-1, and any `ingest:nc-sync` command remnants.
- **I-1 (community-vantage homepage)** unblocks for real once PR-B lands — live events are its centerpiece content.
- New deps: `sabre/vobject` only.
