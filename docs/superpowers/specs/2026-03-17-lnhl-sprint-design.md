# Little NHL Sprint — Content Acquisition & Business Entity Split

**Date:** 2026-03-17
**Status:** Draft
**Sprint window:** Today (Little NHL Day 3 of 5 — Mar 15-19, 2026)

## Context

The Little Native Hockey League (LNHL) 2026 tournament is happening right now in Markham, Ontario. 271 teams, 4,500+ players, and 10-15K attendees from virtually every Ontario First Nation are congregating in one place. This is a high-leverage opportunity for Minoo to gain traction with these communities.

Simultaneously, Nginaajiiw Salon & Spa (owned by Larissa Toulouse) is currently miscategorized as a "group" when it should be a business — and its page needs to be as polished as possible with real social content.

## Goals

1. **Scrape together LNHL datasets** via Python scripts — event, teams/communities, teaching content, media
2. **Register Meta developer app** and scrape Nginaajiiw's FB/Insta content immediately
3. **Split businesses from groups** — `/businesses` route + dedicated template
4. **Polish Nginaajiiw business page** with scraped content, owner card, social feed
5. **Create LNHL content** — event entity, teaching entity, community enrichment

## Non-Goals

- North-cloud code changes (deferred — Python scripts bridge the gap)
- Meta Graph API integration into north-cloud (deferred until app approval)
- Sub-events per arena/day (future enhancement)
- Auto-creating communities not already in Minoo (flag only)

---

## Section 1: Meta App Registration & Social Scraping

### Meta Developer App
- Register app at developers.facebook.com
- Request `pages_read_engagement`, `instagram_basic_display` permissions
- Async — approval takes hours to days, don't block on it

### Scraping Fallback Plan
Facebook aggressively blocks scrapers and may return login walls. Instagram rate-limits automated access. If scraping fails:
- **Fallback:** Manual content entry from publicly visible page info (copy-paste from browser)
- **Priority order if time is short:** Website scrape first (most reliable), then Instagram (instaloader), then Facebook (least reliable)

### Python Scraping (immediate, no API key needed)

**`scripts/scrape_nginaajiiw_website.py`**
- Target: https://nginaajiiw-salon-spa.square.site/
- Extract: services list, hours, contact info, booking link, about text, images
- Output: `data/nginaajiiw_website.json`

**`scripts/scrape_nginaajiiw_social.py`**
- Targets:
  - Facebook: https://www.facebook.com/people/Nginaajiiw-Salon-Spa/100083560024537/
  - Instagram: https://www.instagram.com/nginaajiiw.salonandspa/
- Extract: recent posts (text, image URLs, dates, engagement counts)
- Tools: `instaloader` for Instagram public profiles, Facebook public page scraping
- Output: `data/nginaajiiw_social.json`

### Output Schema: `data/nginaajiiw_website.json`
```json
{
  "name": "Nginaajiiw Salon & Spa",
  "description": "...",
  "services": ["Hair Services", "Esthetics", "..."],
  "hours": { "mon": "9-5", "...": "..." },
  "phone": "...",
  "email": "...",
  "address": "...",
  "booking_url": "https://nginaajiiw-salon-spa.square.site/book",
  "website": "https://nginaajiiw-salon-spa.square.site/",
  "images": [{ "url": "...", "alt": "...", "type": "hero|gallery" }],
  "owner": "Larissa Toulouse"
}
```

### Output Schema: `data/nginaajiiw_social.json`
```json
{
  "facebook": {
    "page_url": "...",
    "posts": [
      {
        "text": "...",
        "image_url": "...",
        "date": "2026-03-15",
        "permalink": "...",
        "type": "photo|text|video"
      }
    ]
  },
  "instagram": {
    "profile_url": "...",
    "posts": [
      {
        "caption": "...",
        "image_url": "...",
        "date": "2026-03-15",
        "permalink": "...",
        "type": "image|carousel|reel"
      }
    ]
  }
}
```

---

## Section 2: Little NHL Content Acquisition

### Python Scripts

**`scripts/scrape_lnhl.py`**
- Targets: lnhl.ca, spordle.com (schedules), anishinabeknews.ca, visitmarkham.ca/lnhl/
- Extract: tournament metadata, team list with community names, schedules, standings

**`scripts/scrape_lnhl_content.py`**
- Targets: Canadian Geographic (50 years article), NHL.com (Color of Hockey), Wikipedia, Anishinabek News
- Extract: origin story, four pillars, cultural significance narrative
- This is teaching content — requires editorial curation, not just raw scrape

**`scripts/scrape_lnhl_media.py`**
- Targets: lnhl.ca gallery, arena photos, official logos/banners
- Extract: image URLs, captions, attribution info
- Download to `data/media/lnhl/`

