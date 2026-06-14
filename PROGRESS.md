# Minoo resurrection — progress and decision log

Mode: autonomous Phases 1-5, no per-phase review. Audit log for Russell.
Target: language-platform-only Minoo on latest Waaseyaa alpha, live at minoo.live on the Pi beside oiatc.ca.

## Ground rules in force
- E:\backups read-only; all work from copies.
- Phase 4 corpus: consent flags OFF, admin-only, no search, no AI grounding. Unsure = gated.
- Counts must verify (dictionary_entry 21721, user 4, game_session 118) before deploy.
- No table drops. No AI-generated Anishinaabemowin. No orthography corrections. Corpus content never committed.
- Stop-and-ask: force-pushes, non-minoo.live DNS, deletions outside the clone. minoo.live cutover pre-approved.

## Phase 0 — repo (done)
- Canonical repo: github.com/waaseyaa/minoo, cloned to Local Sites\minoo.
- HEAD: e83b6c7 (2026-05-21) "fix(deploy): pin waaseyaa framework checkout to composer.lock version".
- Note: gh repo clone hung for an hour with no output; killed, plain git clone succeeded in seconds.

## Phase 1 — lineage reconciliation (done)
Full report: docs/lineage-report.md. Highlights:
- Latest framework release: v0.1.0-alpha.208 (2026-06-12). Target for Phase 2.
- Deployed snapshot src/ is byte-identical to commit 8107dba (alpha.187 bump). No prod-only drift.
- Working tree (Projects\Minoo) is an alpha.157-era fork; ALL its language-domain work (5 entities + Ingestion subsystem) was later merged to main, identical modulo namespaces. Contributes nothing.
- Decision: build on main HEAD (e83b6c7). Snapshot and working tree are reference-only from here.
- Phase 4 alert: provider defines speaker.consent_public_display DEFAULT 1 — corpus import must force every consent flag OFF explicitly.

