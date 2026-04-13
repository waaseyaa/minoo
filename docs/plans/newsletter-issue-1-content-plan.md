# Elder Newsletter Issue #1 — Content Plan

Issue #692. This document is the source of truth for section order, quotas, page budget, and editorial voice for the first printed edition.

---

## Section Manifest (print order)

| # | Section | Slug | Classification | Quota | Sources |
|---|---------|------|---------------|-------|---------|
| 1 | Cover | `cover` | inline | n/a | Hand-authored masthead, issue title, regional community names |
| 2 | Editor's Note | `editors_note` | inline | n/a | Hand-authored welcome, table of contents |
| 3 | Community News | `news` | entity-driven | 3 | `post` |
| 4 | Events | `events` | entity-driven | 5 | `event` |
| 5 | Teachings | `teachings` | entity-driven | 2 | `teaching` |
| 6 | Language Corner | `language` | entity-driven | 1 | `dictionary_entry` |
| 7 | Community Voices | `community` | entity-driven | 4 | `newsletter_submission` |
| 8 | Jokes & Humour | `jokes` | inline | n/a | Hand-authored jokes appropriate for Elders |
| 9 | Puzzles | `puzzles` | inline | n/a | Hand-authored word search, crossword, or trivia |
| 10 | Anishinaabe Horoscope | `horoscope` | inline | n/a | Hand-authored seasonal horoscope by clan |
| 11 | Elder Spotlight | `elder_spotlight` | inline | n/a | Hand-authored Elder profile or interview |
| 12 | Back Page | `back_page` | inline | n/a | Contact info, next issue date, miigwech |