### Output Datasets

**`data/lnhl_event.json`**
```json
{
  "name": "Little NHL 2026",
  "dates": { "start": "2026-03-15", "end": "2026-03-19" },
  "location": "Markham, Ontario",
  "host_nation": "Wiikwemkoong Unceded Territory",
  "venues": [
    { "name": "Centennial Community Centre", "address": "..." },
    { "name": "Angus Glen Community Centre", "address": "..." },
    { "name": "Mount Joy Community Centre", "address": "..." }
  ],
  "divisions": ["Tyke", "Novice", "Atom", "Peewee", "Bantam", "Midget", "Girls"],
  "description": "...",
  "total_teams": 271,
  "total_players": 4500,
  "sponsors": ["Hydro One", "Indigenous Tourism Ontario"],
  "urls": {
    "official": "https://lnhl.ca",
    "schedules": "https://spordle.com/...",
    "markham": "https://visitmarkham.ca/lnhl/"
  }
}
```

**`data/lnhl_teams.json`**
```json
[
  {
    "team_name": "Wiikwemkoong Hawks",
    "community": "Wiikwemkoong Unceded Territory",
    "division": "Bantam",
    "wins": 2,
    "losses": 1,
    "ties": 0
  }
]
```

**`data/lnhl_teaching.json`**
```json
{
  "title": "The Little Native Hockey League",
  "type": "history",
  "origin": "Founded in 1971 in Little Current, Manitoulin Island...",
  "founders": ["Earl Abotossaway"],
  "four_pillars": ["Education", "Citizenship", "Sportsmanship", "Respect"],
  "significance": "Created to counter racism in mainstream hockey...",
  "notable_alumni": [{ "name": "Ted Nolan", "nation": "Ojibwe", "achievement": "NHL coach" }],
  "sources": [
    { "url": "https://canadiangeographic.ca/articles/...", "title": "..." },
    { "url": "https://www.nhl.com/news/...", "title": "..." }
  ]
}
```

**`data/lnhl_media.json`**
```json
[
  {
    "url": "...",
    "filename": "lnhl-2026-opening.jpg",
    "caption": "...",
    "attribution": "LNHL / photographer name",
    "use_for": ["event_hero", "teaching"]
  }
]
```

**`data/community_enrichment.json`**
```json
{
  "matched": [
    {
      "lnhl_team": "Wiikwemkoong Hawks",
      "minoo_community_id": 42,
      "minoo_community_name": "Wiikwemkoong Unceded Territory",
      "match_method": "name_exact",
      "enrichment_needed": ["media", "description"]
    }
  ],
  "unmatched": [
    {
      "lnhl_team": "Some Team",
      "community_name": "Some First Nation",
      "action": "manual_review"
    }
  ]
}
```

---

## Section 3: Business Entity Split & Template

### Route Separation

New `BusinessController` (lightweight, delegates to group storage with `type=business` filter):
- `list()` → fetch groups where `type=business`, render `businesses.html.twig`
- `show($slug)` → fetch group by slug + verify `type=business`, render `businesses.html.twig`

Routes registered in `GroupServiceProvider`:
- `GET /businesses` → `BusinessController::list`
- `GET /businesses/{slug}` → `BusinessController::show`

Existing `/groups` listing filters OUT `type=business`.

### Business Detail Template: `templates/businesses.html.twig`

```
┌─────────────────────────────────────┐
│ Hero image (full-width)             │
├──────────────────┬──────────────────┤
│ Business name    │ Booking CTA      │
│ Community        │ Phone / Email    │
│ Description      │ Address          │
│                  │ Website link     │
├──────────────────┴──────────────────┤
│ Owner card (from linked             │
│ ResourcePerson: photo, name, bio)   │
├─────────────────────────────────────┤
│ Latest from social (FB/Insta posts) │
│ - Post text + image thumbnail       │
│ - 3-6 most recent posts             │
│ - Links out to original post        │
├─────────────────────────────────────┤
│ Services offered (from offerings    │
│ taxonomy or scraped data)           │
└─────────────────────────────────────┘
```

### Business Listing Template

Card-based grid matching existing Minoo patterns:
- Business name, hero thumbnail, community, short description
- Link to detail page

### Social Feed Storage

New `social_posts` field on group entity (text_long, stores JSON):
```json
[
  {
    "source": "instagram",
    "text": "...",
    "image_url": "...",
    "date": "2026-03-15",
    "permalink": "..."
  }
]
```

Template reads and renders this as a card grid. No separate entity needed.

### Groups Template Update

`/groups` listing excludes `type=business` to keep community groups and businesses separate.

---

## Section 4: LNHL Content in Minoo

