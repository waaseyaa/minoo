# Newsletter Builder MVP — Design Spec

**Date:** 2026-04-13
**Goal:** Admin SPA newsletter builder that assembles content from real entities, previews the layout, generates a print-ready PDF, and sends it to the printer.

## Context

Issue #1 of the Elder Newsletter was produced via scripts — a backport seeder (`scripts/backport-edition-1-content.php`) populating 25 inline `newsletter_item` entities, a Twig print template (`templates/newsletter/edition.html.twig`), and a Playwright-based PDF renderer (`bin/render-pdf.js`). The result is a 12-page, letter-size, 83 KB PDF.

The entities, templates, and PDF pipeline all work. What's missing is an editorial interface — a way to assemble, preview, and produce the newsletter from the admin SPA instead of through scripts.

## Scope

**In scope (MVP):**
- Framework upgrade to Waaseyaa alpha.140 (prerequisite)
- Admin SPA pages for edition management and item assembly
- Entity picker (type-ahead search across posts/events/teachings/dictionary_entries)
- Inline item creation (paste pre-formed content)
- Section-based item organization with reordering
- HTML preview (iframe rendering the print template)
- PDF generation and download
- Send-to-printer workflow (email PDF to OJ Graphix)

**Out of scope (future):**
- Rich text editing (TipTap or similar) — inline_body content arrives pre-formed
- Auto-assemble from section quotas — deferred to Issue #2 timeframe
- Locking / multi-user collaboration
- Submission moderation workflow in admin SPA
- Community-scoped edition filtering (admin sees all)

## Architecture

### Data Model (existing — no changes)

Three entity types already exist with migrations:

- **newsletter_edition** (`neid`): community_id, volume, issue_number, publish_date, status (draft → curating → approved → generated → sent), pdf_path, pdf_hash, headline, sent_at, created_by, approved_by
- **newsletter_item** (`nitid`): edition_id, position, section, source_type (post/event/teaching/dictionary_entry/newsletter_submission/inline), source_id, inline_title, inline_body, editor_blurb, included
- **newsletter_submission** (`nsuid`): community_id, submitted_by, category, title, body, status, included_in_edition_id

### Backend API

**Unified admin API controller** (`NewsletterAdminApiController`) — a single controller handling all newsletter builder operations via JSON. Simpler than mixing admin-surface generic CRUD with custom endpoints.

| Method | Path | Purpose |
|--------|------|---------|
| GET | `/admin/api/newsletter` | List all editions |
| POST | `/admin/api/newsletter` | Create a new edition |
| GET | `/admin/api/newsletter/entity-search?q={query}&types={types}` | Type-ahead search across entity types for the entity picker |
| GET | `/admin/api/newsletter/{id}` | Get edition with items grouped by section |
| POST | `/admin/api/newsletter/{id}/items` | Add an item to a section |
| DELETE | `/admin/api/newsletter/{id}/items/{itemId}` | Remove an item |
| POST | `/admin/api/newsletter/{id}/items/{itemId}/reorder` | Update item position |
| GET | `/admin/api/newsletter/{id}/preview-token` | Generate a one-time render token, return the print preview URL |
| POST | `/admin/api/newsletter/{id}/generate` | Trigger Playwright PDF render, return PDF metadata |
| GET | `/admin/api/newsletter/{id}/download` | Serve the generated PDF file |
| POST | `/admin/api/newsletter/{id}/send` | Email PDF to configured printer address |

### Admin SPA Pages

**Edition list** (`/admin/newsletter`):
- Table: headline, volume/issue, community, status badge, date
- "New Edition" button → create form

**Edition detail** (`/admin/newsletter/:id`):
- Header: edition metadata (headline, volume, issue, status)
- Two-panel layout: item list (left/main), preview iframe (right/side)
- Items grouped by section in config order (cover, editors_note, news, events, teachings, language, community, language_corner, jokes, puzzles, horoscope, elder_spotlight, back_page)
- Per section: item cards showing position, title/blurb, source type badge
- Per item: remove button, move up/down arrows
- Per section: "Add Item" button → modal with two tabs:
  - **Inline**: section (pre-filled), inline_title, inline_body (textarea), editor_blurb
  - **From entity**: type-ahead search field, select entity, auto-populate editor_blurb from entity title
- Action bar: "Refresh Preview", "Generate PDF", "Download PDF" (when generated), "Send to Printer" (when generated)

### Preview Pipeline

1. Admin SPA requests a preview token via `/admin/api/newsletter/{id}/preview-token`
2. Backend generates a one-time token via `RenderTokenStore`
3. SPA loads `/_internal/{id}/print?token=TOKEN` in an iframe
4. The existing Twig print template renders the edition with current items
5. "Refresh Preview" repeats steps 1-3

