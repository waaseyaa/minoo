# Shared News & Events Feeds Service — Spec v2
**Date:** 2026-07-02 · **Supersedes:** [EVENTS-INGESTION-SPEC.md](EVENTS-INGESTION-SPEC.md) (v1, minoo-only) — v1's fetcher/normalizer/politeness/provenance design carries forward; its *placement* does not.

## Why v2

The ecosystem is three sites, all on the same Pi:

| Site | Identity | News/events role |
|---|---|---|
| **minoo.live** | Language & culture platform (post-resurrection identity) | Events/groups **scoped to language & culture**; news section to add |
| **rhtcircle.ca** | General site for the 21 RHT nations + related communities | Broadest consumer — everything |
| **oiatc.ca** | Council-only: outlines/showcases work OIATC supports | Has a news section needing cleanup; council-scoped |

One fetcher must serve all three (single crawl footprint against nation websites, single review pass). **Decisions (Russell, 2026-07-02):** standalone worker on the Pi · tag-at-review routing with an AI classifier assist (Anthropic API — key already provisioned on the Pi via vault; no ollama in the stack, not worth adding on a Pi) · minoo CC session: recon proceeds, fetcher implementation holds.

## Architecture

```
                      ┌─ waaseyaa-feeds (new compose service, own SQLite) ─┐
source registry ──► fetch (ICS/RSS/HTML, polite) ──► normalize (kind: event|news)
                      ──► dedupe ──► classifier (Haiku: kind/tags/nations/summary)
                      ──► review queue (single, tag-confirm UI) ──► published items
                      └───────────── syndication API (internal-only) ─────────────┘
                                            │ docker network / tailnet, no public route
              ┌─────────────────┬───────────┴──────────┐
        minoo importer     rht importer          oiatc importer
        (tags: culture,    (all)                 (tag: council)
         language, nation∈7)
        → local event/news rows, provenance kept, curated rows never overwritten
```

**The worker is a thin Waaseyaa app** in a new repo (working name `waaseyaa-feeds`): that buys entities + fail-closed access + admin UI conventions + scheduler for free, and the team knows exactly one stack. Deployed as a compose service in waaseyaa-infra with the same `FEEDS_REF` pin pattern; **memory limit from day one** (E: M-3 lesson). DB volume rides the existing restic backup scope.

