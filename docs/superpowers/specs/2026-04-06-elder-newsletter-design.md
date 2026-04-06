# Elder Newsletter — Design Spec

**Date:** 2026-04-06
**Issue:** waaseyaa/minoo#636
**Milestone:** [#50 Elder Newsletter](https://github.com/waaseyaa/minoo/milestone/50)
**Status:** Design — pending user review

## Why

A Facebook post announcing the Elder Support program surfaced a parallel gap: many Elders don't use computers or devices at all, so they're left out of community happenings entirely. Elder Support helps the ones who can ask for help. This is for the ones who can't even see the announcement.

The fix that fits how Elders actually consume information is the oldest one: paper. A small printed monthly newsletter, like the kind you pick up at a restaurant or community board, automatically assembled from the NorthCloud content pipeline plus community submissions, generated as a print-ready PDF, emailed to a local printer, picked up and distributed by volunteers.

## Decisions Locked In

| Dimension | Decision |
|---|---|
| Scope | Full vision, staged delivery |
| Geography | Start regional, switch to per-community when communities reach critical mass |
| Cadence | Monthly |
| Curation | Hybrid — NC pipeline auto-fills candidate items, coordinator approves |
| Submissions intake | New `newsletter_submission` entity with web form (v1) |
| Submissions follow-up | Issue: email intake (post-v1) |
| PDF rendering | Twig template + `@media print` → headless Chromium via Playwright |
| Print delivery | Auto-email PDF to per-community configured printer email |
| Distribution follow-up | Issue: print job tracking + volunteer assignments (post-v1) |
| Editor role | Reuse existing `community_coordinator` role |

## Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                      EDITORIAL LOOP                          │
│                                                              │
│  NC pipeline ──┐                                             │
│                ├──► NewsletterAssembler ──► newsletter_item │
│  submissions ──┘         (auto-fill)             (queue)    │
│                                                    │         │
│                                                    ▼         │
│  community_coordinator ────► Newsroom UI ──► approve         │
│  (browser)                  (NewsletterEditor                │
│                              Controller)                     │
│                                                    │         │
│                                                    ▼         │
│  NewsletterRenderer ──► Twig + @media print                  │
│         │              (templates/newsletter/                │
│         ▼               edition.html.twig)                   │
│  Symfony\Process ──► node bin/render-pdf.js                  │
│         │              (Playwright headless Chromium)        │
│         ▼                                                    │
│  storage/newsletter/{community}/{vol}-{issue}.pdf            │
│         │                                                    │
│         ▼                                                    │
│  NewsletterDispatcher ──► MailService ──► printer email      │
│         │                                                    │
│         ▼                                                    │
│  ingest_log entry  +  newsletter_edition.status = 'sent'     │
└─────────────────────────────────────────────────────────────┘
```

### Boundaries

- All newsletter logic lives in `src/Domain/Newsletter/` — bounded context, no leakage into other domains.
- Inbound dependencies only: `EntityTypeManager` (entities), `MailService` (dispatch), `NorthCloudClient` (already available via existing provider).
- No new framework features required. Uses existing entity system, access handler, Twig, Symfony Process.
- New production dependency: Chromium runtime, installed via `npx playwright install chromium`. The node helper script lives at `bin/render-pdf.js` (~30 lines: open URL, wait for ready, save PDF).
- Storage: PDFs go to `storage/newsletter/{community-slug-or-regional}/{volume}-{issue}.pdf`. Backed up by existing `storage/` backup strategy.

### Phasing — Regional → Per-Community

`newsletter_edition.community_id` is **nullable**. Null = regional issue. Assembler queries by community when set, falls back to "all communities the assembler is configured to cover" when null. A `config/newsletter.php` file holds per-community settings (printer email, regional cover communities, section quotas, on/off flag). When a community has its own config block with `mode: per_community`, the editor UI hides regional editions for it and shows its own.

No code change to flip regional → per-community: edit config + create the first per-community edition.

## Components

### `src/Domain/Newsletter/`

```
src/Domain/Newsletter/
├── Service/
│   ├── NewsletterAssembler.php
│   ├── NewsletterRenderer.php
│   ├── NewsletterDispatcher.php
│   └── EditionLifecycle.php       # state transition guard
├── ValueObject/
│   ├── EditionStatus.php          # enum: draft|curating|approved|generated|sent
│   ├── SectionQuota.php           # section name + max items
│   └── PdfArtifact.php            # path + bytes + hash
└── Assembler/
    └── ItemCandidate.php          # internal: source_type/id + score + section
```

### Entities (`src/Entity/`)

| Class | Type ID | Keys | Storage |
|---|---|---|---|
| `NewsletterEdition` | `newsletter_edition` | `neid` | content (`_data` JSON) |
| `NewsletterItem` | `newsletter_item` | `nitid` | content (`_data` JSON) |
| `NewsletterSubmission` | `newsletter_submission` | `nsuid` | content (`_data` JSON) |

**`newsletter_edition` fields (in `_data`):**
- `community_id` (nullable string), `volume` (int), `issue_number` (int)
- `status` (`EditionStatus` enum value), `publish_date` (ISO date)
- `pdf_path` (nullable), `pdf_hash` (nullable), `sent_at` (nullable timestamp)
- `created_by`, `approved_by`, `approved_at`

**`newsletter_item` fields:**
- `edition_id`, `position` (int), `section` (string)
- `source_type` (nullable: `post|event|teaching|dictionary_entry|newsletter_submission`)
- `source_id` (nullable)
- `inline_title`, `inline_body` (for items not backed by an entity)
- `editor_blurb` (one-line context), `included` (bool, default true)

**`newsletter_submission` fields:**
- `community_id`, `submitted_by` (user_id), `submitted_at`
- `category` (`birthday|memorial|notice|recipe|language_tip|event|other`)
- `title`, `body` (markdown, max 500 chars)
- `status` (`submitted|approved|included|published|rejected`)
- `approved_by`, `approved_at`, `included_in_edition_id`

### Providers (`src/Provider/`)

`NewsletterServiceProvider` — registers all 3 entity types, registers `NewsletterAssembler` / `NewsletterRenderer` / `NewsletterDispatcher` as singletons (so `SsrPageHandler` auto-injects them via `serviceResolver` fallback). Registers route definitions for both controllers.

### Access (`src/Access/`)

`NewsletterAccessPolicy` — covers all 3 entity types via array attribute:
- `newsletter_edition`: public read on `status in (generated, sent)`. `community_coordinator` of matching `community_id` can view/update at any status. `administrator` can do everything.
- `newsletter_item`: same as parent edition (no independent access).
- `newsletter_submission`: submitter can read their own. `community_coordinator` (matching `community_id`) can read/approve/reject. Public never sees raw submissions.

### Controllers (`src/Controller/`)

**`NewsletterController` (public)**
- `GET /newsletter` — list of communities with editions
- `GET /newsletter/{community}` — list of past editions (most recent first)
- `GET /newsletter/{community}/{volume}-{issue}` — edition view (HTML preview + PDF download link)
- `GET /newsletter/{community}/{volume}-{issue}.pdf` — streams the PDF from disk
- `GET /newsletter/{community}/{volume}-{issue}/print?token=<one-time>` — print template endpoint used by Playwright
- `GET /newsletter/submit` — submission form
- `POST /newsletter/submit` — creates `newsletter_submission`

**`NewsletterEditorController`** (`requireRole('community_coordinator')`)
- `GET /coordinator/newsletter` — list editions (own communities)
- `POST /coordinator/newsletter/new` — creates `newsletter_edition` in `draft`
- `POST /coordinator/newsletter/{id}/assemble` — runs `NewsletterAssembler`
- `GET /coordinator/newsletter/{id}` — newsroom UI (item queue, drag/reorder, blurb editing)
- `POST /coordinator/newsletter/{id}/items/{itemId}` — update position/blurb/included
- `POST /coordinator/newsletter/{id}/approve` — status → `approved`
- `POST /coordinator/newsletter/{id}/generate` — runs `NewsletterRenderer`, status → `generated`
- `POST /coordinator/newsletter/{id}/send` — runs `NewsletterDispatcher`, status → `sent`
- `GET /coordinator/newsletter/submissions` — moderation queue
- `POST /coordinator/newsletter/submissions/{id}/{approve|reject}`

### Templates (`templates/newsletter/`)

- `edition.html.twig` — print template loaded by Playwright. Heavy `@media print` use, multi-column layout via CSS columns or grid, large type, B&W safe.
- `editor/list.html.twig`, `editor/newsroom.html.twig`, `editor/submissions.html.twig` — coordinator UI.
- `public/list.html.twig`, `public/edition.html.twig`, `public/submit.html.twig` — public surface.

### Config (`config/newsletter.php`)

```php
return [
    'enabled' => env('NEWSLETTER_ENABLED', false),
    'mode' => 'regional', // or 'per_community'
    'regional_cover_communities' => ['wiikwemkoong', 'sheguiandah', 'aundeck'],
    'sections' => [
        'news'      => ['quota' => 4, 'sources' => ['post']],
        'events'    => ['quota' => 6, 'sources' => ['event']],
        'teachings' => ['quota' => 2, 'sources' => ['teaching']],
        'language'  => ['quota' => 1, 'sources' => ['dictionary_entry']],
        'community' => ['quota' => 8, 'sources' => ['newsletter_submission']],
    ],
    'communities' => [
        'manitoulin-regional' => [  // v1 launch target
            'mode' => 'regional',
            'printer_email' => 'sales@ojgraphix.com',
            'printer_name' => 'OJ Graphix',
            'printer_phone' => '(705) 869-0199',
            'printer_address' => '7 Panache Lake Road, Espanola, ON P5E 1H9',
            'printer_notes' => 'Hightail uplink fallback: spaces.hightail.com/uplink/OJUpload',
            'editor_emails' => [],
        ],
    ],
    'pdf' => [
        'format' => 'Letter',
        'margins' => ['top' => '0.5in', 'right' => '0.5in', 'bottom' => '0.5in', 'left' => '0.5in'],
    ],
];
```

### Helper script (`bin/render-pdf.js`)

~30-line node script. Takes `--url` and `--out`. Opens URL in headless Chromium via Playwright, waits for `networkidle`, calls `page.pdf({ format: 'Letter', printBackground: true, ... })`. Invoked from `NewsletterRenderer` via `Symfony\Process`.

### Migrations

3 new migrations under `migrations/`:
- `add_newsletter_edition_table.php`
- `add_newsletter_item_table.php`
- `add_newsletter_submission_table.php`

All three follow the content-entity `_data` CLOB schema (per the gotcha in CLAUDE.md).

## Data Flow

### Flow 1 — Editor creates and ships an issue

1. Coordinator visits `/coordinator/newsletter`. `NewsletterEditorController::list()` queries `newsletter_edition` for own communities.
2. Coordinator clicks "New issue for April 2026". `POST /coordinator/newsletter/new {community_id, publish_date}`. `EditionLifecycle::create()` auto-assigns volume + issue number (max+1 for community), creates `newsletter_edition` in `draft`.
3. Coordinator clicks "Auto-fill from NorthCloud". `POST /coordinator/newsletter/{id}/assemble`. `NewsletterAssembler::assemble($edition)`:
   - For each section in config: query source entities by date window + community, score by recency + relevance, take top N per quota
   - Query approved `newsletter_submission` for community
   - Create `newsletter_item` rows ordered by section + score
   - Status → `curating`
4. Coordinator opens newsroom UI. `GET /coordinator/newsletter/{id}` loads edition + items grouped by section. Coordinator drags/reorders, edits blurb, toggles `included`, swaps items.
5. Coordinator clicks "Approve". `POST .../approve`. `EditionLifecycle::transition(curating → approved)`. Status, `approved_by`, `approved_at` set.
6. Coordinator clicks "Generate PDF". `POST .../generate`. `NewsletterRenderer::render($edition)`:
   - Generate signed preview URL (loopback to local PHP server) `/newsletter/{community}/{vol}-{issue}/print?token=<one-time>`
   - `Symfony\Process` invokes `node bin/render-pdf.js --url=<preview-url> --out=<storage-path>`
   - Wait for process exit (timeout 60s)
   - Verify PDF exists, non-zero bytes, read SHA256
   - Status → `generated`, `pdf_path` and `pdf_hash` set
7. Coordinator clicks "Send to printer". `POST .../send`. `NewsletterDispatcher::dispatch($edition)`:
   - Resolve `community.printer_email` from config
   - `MailService::sendWithAttachment(printer_email, subject, body, pdf_path)`
   - Write `ingest_log` entry (`kind=newsletter_dispatch`, success/fail, hash)
   - Status → `sent`, `sent_at` = now
   - Flash success → redirect to edition view

### Flow 2 — Community member submits a notice

1. Logged-in member visits `/newsletter/submit`.
2. `POST /newsletter/submit {category, title, body}`. `NewsletterController::submit()` resolves community from user's session location, creates `newsletter_submission` (status=`submitted`, `submitted_by=current_user`), flashes success.
3. Coordinator visits `/coordinator/newsletter/submissions`.
4. `POST .../submissions/{id}/approve`. Submission status → `approved`. Becomes available to `NewsletterAssembler` on next assemble.

### Flow 3 — Public reads a past edition

1. `GET /newsletter/wiikwemkoong/3-2`. `NewsletterController::show()` loads edition where `community_id=wiikwemkoong AND volume=3 AND issue_number=2`. Access check: `status in (generated, sent)` → public OK. Renders `public/edition.html.twig`.
2. `GET /newsletter/wiikwemkoong/3-2.pdf` streams `pdf_path` from disk via `BinaryFileResponse`.

### Print preview URL token

Playwright needs to fetch the print template *as if it were the editor*. We use a one-time token endpoint:
- Token generated by `NewsletterRenderer` before invoking Playwright
- Single-use, 60s TTL
- Stored in `storage/newsletter/render-tokens.json` (or a small `render_token` table — implementer's choice)
- Validated by `NewsletterController::printPreview()`, deleted on first use
- Scoped to one edition

This avoids cookie passthrough fragility and Twig-to-`file://` asset rewriting headaches.

## Error Handling

| Failure | Detection | Response | State |
|---|---|---|---|
| Assembler finds no candidates | `count(items) == 0` after assemble | Flash error: "No content found for this date window." | edition stays in `draft`, no items written |
| Source entity deleted between assemble and render | `EntityLoader::load()` returns null inside Twig template | Template skips item silently, logs warning to `ingest_log` (`kind=newsletter_render_warning`) | render proceeds; missing items dropped |
| Renderer: token endpoint unreachable | `node bin/render-pdf.js` exits non-zero, stderr captured | `RenderException` thrown with stderr; controller catches → flash error | edition stays `approved`, recoverable |
| Renderer: Playwright crash / Chromium missing | Process exit code != 0, no PDF written | Same; surface `npx playwright install chromium` hint | recoverable |
| Renderer: PDF written but zero bytes | post-render `filesize() == 0` check | delete file, throw `RenderException('zero-byte PDF')` | recoverable |
| Renderer: timeout (>60s) | `Symfony\Process::run()` timeout | kill process, throw `RenderException('timeout')`, log duration + edition id | recoverable |
| Render token expired during render | controller returns 410 Gone, render-pdf.js exits non-zero | Same as renderer error | recoverable; new token issued on retry |
| Dispatcher: MailService not configured | `MailService::isConfigured() === false` | throw `DispatchException('mail not configured')` immediately | edition stays `generated`; coordinator falls back to manual email |
| Dispatcher: SendGrid 4xx/5xx | `MailService` returns error | log to `ingest_log` (`success=false`), throw `DispatchException` | edition stays `generated`, NOT marked sent. Recoverable. |
| Dispatcher: printer email missing in config | resolved value is null | throw `DispatchException('no printer_email for community {slug}')` | requires config fix |
| Submission spam / abuse | rate limit on `POST /newsletter/submit` (existing `RateLimitMiddleware`) | 429 + flash error | n/a |
| Submission body too long | server-side validation: `strlen($body) > 500` | flash error, redirect with input preserved | n/a |
| Concurrent edits (two coordinators editing same item queue) | last-write-wins | acceptable for v1 — small editor pool. Add optimistic locking only if it bites. | n/a |
| Status transition violation (e.g. send before generate) | `EditionLifecycle::transition()` validates allowed transitions, throws `InvalidStateTransition` | controller catches → flash error, no state change | UI shouldn't expose the action in this state anyway |
| Edition deleted while PDF exists on disk | `NewsletterServiceProvider::register()` adds an entity event listener | listener deletes `pdf_path` from disk on `newsletter_edition` delete | n/a |

**Logging surface:** all `RenderException` / `DispatchException` failures write to `ingest_log` with `kind=newsletter_*` so coordinators (and admins) can review failures via the existing ingestion dashboard. No new dashboard needed for v1.

**Status invariant:** every transition runs through `EditionLifecycle::transition()`. The renderer/dispatcher never write status directly. This guarantees you can't get an edition stuck in `generated` with no PDF on disk, or `sent` with no `sent_at`.

**No silent fallbacks:** assembler running on empty content, mail service unconfigured, printer email missing — all surface visibly. No "succeeded with empty issue" outcomes.

## Testing

### Unit tests (`tests/Minoo/Unit/Newsletter/`)

| File | What it locks down |
|---|---|
| `EntityTest/NewsletterEditionTest.php` | Construction, field defaults, status enum, volume/issue uniqueness within community |
| `EntityTest/NewsletterItemTest.php` | Source-backed vs inline construction, position validation |
| `EntityTest/NewsletterSubmissionTest.php` | Category enum, body length cap, status transitions |
| `Service/EditionLifecycleTest.php` | Every legal and illegal transition |
| `Service/NewsletterAssemblerTest.php` | Section quotas, recency scoring, approved-only submissions, idempotency |
| `Service/NewsletterRendererTest.php` | Mock `Symfony\Process` — verifies command args, handles exit code 0 / non-zero / timeout / zero-byte |
| `Service/NewsletterDispatcherTest.php` | Mocks `MailService` — `isConfigured()` guard, attachment passed, `ingest_log` written, status only flipped on success |
| `Access/NewsletterAccessPolicyTest.php` | Public can read sent/generated, can't read draft; coordinator only own community; admin everything |
| `Controller/NewsletterControllerTest.php` | Public list, edition view, PDF download stream, submission form (valid + invalid + length cap + rate limit) |
| `Controller/NewsletterEditorControllerTest.php` | Role gating, each lifecycle action calls correct service, error responses for failed transitions, submission moderation |

### Integration test

`tests/Minoo/Integration/NewsletterEndToEndTest.php`:
- Boots `HttpKernel` with `:memory:` SQLite
- Seeds 1 community, 5 events, 3 teachings, 2 approved submissions, 1 unapproved
- Creates edition, assembles, asserts items distributed across sections per quota
- Approves edition, asserts status transitions
- Skips actual PDF render (no Chromium in CI) — asserts `NewsletterRenderer::render()` invoked correctly
- Skips actual dispatch — asserts `MailService` invoked with correct attachment
- Transitions through `sent`, asserts `ingest_log` entry exists

### Playwright smoke (`tests/playwright/newsletter.spec.ts`)

- Public list page renders, shows past editions
- Public edition view shows item list + PDF download link
- Submission form: happy path + 500-char limit + CSRF
- Coordinator newsroom UI: requires login, list page renders, "new edition" button visible
- **Cannot test PDF generation in CI.** Separate `bin/newsletter-render-smoke` script runs the full render against a fixture edition. Run locally and on production server, **not** in CI.

### Fixtures (`tests/Fixtures/Newsletter/`)

- `fixture-edition.php` — deterministic edition with 8 items across sections
- `fixture-submission-batch.php` — 10 submissions across all categories

### Deferred (post-v1)

- Visual regression: render fixture edition to PDF, convert page 1 to PNG, compare against baseline
- Real SendGrid → printer email round trip (manual dry-run on staging)
- Real Chromium PDF output across operating systems (test on prod box once)

**Test count budget:** ~50 new PHPUnit tests, ~6 Playwright tests. Suite grows from 442 → ~492.

## Out of Scope (v1)

These are real workstreams with their own follow-up issues — explicitly NOT in v1:

- **Email submission intake** — community members emailing notices to a per-community address
- **Print job tracking** — `print_job` entity tracking sent → printed → picked-up → distributed states
- **Volunteer distribution assignments** — volunteers claiming routes/zones (Elder homes, community centers, restaurants, bingo halls) and checking off deliveries
- **Visual regression on print template** — PNG baseline comparison
- **Per-community config admin UI** — for v1, `config/newsletter.php` is edited directly
- **Newsletter editor as a separate role** — reusing `community_coordinator` until usage shows we need to split

## v1 Implementation Issue Order

A suggested merge order for the v1 GitHub issues, dependencies first:

1. **Migrations + entities** — `newsletter_edition`, `newsletter_item`, `newsletter_submission` tables, classes, provider, access policy, unit tests
2. **`EditionLifecycle` + state machine tests**
3. **`NewsletterAssembler` + tests** — pulls from existing entity stores
4. **`NewsletterEditorController` newsroom UI** (assemble → curating → approve flow, no PDF yet)
5. **Print template** — `templates/newsletter/edition.html.twig` + `@media print` CSS
6. **`bin/render-pdf.js`** node helper + render token endpoint
7. **`NewsletterRenderer` + tests + `generate` action**
8. **`NewsletterDispatcher` + tests + `send` action + per-community config**
9. **Public surface** — `NewsletterController` list/show/PDF download, `submit` form, submission moderation queue
10. **Playwright smoke + manual render smoke script**

## Follow-up Issues (post-v1)

- Email submission intake (parses inbound email to `newsletter_submission`)
- Distribution tracking — `print_job` entity, status workflow, dashboard
- Volunteer route/zone assignments + check-off UI
- Visual regression baseline for print template
- Per-community config admin UI
- Optional: split out `newsletter_editor` role

## v1 Prerequisites (external, blocking dispatcher PR)

**OJ Graphix confirmation call/email** — before merging the dispatcher PR (#7 in the implementation order), confirm with our v1 print partner (OJ Graphix, Espanola ON):

1. PDF email submission to `sales@ojgraphix.com` is acceptable (vs Hightail-only)
2. Standard RGB PDF acceptable for B&W jobs (or do we need PDF/X-1a CMYK + Ghostscript post-processing)
3. Bleed/trim requirements
4. Recommended binding + paper size for 100-500 copy monthly Elder booklet
5. Lead time + ballpark price (gates economic viability, affects editor's monthly approval deadline)

Email sent 2026-04-06. Replies fold back into spec + dispatcher implementation. The other 9 v1 issues are not blocked on this — only the dispatcher and (potentially) the renderer need their answers.

## Follow-up Issue Additions From OJ Graphix Research

- **Manual upload mode dispatcher** — for shops that require Hightail/Dropbox/web upload instead of email. Generates PDF, surfaces "Download for upload" link in editor UI, marks edition `awaiting_upload` instead of `sent`. Coordinator marks `sent` after manual upload.
- **Ghostscript PDF/X-1a CMYK post-processing** — only if a print partner requires it. Adds a Ghostscript binary dep to production.

## Open Questions

None blocking design. The OJ Graphix prerequisites above are open at the implementation boundary, not the design boundary — the architecture handles either answer.