### PDF Generation Pipeline (existing — reused)

1. Admin SPA calls POST `/admin/api/newsletter/{id}/generate`
2. Backend creates a render token, invokes `bin/render-pdf.js` with the print URL
3. Playwright/Chromium renders the page to PDF with `preferCSSPageSize: true`
4. PDF saved to `storage/newsletter/regional/{volume}-{issue}.pdf`
5. Edition status updated to `generated`, pdf_path and pdf_hash stored
6. Response returns PDF metadata (path, size, hash)

### Send-to-Printer Pipeline

1. Admin SPA calls POST `/admin/api/newsletter/{id}/send`
2. Backend loads the generated PDF from `pdf_path`
3. Sends email with PDF attachment to configured printer address (`config/newsletter.php` → `printer_email`)
4. Edition status updated to `sent`, `sent_at` recorded
5. Uses framework `AuthMailer` transport (SendGrid) — requires `isConfigured()` guard

## Milestones & Issues

### Milestone 1: Waaseyaa Alpha.140 Upgrade

Prerequisite gate — get Minoo current before building new features.

| Issue | Title | Acceptance |
|-------|-------|------------|
| 1 | Bump waaseyaa/* packages to alpha.140 | `composer update` succeeds, all PHPUnit + Playwright tests green |
| 2 | Add waaseyaa/bimaaji package | Provider registered, `bimaaji:graph` outputs Minoo's entity types and routes |
| 3 | Verify admin-surface serves from vendor dist | `/admin` loads the SPA shell in a browser |
| 4 | Smoke-test newsletter entities via admin-surface CRUD | Can create/list/view edition, item, submission through generic admin UI |

### Milestone 2: Newsletter Builder MVP

Assembly-to-PDF pipeline in the admin SPA.

| Issue | Title | Depends On | Acceptance |
|-------|-------|------------|------------|
| 5 | Admin SPA newsletter edition list + create | M1 complete | Can create an edition and see it in the list |
| 6 | Edition detail with section/item management | #5 | Can view items by section, add inline items, reorder, remove |
| 7 | Entity picker for sourced items | #6 | Can search for a teaching by title, add it to a section |
| 8 | HTML preview iframe | #6 | Can see print-formatted preview alongside item list, refresh after changes |
| 9 | PDF generation + download from admin | #6 | Generate button produces PDF, download button serves the file |
| 10 | Port Issue #1 through the builder | #6, #7, #9 | PDF matches the script-generated 1-1.pdf |
| 11 | Send to printer workflow | #9 | PDF arrives in printer inbox, edition status transitions to sent |

## Section Configuration

From `config/newsletter.php`, sections render in this order:

1. `cover` — masthead, TOC (inline only)
2. `editors_note` — editor's welcome (inline only)
3. `news` — community news (entity-sourced from posts)
4. `events` — upcoming events (entity-sourced)
5. `teachings` — featured teachings (entity-sourced)
6. `language` — word of the issue (entity-sourced from dictionary_entry)
7. `community` — community submissions (entity-sourced from newsletter_submission)
8. `language_corner` — language learning feature (inline only)
9. `jokes` — humor section (inline only)
10. `puzzles` — crossword or word search (inline only)
11. `horoscope` — Anishinaabe horoscope grid (inline only)
12. `elder_spotlight` — elder feature story (inline only)
13. `back_page` — closing content (inline only)

## Technical Notes

- **No new entities or migrations** — existing newsletter_edition, newsletter_item, newsletter_submission are sufficient
- **No new Twig templates** — the print template is reused as-is for both preview and PDF
- **Admin SPA framework** — Nuxt/Vue 3 via waaseyaa/admin-surface; newsletter pages are custom Vue routes added to the SPA
- **PDF renderer** — existing `bin/render-pdf.js` (Playwright/Chromium) invoked as subprocess via `NewsletterRenderer` service
- **Email transport** — framework `AuthMailer` (SendGrid); requires API key configured on production
- **Auth** — admin-only; admin-surface handles authentication; no new access policies needed

## Future Work (post-MVP)

- **Auto-assemble** — populate sections from recent entities using `config/newsletter.php` quotas (Issue #2 timeframe)
- **Rich text editor** — TipTap for inline_body editing in the SPA
- **Submission moderation** — approve/reject submissions from the admin SPA
- **Community scoping** — filter editions by coordinator's community
- **Edition locking** — prevent concurrent edits
- **Template variants** — different print layouts per community or season