## Phase 2 - slim app on alpha.208 (done)
- All 51 waaseyaa/* packages bumped 188 -> 208; framework changelog reviewed (key risks: alpha.204 save-time validation, alpha.205 auto-added revision_author/actor_uid columns at boot, alpha.206 project-root DB path + 404-for-denied reads).
- Cut surfaces de-registered then deleted: communities, businesses, events, feed, newsletters, people, oral histories, teachings, volunteer/coordinator dashboards, messaging, engagement, crisis, guess-price, chat, NorthCloud (sync + search + community client), geo/geocoding, OG images. 67 src files, ~29 template groups, 60+ test files, 37 bin/scripts utilities removed. Cut tables remain dormant per rule 4.
- Kept: language domain (5 entities + contributor for attribution), games x5, auth/accounts, admin SPA + staff ingestion + role management, local search, i18n, consent/access.
- Packages removed: geoip2, engagement, genealogy, geo, groups, mercure, messaging, northcloud, queue (northcloud + queue remain only as framework transitive deps; no app wiring, no workers).
- Gates: phpunit OK (345 tests, 914 assertions), phpstan clean (baseline regenerated: 5 carried findings), cs-fixer applied, bin/smoke-test rewritten and passing (27/28; the 1 expected failure is dictionary count on an empty in-memory DB).

## Phase 3 - data restore (done)
- Backup copied (read-only source untouched) to storage/waaseyaa.sqlite (19.7 MB, prod state 2026-05-20).
- COUNTS VERIFIED post-restore: dictionary_entry 21721 OK, user 4 OK, game_session 118 OK. (Smoke testing then created 14 practice sessions; pruned back to exactly 118 via scripts/prune-smoke-sessions.php.)
- New tables created: speaker, word_part, contributor, dialect_region (contributor ALSO never existed in prod - example_sentence is 0 rows). Migration file migrations/20260612_160000_create_language_domain_tables.php for fresh installs; applied to the restored DB via scripts/restore-language-tables.php.
- Migration bookkeeping repaired: prod recorded only 12 of ~20 applied migrations, and alpha.208 attributes the app migrations dir to a DIFFERENT waaseyaa package on every boot (discovery quirk; upstream candidate). Backfilled waaseyaa_migrations under app + all 51 waaseyaa/* namespaces so `bin/waaseyaa migrate` is a deterministic no-op. NEVER run migrate against this DB without checking migrate:status first.
- alpha.208 boot auto-created audit_event, audit_retention_policy, agent_run tables and additive columns in the restored DB - expected alpha.205 behavior, additive only.
- Games smoke-tested against restored data: bin/smoke-test 37/37 (pages + data APIs for shkoda, matcher, agim, crossword, journey; agim/prompt correctly 422 without session token). Dormant tables untouched.

## Phase 4 - consent-gated corpus import (done)
- Source: Projects\LLC\anishinaabemowin\content\corpus (27 items, 1 contributor: Steven Bennett; Facebook reel clips, May 2026). NOTHING from the directory committed to the repo; audio/video/thumbs stay in the community-controlled directory; only provenance paths recorded in the DB.
- Mapping: each item -> example_sentence (ojibwe_text verbatim, english_text, source_url = reel URL, source_date, source_sentence_id = corpus:<id> for idempotent dedup, provenance = full original item JSON). One speaker row (code sb) linked via speaker_id. Orthography untouched.
- HARD GATES, all verified by scripts/verify-corpus-gates.php (9/9 PASS):
  * every corpus row + speaker: consent_public/consent_public_display=0, consent_ai_training=0, status=0
  * anonymous view DENIED at the access-handler level (LanguageAccessPolicy strengthened: consent flag 0 now blocks public view even if status were flipped to 1)
  * absent from FTS search index, no embeddings, never entered dictionary_entry
  * audio_url left empty - no media served
- example_sentence gained consent_public, consent_ai_training, source_url, source_date, provenance field definitions.
- Import idempotent (re-run: 27 skipped). Suite still green (345 tests).

## Phase 5 - deploy (done on the Pi; public cutover blocked on Cloudflare access)
- waaseyaa/minoo main pushed (merge 9719d1f); infra in jonesrussell/waaseyaa-infra: compose/minoo Dockerfile (multi-stage, MINOO_REF pin, ext-gmp for the sendgrid dep), minoo-app service with own minoo_storage/minoo_files/minoo_public volumes, Caddy php_fastcgi block for minoo.live + www, deploy-minoo.yml CI (Tailscale -> Pi), runbook 06.
- Database: restored+corpus sqlite seeded into the minoo_storage volume (chown 82), THEN CI db:init (idempotent no-op thanks to the Phase 3 backfill). Static placeholder container removed; caddy force-recreated (twice: new site block, then cache-header fix).
- VERIFIED on the Pi through the full cloudflared-side path (Host: minoo.live -> caddy -> php-fpm): / 200, /language 200 (21,721-entry list), /language/search?q=makwa 200, /games 200, shkoda daily API returns a real challenge from restored data; corpus example_sentence and speaker return 404 to anonymous API requests; HTML Cache-Control: no-cache (enforced at Caddy because the app's WAASEYAA_SSR_CACHE_MAX_AGE=0 override has a falsy-string bug - upstream candidate), static assets immutable max-age=1y. Sibling sites (oiatc.ca, fnprocure.ca) verified unaffected.
- Secrets: compose/minoo/secrets/minoo.env on the Pi only (oiatc:oiatc 0600); SENDGRID key + mail identity carried over from the razor-crest backup .env; fresh JWT secret. Nothing secret committed.
- BLOCKED (needs Russell, ~5 minutes): minoo.live's nameservers still point at Spaceship and the zone is NOT in the Cloudflare account; no CF API token exists on the Pi or in the infra repo, so I cannot do this. Steps (also in waaseyaa-infra/runbooks/06-minoo-deploy.md): 1) Cloudflare -> Add site -> minoo.live; 2) at Spaceship, set the two Cloudflare nameservers; 3) Zero Trust -> Tunnels -> oiatc-pi -> Public Hostnames: add minoo.live AND www.minoo.live -> HTTP -> caddy:80. No Cache-Everything rule. The site goes live the moment step 3 saves.

## Phase 2 increment - alpha.209 (2026-06-14)
- Latest published alpha is now v0.1.0-alpha.209 (released 2026-06-14): framework repo hygiene sweep + a new `user:assign-role` CLI command. No keep-list impact. Bumped all waaseyaa/* constraints 208 -> 209; `composer update` clean; suite green. (Plan Phase 2 calls for the LATEST alpha.)
- Also fixed a pre-existing flaky test: `DateTwigExtensionTest::formatsSameYearWithoutYear` used real "now" + a 06-15 date, so it failed on 06-14 (rendered "Tomorrow"). Pinned to a deterministic clock.

## Phase 4B - Lesson 1 (Kitchen) course surface (2026-06-14)
First real surface over the migrated corpus. Routes: `/lesson` (landing), `/lesson/1` (16 cards), `/lesson/media/{thumb,audio}/{id}` (media stream).
- 16 kitchen items in the exact order + section groups from `research/elder-and-teacher-input/steven-lesson-1-review.md` (Dishes and containers / Utensils / Cookware / Furniture and appliances), matching the reference impl `code/lesson/lesson.py`.
- Card = whiteboard thumbnail + Listen (audio) + Anishinaabemowin + English. Practice mode blurs English until tap; mark-known persists in localStorage. Steven Bennett credited in header + footer via https://www.facebook.com/profile.php?id=61582894730998; each card also links its source reel.
- DATA SOURCING (house rules): Anishinaabemowin read VERBATIM from the migrated corpus (`example_sentence.ojibwe_text`) at runtime, never committed, never altered. `config/lesson1.php` holds only curation metadata (IDs, order, group labels, reviewed English glosses from the review doc, e.g. "stove" not the raw corpus artifact "stoue", "plate" not "Plate"). Audio + thumbnails streamed from the corpus dir via `MINOO_CORPUS_PATH`, never copied into the repo.
- CONSENT GATE (absolute): lesson + media routes are admin-gated (`requireAuthentication()` + in-controller `hasPermission('administer content')`). Verified anon gets 302->/login on `/lesson/1` and 401 on media; corpus audio/whiteboards/Anishinaabemowin are NOT publicly reachable. `/games` and `/language` stay public. Corpus rows remain consent-OFF/unpublished/out-of-search/no-embeddings (Phase 4 gate verifier re-run 9/9). Going public is a deliberate later step (relax guard + publish rows) gated on WRITTEN consent.
- Media allowlist: only the 16 lesson IDs and {thumb,audio}; traversal and non-lesson corpus IDs (e.g. sb-002) return 404. 5 security unit tests cover the guard + allowlist.
- LOCAL REVIEW: `.env` (gitignored) enables the framework dev-fallback admin so `/lesson/1` is reviewable at http://localhost:8080/lesson/1 without a login. OFF in production (Pi minoo.env sets it false).
- Adversarial review (4-lens workflow: house-rules, consent/security, reference-parity, code-correctness): 0 blockers, 0 majors, 4 minors. Fixed: em dash in a config comment; added the `accessCheck(false)` row to docs/security/sql-entity-query-access-check-bypass-audit.md. Left by design: `audio/ogg` MIME for .opus (plays, faithful to reference); one pre-existing non-user-facing Twig doc-comment em dash (codebase-wide convention, not mine).
- Gates: phpunit 350 green, phpstan clean, cs-fixer applied. Counts re-verified 21721/4/118 (pruned 1 game_session created by smoke curls).
- NOT a public surface and NOT deployed: per instruction, stopped before Phase 5. Pi deploy will need the corpus dir provided out-of-band (the /srv/lessons rsync pattern) since corpus content is never committed.

## Decisions
- (running list, newest last)
- D1: gh repo clone hung; switched to plain git clone. (Phase 0)
- D2: Use main HEAD as the only source tree; no porting from snapshot or working tree needed (evidence in docs/lineage-report.md). (Phase 1)
- D3: Worktree minoo-at-187 created beside the clone for fingerprinting; removed after Phase 1. (Phase 1)
- D4: Chat controller CUT, not re-grounded. Priority is a live site; a dictionary-grounded chat can come back as production refinement. chat_enabled hardcoded false for templates. (Phase 2)
- D5: contributor entity KEPT despite "people" cut: example_sentence rows reference it for attribution and it is the dedup target of the corpus speaker mapper. Its community_id field def dropped. (Phase 2)
- D6: Search: NorthCloud override removed; SearchProviderInterface falls through to framework FTS5 (empty index for now). /language/search rewritten as local entity query (LIKE on word + definition, consent_public + status gated). /search page kept, renders empty results until FTS5 indexing is wired. (Phase 2)
- D7: speaker gains dialect_region_id + community fields; example_sentence gains speaker_id - additive _data fields needed for Phase 4 provenance. (Phase 2)
- D8: AdminRouteProvider's access-policy replay now uses KernelPolicyDependencyResolver(kernelServices) - alpha.189+ ClassificationFieldAccessPolicy has service deps and the old null resolver throws at every route registration. (Phase 2)
- D9: static pages how-it-works, safety, elders, get-involved, messages, volunteer cut (elder-support program content). about, data-sovereignty, legal, journey, matcher, studio, games, search kept. (Phase 2)
- D10: homepage rewritten (dictionary stats + featured + games); authenticated users no longer redirected to /feed. (Phase 2)
- D11: phpstan baseline regenerated after deletions (old baseline referenced removed files); 5 pre-existing findings carried. (Phase 2)

## Minoo Resurrection milestone (excellence pass, 2026-06-14)

GitHub milestone "Minoo Resurrection" (#60) opened; 32 issues seeded (#777-#808) across six workstreams (W1 infra, W2 dictionary, W3 games, W4 community map, W5 brand, W6 growth). Run-to-done mode.

SHIPPED + CLOSED this session (all on `main`, suite 360 green, phpstan clean):
- #777 remove the analytics script + leave a clean seam (no render/game-init gating)
- #784 dialect label on every dictionary entry (truthful: Southwestern Ojibwe / OPD; never silently mixed; seam for future Nishnaabemwin codes)
- #785 `clean_definition` Twig filter: no raw JSON or empty arrays render in any view (list/detail/search/card); OPD abbreviations expanded; dictionary + games show identical text
- #790 one canonical name per game; all five (Shkoda/Matcher/Agim/Crossword/Journey) on /games, homepage, and nav; Matcher page renamed from "Word Match"
- #796 hide unbuilt game teasers (Listening Quiz, Sentence Builder)
- #802 English terminology reconciled (Turtle Island -> "north shore of Lake Huron") with judgement; one touched em dash fixed; "Turtle Island News" proper noun untouched
- #779 legacy origin 147.182.150.145 confirmed decommissioned (no repo refs; apex DNS now Cloudflare)
- #782 DNSSEC verified clean via DoH: NS at Cloudflare, no DS at the .live registry, Status 0, no orphaned DS (valid unsigned-clean end state)

PARTIAL: #788 single source credit shipped (retired repeated per-card attribution); examples/audio/related remain. #804 homepage skips empty/placeholder featured cards; real featured pipeline + full em-dash sweep remain. #781 app-side verified (AuthMailer no-ops when unconfigured, so email-less auth is correct); remaining is a deploy action (drop SENDGRID_API_KEY on the Pi).

DEPLOY GATE (human): none of this is on minoo.live yet. Pushing this repo's `main` does NOT deploy; promotion requires bumping `MINOO_REF` to the new HEAD in waaseyaa-infra (the deliberate human action). Lesson 1 (alpha.209 commit 5a70376) + this session's commits ship together on that bump. Re-verify on minoo.live by body-size + title after promotion.

BLOCKERS (external/human, see issue comments): #778 + #780 (Cloudflare DNS record deletes), #781-deploy (Pi env), #789 (Nishnaabemwin community partnership), #800-listings (community consent), #803 (Ojibwe Mother Earth word from speakers).

REMAINING (tracked, substantial): #783 staging path; #791-#795 games depth (crossword medium/hard generation, stats, learnable-word selection, illustrated cards to homepage, mode/toggle verification); #797-#799/#801 community living-map (re-surface the cut community domain + Leaflet + ISC/NC governance); #805 full light/dark + EN/OJ parity audit; #806-#808 onboarding/word-lists, OG/SEO/sitemap/structured-data, accessibility + performance.

## Dictionary batch #786/#787/#788 (2026-06-14, continue-to-done run)

SHIPPED:
- #786 browse ordering: `/language` leads with defined headwords; entries whose definition is the empty JSON `"[]"` are de-ranked to the end (split cheaply at the query layer on the presence of a `"` char). Verified live: first page opens on real words.
- #787 search relevance + did-you-mean: results rank exact word (0) > prefix (1) > substring (2) > definition-only (3), tie-broken alphabetically. `q=makwa` returns `makwa` first. Did-you-mean (Levenshtein over a same-prefix candidate set) shows on zero-result queries; prefers the sharpest prefix (3->2->1 chars) so the true neighbour stays inside the bounded set (`makwwa` -> `makwa`, distance 1). Early-position typos degrade to no-suggestion rather than a bad one (distance<=3 guard).
- #788 entry detail: Examples + Related-words sections render consent-respecting, dialect-safe, ONLY when data exists. The OPD import carries no linked examples/audio/stems, so both are empty until the Nishnaabemwin source work (#789); the capability is built and styled (`.detail__section`/`.detail__example`).

CONSENT LEAK FOUND AND FIXED (the reason this batch took a full debugging pass):
- The entry-detail examples query correctly returned `[]` (consent filter works), but `EntityStorage::loadMultiple([])` treats an empty array as the framework's "load all" sentinel (`if (!empty($ids))` only adds the `IN` filter when non-empty), so it dumped ALL 27 consent-gated corpus sentences onto the public `makwa` page. Caught on live verification (kitchen corpus showing under "bear"), root-caused via kernel probes (query=0, `loadMultiple([])`=27).
- FIX (app, immediate): guard `if ($ids === []) return [];` before every `loadMultiple($ids)` in `examplesFor`, `relatedByStem`, and the search ranking loop. `list()`/`didYouMean()` already guarded. Crossword/Shkoda use `load(reset($ids))` behind `!== []` and `status=1` (all corpus is status=0) - not leaking. Lesson is intentional admin-gated. Re-verified: 0 examples on `/language/makwa`; `verify-corpus-gates.php` ALL GATES HOLD.
- Regression-locked: `LanguageControllerTest` asserts the example storage's `loadMultiple` is `never()` called when the id set is empty.
- Documented as a CLAUDE.md gotcha (loadMultiple([]) = load-all footgun). UPSTREAM follow-up: harden the framework so `loadMultiple([])` fails closed (Drupal-style `?array $ids = null`, null=all, []=none) - tracked for a framework release; the app guard is correct usage regardless.

Gates: phpunit 360 green (938 assertions), phpstan clean, CSS bumped `?v=85`. Pre-existing `dialect_region` schema drift (missing `name` column) is unrelated to this batch - flagged for the #783 infra pass.
