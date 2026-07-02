# RHT Nation Event/News Source Recon — 2026-07-02

**Purpose:** W-0 recon for [FEEDS-SERVICE-SPEC.md](feeds-service.md) / [EVENTS-INGESTION-SPEC.md](events-ingestion.md). Probes the 21 RHT nation websites (+ oiatc.ca, rhtcircle.ca, 3 related-org sites) for machine-readable or scrapeable event/news surfaces. Findings back the draft source registry at `docs/specs/event-sources.draft.php`.

**Ground truth used:** the validated 20-of-21 URL list supplied in the mission brief (2026-06-25 recon table), not re-discovered here. This pass only verifies liveness and probes for feeds/pages.

---

## Method note

- UA: `RHTFeedsBot/1.0 (+https://rhtcircle.ca; feeds recon; contact jonesrussell42@gmail.com)` on every request, including `robots.txt`.
- Sequence per site: `robots.txt` → homepage (parsed for `<link rel="alternate">`, nav hrefs matching event/calendar/news/announce/newsletter, generator meta, Google Calendar iframes) → `/feed/` → `/events/?ical=1` → `/events/` → `/calendar/` → `/news/`, plus 1-3 targeted follow-ups per site where the homepage declared a different real path (e.g. a feed URL without a trailing slash, or an ICS URL under `/calendar/` instead of `/events/`). All sites stayed at or under the 10-request budget.
- ≥1s delay between requests to the same host; 15s timeout (`curl -m 15`); no Facebook, no login-gated URLs fetched.
- Every feed/ICS claim below was fetched and inspected (status, content-type, and — for feeds — actual `<title>`/`<pubDate>`/`VEVENT` content), not inferred from a declared `<link>` alone. Several sites declare a feed that does not actually work; these are called out explicitly rather than counted as usable.
- Run date: 2026-07-02. "Newest post" ages are relative to that date.
- **Compliance disclosure:** several sites' `robots.txt` declare a `Crawl-delay` longer than the flat 1s used here (Atikameksheng: 3s, most WordPress WAF sites: 10s, Sheshegwaning: 60s). This recon pass was a single bounded probe (≤10 requests/site, one-time) and used a flat 1s inter-request delay rather than the declared per-site value. **The production fetcher (W-2) must parse and honor each site's declared `Crawl-delay`, not a flat constant.** No `Disallow` rule was violated — the two sites with feed/event-related `Disallow` entries (Atikameksheng, Nipissing) only block parametrized AJAX action endpoints (`/events/action~...`), not the paths probed here.
- No `robots.txt` returned 403 to this UA (the "WAF 403's default UAs" gotcha called out in the brief did not manifest here); one site (Garden River) 404'd `robots.txt` at the probed subpath (`/site/robots.txt`) but has one at domain root.

---

## Summary table — 21 RHT nations

Priority = one of the 7 Mamaweswen (North Shore Tribal Council) nations.