### Event Entity
- Name: "Little NHL 2026"
- Type: `tournament` (new taxonomy term in `event_type` vocabulary)
- Dates: Mar 15-19, 2026
- Location: Markham, Ontario
- Description: from `data/lnhl_event.json`
- Media: hero image from `data/lnhl_media.json`

### Teaching Entity
- Name: "The Little Native Hockey League"
- Type: `history` (new taxonomy term in `teaching_type` vocabulary if needed)
- Content: origin story, four pillars, significance from `data/lnhl_teaching.json`
- Media: supporting images from `data/lnhl_media.json`
- Evergreen — continues to drive value after tournament ends

### Community Enrichment
- Match LNHL team communities against existing Minoo entities (name + INAC ID)
- For matches: verify data quality, add missing fields, attach LNHL participation
- For gaps: flag for manual review — do NOT auto-create
- Output audit log for review

### Taxonomy Additions
- `event_type`: add `tournament` to `ConfigSeeder::eventTypes()` (existing method has powwow, gathering, ceremony)
- `teaching_type`: `history` already exists in `ConfigSeeder::teachingTypes()` — no change needed

### Data Mapping Notes
- Event entity label key is `title` (not `name`) — scraper JSON uses `name`, population step must map `name` → `title`
- Group entity label key is `name` — maps directly from scraper JSON
- ResourcePerson `roles` and `offerings` are `entity_reference` fields pointing to taxonomy terms in `person_roles` and `person_offerings` vocabularies — population must resolve term IDs (e.g., look up "Small Business Owner" term ID before setting the field). Seeds already include these terms.

### Schema Migration Notes
- Adding `social_posts` field to group entity triggers schema drift: production SQLite table already exists, requires `ALTER TABLE group ADD COLUMN social_posts TEXT`
- Changing Nginaajiiw's type from `offline` to `business` requires a database UPDATE on production, not just a code change
- Run `bin/waaseyaa schema:check` after adding the field to detect drift

### Nginaajiiw Business Page
- Update group type: `offline` → `business`
- Populate all fields from `data/nginaajiiw_website.json`
- Set `social_posts` field from `data/nginaajiiw_social.json`
- Create/update Larissa Toulouse as ResourcePerson with:
  - `business_name`: "Nginaajiiw Salon & Spa"
  - `linked_group_id`: reference to the group entity
  - `roles`: Small Business Owner
  - `offerings`: Hair Services, Esthetics (+ others from scrape)
- Attach hero image from scraped website

---

## Implementation Sequence

### Phase 1 — Data Acquisition (Python, parallel-safe)
1. Register Meta developer app (5 min, then async)
2. `scripts/scrape_nginaajiiw_website.py` → `data/nginaajiiw_website.json`
3. `scripts/scrape_nginaajiiw_social.py` → `data/nginaajiiw_social.json`
4. `scripts/scrape_lnhl.py` → `data/lnhl_event.json`, `data/lnhl_teams.json`
5. `scripts/scrape_lnhl_content.py` → `data/lnhl_teaching.json`
6. `scripts/scrape_lnhl_media.py` → `data/lnhl_media.json`, `data/media/lnhl/`
7. Cross-reference communities → `data/community_enrichment.json`

### Phase 2 — Minoo Platform (parallel with Phase 1 where no data dependency)
1. Create `scripts/` and `data/` directories
2. Add `social_posts` field to group entity in `GroupServiceProvider`
3. Create `BusinessController` with `list()` and `show()` methods
4. Register `/businesses` routes in `GroupServiceProvider` (with `->allowAll()`)
5. Create `templates/businesses.html.twig` (listing + detail)
6. Create `templates/components/business-card.html.twig`
7. Add business detail CSS to `public/css/minoo.css` (`@layer components`)
8. Update `/groups` listing to exclude `type=business`
9. Add `tournament` term to `ConfigSeeder::eventTypes()`
10. Write `BusinessControllerTest` (unit test for list/show)

### Phase 3 — Content Population (requires Phase 1 data + Phase 2 templates)
1. Update Nginaajiiw group: type → business, populate all fields
2. Create/update Larissa Toulouse ResourcePerson, link to business
3. Inject social posts into Nginaajiiw entity
4. Create Little NHL 2026 event entity
5. Create Little NHL teaching entity
6. Run community enrichment pass
7. Attach media to all entities

### Phase 4 — Verification
1. Verify `/businesses` listing shows Nginaajiiw
2. Verify `/businesses/nginaajiiw-salon-spa` detail page renders correctly
3. Verify social feed section displays posts
4. Verify owner card links to Larissa's people page
5. Verify `/events` shows Little NHL 2026
6. Verify `/teachings` shows LNHL teaching
7. Verify `/groups` no longer shows businesses
8. Run full PHPUnit suite
9. Run Playwright smoke tests
