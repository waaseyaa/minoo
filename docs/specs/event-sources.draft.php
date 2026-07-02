<?php

/**
 * DRAFT source registry — portable to the waaseyaa-feeds worker repo
 * (FEEDS-SERVICE-SPEC W-1/W-2). NOT wired into minoo config; lives in
 * docs/ on purpose.
 *
 * Built from the 2026-07-02 recon pass documented in
 * docs/specs/event-sources-recon.md — read that file for the full
 * per-site findings, the sites that were probed but excluded (dead
 * feeds, broken ICS endpoints, unconfirmed sources), and the method
 * note (UA, request budget, politeness compliance).
 *
 * Every entry below was actually fetched and inspected during recon —
 * no entry here is a "declared but never verified" feed.
 *
 * Field notes:
 * - kind: 'ics' | 'rss' | 'html'. For ics/rss, `url` is the feed URL
 *   itself. For html, `url` is the listing/index page — no HTML
 *   extractor exists yet (that's W-6 work); these are placeholders
 *   for future extractor authors, not fetchable today.
 * - trust: always 'review' in this draft (per recon instructions),
 *   regardless of source quality — the FEEDS-SERVICE-SPEC trust=auto
 *   promotion is a separate, later decision (W-6).
 * - auto_candidate: true ONLY where the feed is owned by one of the
 *   21 RHT nations AND lives on that nation's own domain AND was
 *   confirmed working at recon time. Google-Calendar-hosted feeds,
 *   related-org feeds (Anishinabek, UCCMM), council feeds (oiatc),
 *   and all html entries are false — either the domain isn't the
 *   nation's own, or there's no extractor yet to trust.
 * - scope: ['event'] and/or ['news'], per what the source actually
 *   contains.
 */