| # | Nation | Priority | URL | Live? | CMS | Events page | ICS | RSS (coverage) | News page | Newest post seen | Trust rec. | Notes |
|---|---|---|---|---|---|---|---|---|---|---|---|---|
| 1 | Batchewana | ★ | batchewana.ca | Yes (200) | WordPress/Divi | `/events/` (HTML) | No — `?ical=1` returns identical HTML to `/events/`, not calendar data | Yes, `/feed/`, general blog (news + notices) | No dedicated `/news/` (redirects → `/newsletter/`) | 2026-05-20 | review | RSS is the only real feed; ~6 weeks stale at run time. |
| 2 | Garden River | ★ | gardenriver.org/site/ | Yes (200) | WordPress 7.0/Divi | No (`/events/`, `/calendar/` 404) | No | Yes, `/site/feed/`, active — **freshest RSS found, 2 days old** | `/news/` redirects → `/newsletter/`; real listing is `/category/news/` | 2026-06-30 | review | `robots.txt` only exists at domain root, not `/site/`. `gardenriver.ca` (Education Unit) not prominently linked from homepage nav — not probed per brief. |
| 3 | Thessalon | ★ | thessalonfirstnation.ca | Yes (200) | GoDaddy Website Builder (no CMS blog) | Embedded Google Calendar iframe | **Yes** — public Google Calendar ICS confirmed working, 154 `VEVENT`s (`X-WR-CALNAME: Website Events Calendar`) | No RSS | None found (all of `/feed/`, `/events/`, `/calendar/`, `/news/` 404) | n/a (calendar has no news) | review | ICS host is `calendar.google.com`, **not** thessalon's own domain — real, nation-curated content but not `auto_candidate`-eligible under the "nation's own domain" rule. |
| 4 | Mississauga (Mississaugi) | ★ | mississaugi.com | Yes (200) | Wix | Not found in probed paths (all 301, Wix internal routing) | No | No (`/feed` 404) | Not found within budget | unknown | review (weak) | Wix sites don't expose WP-style feeds; a manual browse of the Wix nav would be needed to find the real events/news page — out of scope for this pass. Recommend a follow-up manual check before registry inclusion. |
| 5 | Serpent River | ★ | serpentriverfn.com | Yes (200) | WordPress/Avada/Divi | `/events/` (HTML), Tribe Events plugin declared (`wp-json/tribe/events/v1/`) | Declared (`<link rel="alternate" type="text/calendar">` at `/events/?ical=1`) but **fetch returns HTTP 200, `Content-Length: 0`, `X-Robots-Tag: noindex`** — looks like a bot-mitigation empty response to this UA, not usable as-is | Yes, `/feed/`, works; newest sampled post 2026-01-16 (~5.5 months stale) | `/news/` redirects → `/newsletter/` | 2026-01-16 | review | ICS is declared but unconfirmed — flag for a re-check with a browser-like UA before trusting. |
| 6 | Sagamok | ★ | sagamokanishnawbek.com (Webflow) | Yes (200) — **canonical** | Webflow | `/sagamok-news` (HTML "Sagamok News" listing; recent-looking items: MOU signing, power outages) | No | No — `/feed` 404s (Webflow has no blog feed here) | `/sagamok-news` | not date-verified (Webflow lists no visible dates in probed markup) | review | **Discrepancy confirmed:** `sagamok.ca` (the URL in minoo's `MamaweswenNations.php`) returns `301 → https://www.sagamokanishnawbek.com/`. It is a redirect alias, not a separate site — minoo's config points at the redirector, not the canonical Webflow domain. Registry should use `sagamokanishnawbek.com`. |
| 7 | Atikameksheng Anishnawbek | ★ | atikamekshenganishnawbek.ca | Yes (200) | WordPress 7.0 | `/events/` + `/events-page/` (HTML), Tribe Events plugin declared | Declared, same empty-200 bot-block pattern as Serpent River — unconfirmed | Yes, `/feed/`, but feed body is nearly empty (1.2KB, ~1 item) — low posting volume | `/news/` (200, dedicated page) | not confirmed (feed too thin to date) | review | `robots.txt` declares `Crawl-delay: 3`. |
| 8 | Wahnapitae | | wahnapitaefn.ca | Yes (200) | Joomla (Helix Ultimate) + DPCalendar extension | `/notices/events.html` + individual `/notices/events/<slug>.html` pages (HTML) | No | No | `/notices/community-newsletter.html`, `/administration/health/nrhc-newsletters.html` | July 2026 newsletter PDF linked directly on homepage | review | HTML-only; DPCalendar extension present but no ICS export path found live. |
| 9 | Nipissing | | nfn.ca | Yes (200) | WordPress 7.0/Divi | `/events/` (200; individual `/event/<slug>/` pages exist) | No — `?ical=1` returns identical bytes to the HTML events page; no Tribe wp-json alternate declared, so a lighter events plugin without ICS export | Yes, `/feed/`, very active | `/news-notices/` (redirect target, dedicated page) | 2026-06-30 | review | Second-freshest RSS found. `robots.txt` `Disallow` only covers `/events/action~` AJAX endpoints. |
| 10 | Dokis | | dokis.ca | Yes (200) | WordPress 6.9.4 + Site Kit | No (`/events/`, `/calendar/` 404) | No | Yes, `/feed/`, moderate | `/news/` (200, dedicated page, dates 2023–2026) | 2026-05-07 | review | ~8 weeks stale at run time. |
| 11 | Henvey Inlet | | hifn.ca | Yes (200) | Joomla (Helix Ultimate) | `/announcements/events.html` (HTML) | No | No | `/announcements/news.html`, `/announcements/newsletters.html` | July 2026 newsletter linked directly on homepage | review | Very current-looking "Announcements" hub (News, Newsletters, Events, Job Postings, Community Meetings as separate HTML sub-sections) — good HTML-extractor candidate later (W-6). |
| 12 | Magnetawan | | magfn.com | Yes (200) | WordPress 6.9.4 | Not found at probed paths (404) | No | Yes, `/feed/`, active | `/category/news/` (real listing; literal `/news/` 404s) | 2026-06-25 | review | |
| 13 | Shawanaga | | shawanagafirstnation.ca | Yes (200) | WordPress | `/events/` (200; `?ical=1` returns identical bytes — no real ICS) | No | Yes, `/feed/`, but body nearly empty (~1KB) — low posting volume | Not found (404) | not confirmed | review | `robots.txt` `Crawl-delay: 10`. |
| 14 | Wasauksing | | wasauksing.ca | Yes (200) | WordPress 6.6.5 | `/calendar/` (200, HTML, not ICS itself); literal `/events/` 404s | No | Yes, `/feed/`, active | `/news/` (200, dedicated) and `/category/community/latest-news/` | 2026-06-25 | review | |
| 15 | Aundeck Omni Kaning | | aokfn.com | Yes (200) | WordPress | `/news-and-events` (combined HTML page; redirect target of `/news/`) | No | Yes — declared feed URL has no trailing slash (`/feed`, not `/feed/`); works once you follow the redirect, ~5 months stale (newest 2026-02-19) | `/news-and-events` | 2026-02-19 | review | `robots.txt` `Crawl-delay: 10`. |
| 16 | Whitefish River | | whitefishriver.ca | Yes (200) | WordPress 7.0 | Not found (404) | No | Declared and technically "works" (200, valid RSS XML) but **content is dead** — single item, dated 2016-10-13. Does **not** reflect real site activity. | `/news` (real page, current — newest visible 2026-06-26) | 2026-06-26 (via HTML page, not RSS) | review — **do not trust the RSS** | Important trap: RSS validates but is stale/wrong. If this ever enters the registry it should be `kind: html` pointing at `/news`, not the RSS URL. |
| 17 | M'Chigeeng | | mchigeeng.ca | Yes (200) | WordPress | Nav links to `/events` (no trailing slash); probing `/events/` (with slash) 404'd — likely just a slash-normalization mismatch, not confirmed absent | No | Yes, `/feed/`, active | `/news` redirects → `/11-2/newsletter-survey/`; real news is RSS-covered posts (e.g. election coverage) | 2026-06-26 | review | Re-probe `/events` (no trailing slash) in a follow-up pass. |
| 18 | Sheguiandah | | sheguiandahfn.ca | Yes (200) | WordPress + Elementor | `/events/` (200, dedicated) | **Yes — confirmed working**, `BEGIN:VCALENDAR`/`VEVENT` returned at both `/calendar/?ical=1` (declared) and `/events/?ical=1`, 6KB, real event data | Yes, `/feed/`, active | `/news/` (200, dedicated) | 2026-06-03 | **review → strong auto candidate later** | Best-instrumented nation site found: working ICS + working RSS + dedicated events/news/calendar pages, all on the nation's own domain. |
| 19 | Sheshegwaning | | sheshegwaning.org | Yes (200) | "Website Builder Gridbox" (custom, not WordPress) | `/news-events/events/<slug>` (individual HTML pages) | No — `?ical=1` returns the HTML events page (content-type `text/html`), not calendar data | No (`/feed/` 404) | `/news-events` (redirect target of `/news/`) | not date-verified in probed markup | review | `robots.txt` `Crawl-delay: 60` — the longest of any site probed. |
| 20 | Wiikwemkoong | | wiikwemkoong.ca | Yes (200) | WordPress + Divi/Avada + The Events Calendar (Tribe) plugin | `/events/` (200, dedicated) + `/annual-events/` | Declared (`/events/?ical=1`) but **returned HTTP 502** at fetch time — broken/transient, not usable right now; `wp-json/tribe/events/v1/` also declared but redirected (301) without confirming JSON | Yes, `/feed/`, **very active** (newest 2026-06-30) | `/news/` (200, dedicated) + `/category/news/` | 2026-06-30 | review | Richest tech stack of the 21 (full Events Calendar plugin) but the ICS endpoint itself is currently down — worth a re-check, this could become `auto_candidate` once fixed. |
| 21 | Zhiibaahaasing | | — | **No own-domain site** | — | — | — | — | — | — | absent | Confirmed 2026-06-25, Facebook-only. Not probed per instructions (no Facebook fetches). |

---

## Usable-feeds rollup

**Has a confirmed working ICS (nation's own domain or embedded calendar):**
- **Sheguiandah** — `/calendar/?ical=1` (or `/events/?ical=1`), confirmed `VEVENT` data. Own domain.
- **Thessalon** — public Google Calendar ICS (154 events), but hosted on `calendar.google.com`, not the nation's own domain.

**Declared ICS, not currently usable (flag for re-check, do not trust yet):**
- Serpent River, Atikameksheng — `?ical=1` returns an empty `200`/`Content-Length: 0` to this UA (looks like bot mitigation on the dynamic endpoint).
- Wiikwemkoong — `?ical=1` returned `502` at fetch time (transient or broken).

**RSS present and genuinely useful (recent, covers real posting activity):**
Garden River (freshest, 2 days old), Nipissing, Wiikwemkoong, Sheguiandah, Batchewana, Magnetawan, Wasauksing, M'Chigeeng, Dokis, Serpent River (older), Aundeck Omni Kaning (older).

**RSS present but low-value (near-empty or dead):**
- Atikameksheng, Shawanaga — feed validates but is nearly empty (very low posting volume).
- **Whitefish River — RSS validates but is a dead feed (single 2016 item)** while the site's actual `/news` page is current. Do not register the RSS; if used at all, register as `html` against `/news`.

**HTML-only (no feed of any kind found):**
Wahnapitae, Henvey Inlet, Sheshegwaning, Mississauga (Wix — needs a manual follow-up), Sagamok (Webflow — canonical site, `/sagamok-news`), Magnetawan/Wasauksing/M'Chigeeng/Aundeck Omni Kaning also have usable HTML news listings alongside their RSS.

**Nothing usable at all (own-domain):**
Zhiibaahaasing (no site).

---

## oiatc.ca news inventory (cleanup audit)

- **Section:** `/news` — a hand-built, time-stamped list ("newest first"), not a CMS blog. Custom static site (own `/css/site.css`, `/js/oiatc-analytics.js`; Cloudflare-fronted; no WordPress/Webflow markers).
- **Feed:** yes — `<link rel="alternate" type="application/rss+xml" href="https://oiatc.ca/news/rss.xml">`, **confirmed working**: `200`, `application/rss+xml`, 8 `<item>`s, one-to-one with the HTML listing.
- **Item count:** **8 items total**, all shown on a single page (no pagination needed within the request budget — "All (8)" filter chip confirms 8 is the full count).
- **Date range:** newest **2026-06-15**, oldest **2025-05-28** (all 8 items fall within a 4.5-week window: May 28 – June 15, 2026).
- **Staleness verdict:** **not stale.** Newest item is 17 days old as of the 2026-07-02 run date. The spec's framing of oiatc news as needing "cleanup" appears to be about *migrating hand-managed rows to importer-fed rows going forward* (per FEEDS-SERVICE-SPEC §Per-site consequences), not about a backlog of abandoned content — the section is small, current, and well-maintained as-is.
- Each item also carries an `explainer` topic tag (`council`, `sovereign-ai`, `anishinaabemowin`, `anokii`, etc.) used for its filter UI — worth preserving as taxonomy input if oiatc's news later syndicates through the shared worker.

## rhtcircle.ca

- Homepage (200) nav: `/about`, `/circle`, `/communities`, `/contact`, `/land`, `/resources`, `/safety`, `/standard`, `/treaty`. **No `news`, `events`, or `calendar` nav item exists today.** Confirms the spec's framing — rhtcircle currently has no news/events surface; it's the intended consumer once the importer (W-4/W-5) lands, not a source.

## Related-org sites (3 probed, as instructed)

| Org | URL | Live? | CMS | RSS | ICS | Notes |
|---|---|---|---|---|---|---|
| Mamaweswen (North Shore Tribal Council — umbrella for the 7 priority nations) | mamaweswen.com | Yes (200) | WordPress 6.4.8 | Declared, `200`, valid XML — **but dead: newest item dated 2023-03-06**, over 3 years stale | No | **`mamaweswen.ca` does not resolve (DNS failure)** — the working domain is `.com`. Notable finding: the umbrella body for the priority-7 nations has an apparently abandoned WordPress feed despite the site itself being live. |
| UCCMM (United Chiefs and Councils of Mnidoo Mnising — umbrella for the Manitoulin/UCCMM nations: Aundeck Omni Kaning, M'Chigeeng, Sheguiandah, Sheshegwaning, Whitefish River, Wiikwemkoong) | uccmm.ca | Yes, **but only over `http://`** — `https://` TLS handshake fails (`SSL alert: handshake failure`) | Static HTML page-builder, not WordPress | No | No | HTML-only `/calendar-of-events.html` and `/news--notices.html` pages. `robots.txt` `Crawl-delay: 10`. |
| Anishinabek Nation / Union of Ontario Indians (regional political umbrella, not one of the 21 nations but prominently cross-linked) | anishinabek.ca (`www.` redirects to apex) | Yes (200) | WordPress 7.0 | Yes, **very active** — newest item **2026-07-01** (one day before run date) | **Yes — confirmed working**, `/events/?ical=1`, real `VEVENT` data (Tribe Events plugin) | The strongest single source found in this whole pass: working RSS *and* working ICS, both current, on the org's own domain. Not `auto_candidate`-eligible under the strict "one of the 21 nations" reading of the trust rule, but a clear high-value `review` addition — also links out to a sister site `anishinabeknews.ca` (not probed, out of budget). |

---

## What this means for the registry (`event-sources.draft.php`)

- Every usable feed found above is included with `trust => 'review'` (per instructions, no exceptions), plus `auto_candidate => true` only where the feed lives on the nation's own domain (Sheguiandah's ICS/RSS, and the RSS feeds that were confirmed active and reasonably fresh — see file for the full per-entry judgment call, especially Thessalon's Google-hosted ICS and Anishinabek's org-not-nation ICS, both flagged `auto_candidate => false` with a note explaining why).
- Whitefish River's RSS is **excluded** — a dead feed is worse than no feed for an ingestion pipeline (it would produce zero items forever while looking "wired up"). If wanted, it should be registered as `kind: html` against `/news` in a later pass, once an HTML extractor is written for it (W-6).
- Declared-but-broken ICS endpoints (Serpent River, Atikameksheng, Wiikwemkoong) are **excluded** from the draft registry rather than included and pre-broken; they're called out in this doc for a re-check once the real fetcher exists (a browser-like UA or a retry-with-backoff may get past the empty-200/502 responses seen here).
- Sagamok's `/sagamok-news` is HTML-only with no dates verified in this pass — included as an `html` entry pointing at the listing page found, flagged for extractor work later (W-6) rather than immediate ingestion. **Mississauga (Wix) has NO registry entry**: no events/news listing URL could be verified within the request budget (Wix internal routing), so there is nothing concrete to register — needs a manual browse follow-up before inclusion.