**Syndication, not shared DB:** sites pull via `GET /api/items?site=<profile>&kind=event|news&since=<cursor>` server-to-server (same pattern as rhtcircle consuming minoo's `/api/lang/lookup`). Each site runs a small scheduled importer that upserts into its *own* entities — sites stay fully functional when the worker is down, and each site's access policies/provenance gates stay authoritative at its own edge.

## What carries over from v1 unchanged

Source registry shape (now includes `kind: news` feeds + oiatc/council sources) · politeness rules (1 req/s, MinooEventsBot UA → rename `RHTFeedsBot/1.0 (+https://rhtcircle.ca)`, robots.txt, ETag, failure isolation, fetch-health log) · normalize/dedupe (`source_uid` else content hash; **bake in minoo #727 ISO-datetime-cast fix and #729 URL-scheme validation** — CC already scoped these) · trust model (`auto` only for nation-owned feeds — skips review, classifier tags applied, spot-checked) · never overwrite human-edited rows · past events unpublish, never delete.

## Classifier

Per new item, one Haiku call: suggest `kind` (event/news), `tags` (culture, language, council, health, education, employment, general…), `nations[]`, one-line summary. Human confirms in the queue — classifier is assist, not authority; `trust=auto` items go out with classifier tags and get spot-checked. Volume is tens of items/day → cost negligible. Key already on the Pi (`ANTHROPIC_API_KEY` in vault). Add a taxonomy config the reviewer can extend; log classifier-vs-human disagreements to tune the prompt.

## Per-site consequences

- **minoo:** importer (events tagged culture/language or nations ∈ 7 Mamaweswen; news likewise) + **new news section** + member event submissions (v1 §5) now submit into… minoo, then sync *up* to the worker queue via API so one queue sees everything. Groups: worker's discovered-org diffing posts *suggestions* through the API into minoo's review surface (v1 §6 behavior preserved, later phase). Unblocks community-vantage homepage (I-1).
- **rhtcircle:** importer (all tags) + news+events surfaces. Broadest beneficiary.
- **oiatc:** news cleanup = migrate hand-managed news to importer-fed rows (council tag), archive stale items. Council posts *originate* in oiatc and syndicate out through the worker so minoo/rht can carry them.

## Build order (revised; each shippable)

| # | What | Where |
|---|---|---|
| W-0 | **Recon** (in flight — minoo CC session): probe 21 nation sites (+ oiatc news, related orgs) for event pages, RSS, ICS, news feeds. Seed: recon doc §1a table (20 validated URLs) + `MamaweswenNations.php`. Output: docs only — findings table + draft source registry, portable (not minoo config). | minoo repo `docs/specs/` |
| W-1 | Repo skeleton `waaseyaa-feeds` (thin Waaseyaa app) + compose service + `FEEDS_REF` pin + memory limit + Caddy/tailnet internal exposure | new repo + waaseyaa-infra |
| W-2 | Fetcher: registry + polite client + ICS/RSS parsers + normalizer (#727/#729 fixes) + dedupe + fetch-health log. Port CC's PR-A brief nearly verbatim — placement changes, design doesn't. | waaseyaa-feeds |
| W-3 | Classifier callout + review queue UI (tag-confirm, approve/reject/edit) | waaseyaa-feeds |
| W-4 | Syndication API + **minoo importer** + minoo news section → **minoo events unfreeze** | both |
| W-5 | rht + oiatc importers; oiatc news cleanup/migration; council-post syndication | rht, oiatc repos |
| W-6 | `trust=auto` for nation-owned feeds · member-submission sync-up · groups diff suggestions · HTML extractors where justified | waaseyaa-feeds + minoo |

## Recon amendments (post PR-0, minoo #919 — 2026-07-02)

Findings from the 21-nation probe change the following (decisions: Russell):

1. **Politeness:** honor per-site `Crawl-delay` (observed up to 60s at Sheshegwaning), not a flat 1 req/s floor.
2. **Bot walls — no evasion, outreach instead.** Serpent River + Atikameksheng ICS return empty-200 to bots. Policy: never rotate/spoof UAs to get past mitigation. OIATC's council relationships are the fix — ask those nations to allowlist `RHTFeedsBot` (or share the feed directly). Registry marks them `blocked_pending_outreach`; HTML/review-tier in the interim. Add outreach to the W-2 runbook, not the fetch code.
3. **Anishinabek Nation umbrella org: included, review-tier.** Richest source found (current RSS + ICS); carries regional event coverage while nation ICS stays scarce (only Sheguiandah own-domain-confirmed). Not nation-owned ⇒ never auto; classifier tags items per-nation.
4. **Auto-tier rule refined:** Thessalon's ICS is nation-curated but Google-hosted — auto candidacy requires nation-owned *and* own-domain *and* confirmed. Google/Wix/Webflow-hosted feeds cap at review.
5. **oiatc is a source, not a cleanup project.** Its news is healthy (8 items, newest 17 days, working RSS at `/news/rss.xml` mirroring HTML 1:1). W-5 shrinks to a light migration; its topic tags (council, sovereign-ai, anishinaabemowin…) seed the classifier taxonomy.
6. **Canonical domains:** sagamok.ca is a 301 alias → `sagamokanishnawbek.com` (registry uses canonical; note the alias in `MamaweswenNations.php` eventually).
7. **W-6 extractor scope confirmed:** ~5–6 HTML extractors for full coverage (Wahnapitae, Henvey Inlet, Sheshegwaning, Sagamok/Webflow, Whitefish River news — its RSS is dead-since-2016 and excluded); nation RSS is nearly all *news*, machine-readable *events* remain scarce ⇒ Anishinabek Nation + extractors carry events early on.
8. **Gaps:** Zhiibaahaasing has no site (coverage only via umbrella org); Mississauga (Wix) needs a manual browse before any registry entry.
9. rhtcircle.ca confirmed consumer-only today (no news/events surface — W-5 builds it).

## Open items
- ~~Worker repo name~~ → **`waaseyaa-feeds`** (rename before W-1 if desired); no public surface — internal only.
- Review-queue staffing: one queue implies one set of coordinator accounts on the worker app — who?
- ~~oiatc news cleanup scope~~ → resolved by recon (amendment 5).
- Outreach owner for Serpent River + Atikameksheng allowlisting (amendment 2).