**Entity-driven total:** 15 items (3 + 5 + 2 + 1 + 4)
**Inline sections:** 7 (cover, editor's note, jokes, puzzles, horoscope, elder spotlight, back page)

---

## Page Budget (12-page Letter booklet)

| Pages | Section | Notes |
|-------|---------|-------|
| 1 | Cover | Masthead, issue date, "Serving Wiikwemkoong, Sheguiandah & Aundeck Omni Kaning" |
| 2 | Editor's Note | Welcome, brief table of contents, seasonal greeting |
| 3--4 | Community News (3 posts) | ~half page each, title + blurb + source date |
| 5--6 | Events (5 events) | Compact cards: name, date, location, one-line description |
| 7 | Teachings (2 teachings) | Title, source/Knowledge Keeper attribution, 2--3 sentence summary |
| 8 | Language Corner (1 entry) | Featured word with pronunciation, definition, example sentence, mini word list sidebar |
| 9--10 | Community Voices (4 submissions) | Letters, announcements, notices from community members |
| 11 (top) | Jokes & Humour | 2--3 short jokes, half page |
| 11 (bottom) | Puzzles | Word search or crossword, half page |
| 12 (top half) | Anishinaabe Horoscope | Seasonal horoscope by clan animal, half page |
| 12 (middle) | Elder Spotlight | Brief Elder profile (~150 words), quarter page |
| 12 (bottom) | Back Page | Community contacts, "Next issue: [month]", miigwech closing |
| **12** | **Total** | |

---

## Section Details

### Cover (inline)

- Masthead: "Minoo Elder Newsletter" wordmark
- Subtitle: "Issue #1 -- [Season] 2026"
- Regional tagline: "Serving Wiikwemkoong, Sheguiandah & Aundeck Omni Kaning"
- No entity content; purely editorial layout

### Editor's Note (inline)

- 150--200 words
- Introduces the newsletter's purpose: connecting Elders to community happenings
- Brief preview of what's inside (not a formal TOC, just a warm summary)
- Signed by editor name or "The Minoo Team"

### Community News (entity-driven)

- **Quota:** 3 posts, sorted by recency (assembler `scoreByRecency`)
- **Source:** `post` entity type
- **Per-item layout:** Title, 2--3 sentence blurb (from `editor_blurb`), publish date
- **Assembler note:** Assembler writes `editor_blurb` from post title; editor refines during Curating phase

### Events (entity-driven)

- **Quota:** 5 events, sorted by recency
- **Source:** `event` entity type
- **Per-item layout:** Event name, date, location, one-line description
- **Constraint:** Only future or very recent events (assembler scores by recency; stale events naturally rank low)

### Teachings (entity-driven)

- **Quota:** 2 teachings
- **Source:** `teaching` entity type
- **Per-item layout:** Title, Knowledge Keeper or source attribution, 2--3 sentence summary
- **Constraint:** Prioritize teachings with named sources for print credibility

### Language Corner (entity-driven)

- **Quota:** 1 dictionary entry (featured word)
- **Source:** `dictionary_entry` entity type
- **Per-item layout:** Anishinaabemowin word, pronunciation guide, English definition, example sentence
- **Inline supplement:** Editor may add a sidebar of 3--5 additional words (hand-authored, not assembled)

### Community Voices (entity-driven)

- **Quota:** 4 approved submissions
- **Source:** `newsletter_submission` entity type
- **Per-item layout:** Title, author/community attribution, submission text (may be trimmed for space)
- **Constraint:** Only `status = approved` submissions (assembler already filters this)

### Jokes & Humour (inline)

- 2--3 short, clean jokes appropriate for Elders
- Warm, community-oriented humour — nothing mean-spirited
- May include Anishinaabe wordplay or bilingual puns when natural
- Not assembled from entities; hand-authored per issue

### Puzzles (inline)

- One puzzle per issue: word search, crossword, or trivia quiz
- Theme should connect to the season or a teaching from this issue
- Include answer key (small print, inverted at bottom of section)
- Not assembled from entities; hand-authored per issue

### Anishinaabe Horoscope (inline)

- Seasonal horoscope organized by clan animal (Bear, Loon, Crane, etc.)
- Light, positive, grounded in seasonal rhythms — not astrology
- 1--2 sentences per clan, plus a seasonal message for all
- Not assembled from entities; hand-authored per issue

### Elder Spotlight (inline)

- Hand-authored Elder profile or interview
- ~150 words (reduced from 300 to share page 12 with horoscope + back page)
- Not assembled from entities; written by editor for each issue
- Should name the Elder, their community, and what they want readers to know

### Back Page (inline)

- Community coordinator contact info (phone, email)
- "Next issue" date
- Miigwech closing message
- Optional: volunteer call-to-action ("Want to help? Call...")

---

## Editorial Voice Guidelines

All newsletter copy follows [docs/content-tone-guide.md](../content-tone-guide.md) with these print-specific additions:

### Newsletter-Specific Guidance

1. **Write for reading aloud.** Many Elders will hear this content read by a family member or volunteer. Sentences should sound natural spoken. Avoid parenthetical asides and nested clauses.

2. **One idea per sentence.** Print has no hyperlinks, no "click here." Each sentence must stand on its own.

3. **Name people and places.** Every teaching credits a Knowledge Keeper. Every event names a location. Every community voice has an author. Print is permanent; attribution matters.

4. **Seasonal, not dated.** Use "this spring" or "in May" rather than "on 2026-05-14." The newsletter may sit on a counter for weeks. Exact dates only for events.

5. **Editor blurbs are summaries, not teasers.** Unlike web blurbs, print blurbs must deliver the core information. There is no "Read more" link. Write the whole thought.

6. **Warm close, every section.** End each section with a sentence that connects back to community. Not a call to action (this isn't a website), but a reason to keep reading or to share the newsletter.

### Section Intro Pattern

Each entity-driven section opens with a one-sentence intro written by the editor:

- **Community News:** "Here's what's been happening across our communities."
- **Events:** "Gatherings, ceremonies, and community events coming up."
- **Teachings:** "Living knowledge from our Knowledge Keepers."
- **Language Corner:** "A word to carry with you this month."
- **Community Voices:** "In your own words."

### Tone Reminders (from content-tone-guide.md)

- First-person plural ("we," "our") or second-person ("you")
- Capitalize Elder, Knowledge Keeper, Teachings
- Anishinaabemowin terms with English context, never as decoration
- No corporate speak, no jargon, no "users" or "content"

---

## Assembler Constraints

- The `NewsletterAssembler` processes entity-driven sections in config order (the `sections` key in `config/newsletter.php`)
- Inline sections (cover, editor's note, jokes, puzzles, horoscope, elder spotlight, back page) are defined in `config/newsletter.php` under `inline_sections` but are **not** processed by the assembler; they are seeded via `scripts/seed-inline-sections.php`
- Config `sections` key must list entity-driven sections in print order: `news`, `events`, `teachings`, `language`, `community`
- Quotas in config must match this plan: news=3, events=5, teachings=2, language=1, community=4