return [

    // --- Mamaweswen / North Shore Tribal Council priority nations ---

    [
        'community' => 'batchewana',
        'kind' => 'rss',
        'url' => 'https://batchewana.ca/feed/',
        'trust' => 'review',
        'scope' => ['news'],
        'auto_candidate' => true,
        'notes' => 'WordPress default feed. Confirmed working, general blog/notices content. Newest item ~6 weeks old at recon time (2026-05-20).',
    ],
    [
        'community' => 'batchewana',
        'kind' => 'html',
        'url' => 'https://batchewana.ca/events/',
        'trust' => 'review',
        'scope' => ['event'],
        'auto_candidate' => false,
        'notes' => 'Events listing page. No ICS (the ?ical=1 param returns the identical HTML page, not calendar data). No extractor written; item dates not verified in recon pass.',
    ],
    [
        'community' => 'garden-river',
        'kind' => 'rss',
        'url' => 'https://www.gardenriver.org/site/feed/',
        'trust' => 'review',
        'scope' => ['news'],
        'auto_candidate' => true,
        'notes' => 'WordPress default feed. Freshest feed of all 21 nations at recon time (newest item 2026-06-30, 2 days old). No events/calendar page found on this site.',
    ],
    [
        'community' => 'thessalon',
        'kind' => 'ics',
        'url' => 'https://calendar.google.com/calendar/ical/6o36k27g790sgchjp99jc4dlnk%40group.calendar.google.com/public/basic.ics',
        'trust' => 'review',
        'scope' => ['event'],
        'auto_candidate' => false,
        'notes' => 'Public Google Calendar embedded on the Thessalon homepage ("Website Events Calendar"). Confirmed working, 154 VEVENTs. auto_candidate is false: the feed is hosted on calendar.google.com, not thessalon\'s own domain, even though the calendar content is nation-curated. No RSS or CMS blog on this site (GoDaddy Website Builder).',
    ],
    [
        'community' => 'serpent-river',
        'kind' => 'rss',
        'url' => 'https://serpentriverfn.com/feed/',
        'trust' => 'review',
        'scope' => ['news'],
        'auto_candidate' => true,
        'notes' => 'WordPress default feed. Confirmed working; newest item ~5.5 months old at recon time (2026-01-16). An ICS is declared (The Events Calendar plugin, /events/?ical=1) but returned HTTP 200 with Content-Length: 0 to the recon UA — excluded from this registry until re-verified with a different UA/retry strategy.',
    ],
    [
        'community' => 'serpent-river',
        'kind' => 'html',
        'url' => 'https://serpentriverfn.com/events/',
        'trust' => 'review',
        'scope' => ['event'],
        'auto_candidate' => false,
        'notes' => 'Events listing page (backs the broken ICS above). No extractor written yet.',
    ],
    [
        'community' => 'sagamok',
        'kind' => 'html',
        'url' => 'https://www.sagamokanishnawbek.com/sagamok-news',
        'trust' => 'review',
        'scope' => ['news'],
        'auto_candidate' => false,
        'notes' => 'Webflow site — this is the CANONICAL sagamok domain (sagamokanishnawbek.com), confirmed live. sagamok.ca (used in minoo\'s MamaweswenNations.php) returns a 301 redirect to this domain — it is an alias, not a separate site; the registry should point here, not at sagamok.ca. No RSS or ICS (Webflow has no blog/calendar feed on this site). Item dates not verified in recon pass.',
    ],
    [
        'community' => 'atikameksheng',
        'kind' => 'rss',
        'url' => 'https://atikamekshenganishnawbek.ca/feed/',
        'trust' => 'review',
        'scope' => ['news'],
        'auto_candidate' => true,
        'notes' => 'WordPress default feed. Confirmed working but very sparse (~1 item in the feed body at recon time). robots.txt declares Crawl-delay: 3. An ICS is declared (/events/?ical=1) but returned the same empty-200 pattern as Serpent River — excluded.',
    ],
    [
        'community' => 'atikameksheng',
        'kind' => 'html',
        'url' => 'https://atikamekshenganishnawbek.ca/events-page/',
        'trust' => 'review',
        'scope' => ['event'],
        'auto_candidate' => false,
        'notes' => 'Events listing page (backs the broken ICS above). No extractor written yet.',
    ],

    // --- Other RHT nations ---

    [
        'community' => 'wahnapitae',
        'kind' => 'html',
        'url' => 'https://wahnapitaefn.ca/notices/events.html',
        'trust' => 'review',
        'scope' => ['event'],
        'auto_candidate' => false,
        'notes' => 'Joomla + DPCalendar extension. No RSS/ICS found. Individual event sub-pages exist under /notices/events/<slug>.html and looked current (2026 items) in nav teasers.',
    ],
    [
        'community' => 'wahnapitae',
        'kind' => 'html',
        'url' => 'https://wahnapitaefn.ca/notices/community-newsletter.html',
        'trust' => 'review',
        'scope' => ['news'],
        'auto_candidate' => false,
        'notes' => 'Community newsletter listing; July 2026 issue linked directly from the homepage at recon time. No feed.',
    ],
    [
        'community' => 'nipissing',
        'kind' => 'rss',
        'url' => 'https://nfn.ca/feed/',
        'trust' => 'review',
        'scope' => ['news'],
        'auto_candidate' => true,
        'notes' => 'WordPress default feed. Confirmed working and very active (newest item 2026-06-30). robots.txt Disallow only covers /events/action~ AJAX endpoints, not this path.',
    ],
    [
        'community' => 'nipissing',
        'kind' => 'html',
        'url' => 'https://nfn.ca/events/',
        'trust' => 'review',
        'scope' => ['event'],
        'auto_candidate' => false,
        'notes' => 'Events listing page with individual /event/<slug>/ pages. No Tribe-Events wp-json alternate declared and ?ical=1 returns the plain HTML page, not calendar data — no confirmed ICS on this site.',
    ],
    [
        'community' => 'dokis',
        'kind' => 'rss',
        'url' => 'https://dokis.ca/feed/',
        'trust' => 'review',
        'scope' => ['news'],
        'auto_candidate' => true,
        'notes' => 'WordPress default feed. Confirmed working; newest item ~8 weeks old at recon time (2026-05-07), matches the dedicated /news/ HTML page. No events/calendar page found.',
    ],
    [
        'community' => 'henvey-inlet',
        'kind' => 'html',
        'url' => 'https://www.hifn.ca/announcements/news.html',
        'trust' => 'review',
        'scope' => ['news'],
        'auto_candidate' => false,
        'notes' => 'Joomla "Announcements" hub with separate News, Newsletters, Events, Job Postings, Community Meetings sub-sections. No feed. Good future HTML-extractor candidate (W-6) — content looked current (July 2026 newsletter linked from homepage).',
    ],
    [
        'community' => 'henvey-inlet',
        'kind' => 'html',
        'url' => 'https://www.hifn.ca/announcements/events.html',
        'trust' => 'review',
        'scope' => ['event'],
        'auto_candidate' => false,
        'notes' => 'Events sub-section of the Announcements hub. No feed.',
    ],
    [
        'community' => 'magnetawan',
        'kind' => 'rss',
        'url' => 'https://www.magfn.com/feed/',
        'trust' => 'review',
        'scope' => ['news'],
        'auto_candidate' => true,
        'notes' => 'WordPress default feed. Confirmed working and active (newest item 2026-06-25). No dedicated events/calendar page found (real news listing is /category/news/, mirrored by this feed).',
    ],
    [
        'community' => 'shawanaga',
        'kind' => 'rss',
        'url' => 'https://shawanagafirstnation.ca/feed/',
        'trust' => 'review',
        'scope' => ['news'],
        'auto_candidate' => true,
        'notes' => 'WordPress default feed. Confirmed working but very sparse (~1 item in feed body). robots.txt declares Crawl-delay: 10.',
    ],
    [
        'community' => 'shawanaga',
        'kind' => 'html',
        'url' => 'https://shawanagafirstnation.ca/events/',
        'trust' => 'review',
        'scope' => ['event'],
        'auto_candidate' => false,
        'notes' => 'Events listing page. No confirmed ICS (?ical=1 returns identical bytes to this page).',
    ],
    [
        'community' => 'wasauksing',
        'kind' => 'rss',
        'url' => 'https://wasauksing.ca/feed/',
        'trust' => 'review',
        'scope' => ['news'],
        'auto_candidate' => true,
        'notes' => 'WordPress default feed. Confirmed working and active (newest item 2026-06-25). Dedicated /news/ HTML page also confirmed current (items 2026-06-11, 2026-06-25).',
    ],
    [
        'community' => 'wasauksing',
        'kind' => 'html',
        'url' => 'https://wasauksing.ca/calendar/',
        'trust' => 'review',
        'scope' => ['event'],
        'auto_candidate' => false,
        'notes' => 'Dedicated calendar page (HTML, not an ICS feed). /events/ 404s — this is the real events surface. No confirmed ICS.',
    ],
    [
        'community' => 'aundeck-omni-kaning',
        'kind' => 'rss',
        'url' => 'https://aokfn.com/feed',
        'trust' => 'review',
        'scope' => ['news', 'event'],
        'auto_candidate' => true,
        'notes' => 'WordPress default feed (declared without a trailing slash — /feed/ 301s to /feed, follow the redirect). Confirmed working; ~5 months stale at recon time (newest 2026-02-19). Site groups news and events on a single /news-and-events page, so scope covers both.',
    ],
    [
        'community' => 'whitefish-river',
        'kind' => 'html',
        'url' => 'https://www.whitefishriver.ca/news',
        'trust' => 'review',
        'scope' => ['news'],
        'auto_candidate' => false,
        'notes' => 'IMPORTANT: this site declares an RSS feed (/feed) that returns HTTP 200 with valid XML, but the feed is DEAD — a single item dated 2016-10-13. The real, current news content (newest item 2026-06-26 at recon time) lives on this HTML page instead. Deliberately excluded the RSS URL from this registry — a feed that validates but returns zero real items would silently starve the pipeline while looking wired up. No extractor written yet for this HTML page.',
    ],
    [
        'community' => 'mchigeeng',
        'kind' => 'rss',
        'url' => 'https://mchigeeng.ca/feed/',
        'trust' => 'review',
        'scope' => ['news'],
        'auto_candidate' => true,
        'notes' => 'WordPress default feed. Confirmed working and active (newest item 2026-06-26). Nav links to /events (no trailing slash); probing /events/ (with slash) 404\'d in recon — likely a slash-normalization quirk, not confirmed absent. Re-check before assuming no events page.',
    ],
    [
        'community' => 'sheguiandah',
        'kind' => 'ics',
        'url' => 'https://sheguiandahfn.ca/calendar/?ical=1',
        'trust' => 'review',
        'scope' => ['event'],
        'auto_candidate' => true,
        'notes' => 'CONFIRMED WORKING ICS — BEGIN:VCALENDAR/VEVENT data returned (also reachable at /events/?ical=1). WordPress + The Events Calendar plugin, own domain. The strongest event source found among all 21 nations.',
    ],
    [
        'community' => 'sheguiandah',
        'kind' => 'rss',
        'url' => 'https://sheguiandahfn.ca/feed/',
        'trust' => 'review',
        'scope' => ['news'],
        'auto_candidate' => true,
        'notes' => 'WordPress default feed. Confirmed working and active (newest item 2026-06-03). Same site as the ICS entry above — best-instrumented nation site in this recon pass.',
    ],
    [
        'community' => 'sheshegwaning',
        'kind' => 'html',
        'url' => 'https://www.sheshegwaning.org/news-events/events',
        'trust' => 'review',
        'scope' => ['event', 'news'],
        'auto_candidate' => false,
        'notes' => 'Custom "Website Builder Gridbox" CMS (not WordPress). No RSS (/feed/ 404s) and no real ICS (?ical=1 returns the plain HTML page, content-type text/html). Combined News & Events section with individual event sub-pages. robots.txt declares Crawl-delay: 60 — the longest of any site in this pass; a future fetcher must respect that.',
    ],
    [
        'community' => 'wiikwemkoong',
        'kind' => 'rss',
        'url' => 'https://www.wiikwemkoong.ca/feed/',
        'trust' => 'review',
        'scope' => ['news'],
        'auto_candidate' => true,
        'notes' => 'WordPress default feed. Confirmed working and very active (newest item 2026-06-30). Richest tech stack of the 21 nations (Divi/Avada + The Events Calendar plugin) but its ICS endpoint (/events/?ical=1, declared via <link rel="alternate" type="text/calendar">) returned HTTP 502 at recon time — excluded from this registry, worth re-checking; could become auto_candidate once fixed.',
    ],
    [
        'community' => 'wiikwemkoong',
        'kind' => 'html',
        'url' => 'https://www.wiikwemkoong.ca/events/',
        'trust' => 'review',
        'scope' => ['event'],
        'auto_candidate' => false,
        'notes' => 'Events listing page (backs the currently-502 ICS above). No extractor written yet.',
    ],

    // zhiibaahaasing: no own-domain site (Facebook-only, confirmed
    // 2026-06-25). No entry — nothing to register.

    // --- oiatc.ca (council-owned, first-party) ---

    [
        'community' => 'oiatc',
        'kind' => 'rss',
        'url' => 'https://oiatc.ca/news/rss.xml',
        'trust' => 'review',
        'scope' => ['news'],
        'auto_candidate' => false,
        'notes' => 'Confirmed working, 8 items, exactly mirrors the /news HTML listing. Not one of the 21 RHT nations, so auto_candidate is false under the strict "nation-owned" reading of the trust rule — but this is first-party council content (originates in oiatc per FEEDS-SERVICE-SPEC §Per-site consequences) and a natural early auto candidate once the worker\'s council-post syndication path (W-5) exists. Full inventory: 8 items, 2025-05-28 to 2026-06-15, not stale.',
    ],

    // --- rhtcircle.ca ---
    // No entry: rhtcircle currently has no news/events nav surface at
    // all (checked 2026-07-02) — it is the intended consumer of this
    // registry (importer lands W-4/W-5), not a source.

    // --- Related orgs (probed per recon instructions, max 3) ---

    [
        'community' => 'anishinabek',
        'kind' => 'rss',
        'url' => 'https://anishinabek.ca/feed/',
        'trust' => 'review',
        'scope' => ['news'],
        'auto_candidate' => false,
        'notes' => 'Union of Ontario Indians / Anishinabek Nation — regional political umbrella, not one of the 21 RHT nations, so auto_candidate is false. Confirmed working and extremely active (newest item 2026-07-01, one day before recon run). Also links out to a sister site anishinabeknews.ca, not probed (out of request budget).',
    ],
    [
        'community' => 'anishinabek',
        'kind' => 'ics',
        'url' => 'https://anishinabek.ca/events/?ical=1',
        'trust' => 'review',
        'scope' => ['event'],
        'auto_candidate' => false,
        'notes' => 'CONFIRMED WORKING ICS (The Events Calendar plugin), real VEVENT data. Same auto_candidate reasoning as the RSS entry above — strong source, but not one of the 21 nations.',
    ],
    [
        'community' => 'uccmm',
        'kind' => 'html',
        'url' => 'http://www.uccmm.ca/news--notices.html',
        'trust' => 'review',
        'scope' => ['news'],
        'auto_candidate' => false,
        'notes' => 'United Chiefs and Councils of Mnidoo Mnising — tribal council umbrella for the Manitoulin/UCCMM member nations. NOTE: only reachable over plain http:// — https:// fails TLS handshake on this domain. Static HTML page-builder site (not WordPress), no feed. robots.txt declares Crawl-delay: 10. A separate /calendar-of-events.html page exists for events but was not added as its own entry (not distinct content verified in this pass).',
    ],

    // Mamaweswen (North Shore Tribal Council, mamaweswen.com — note
    // the working domain is .com, mamaweswen.ca does not resolve at
    // all) was probed and excluded: its declared RSS feed returns
    // HTTP 200 with valid XML but is dead (newest item 2023-03-06,
    // over 3 years stale). No entry — see event-sources-recon.md for
    // detail; a dead feed for the umbrella of the 7 priority nations
    // is worth flagging to a human, not silently registering.

];
