# Polish & Acquisition Sprint — 2026-04-06

**Goal:** Turn "it works" into "it's a serious product" while simultaneously building an audience in public. Two parallel tracks, single backlog, live-streamable tempo.

**Handoff context:** Written at the end of an incident-recovery session that shipped framework alpha.75 → alpha.107 and restored minoo.live after a ~30 min outage. Repos are clean. Production is live. Russell is about to switch computers and start live-streaming development on Twitch.

---

## Current state (snapshot as of 2026-04-06)

| Thing | State |
|---|---|
| minoo.live | Live on alpha.107, release 282, verified via body fetch |
| waaseyaa framework | v0.1.0-alpha.107 tagged and split to per-package repos |
| minoo main branch | Clean, CI green, 41fbaf0 is the incident-recovery commit; f371baf added docs gotchas |
| Framework main | Clean, drift-detector happy, 1c9d508b is the docs commit |
| Open incident follow-ups | #633 FeedController cookie idiom, #634 VolunteerController scalar skills bug |
| Untouched incident artifacts | `.bak-wsod` backup on production at `releases/281/public/index.php.bak-wsod` — safe to ignore or delete |
| V1 Release (#19) | CLOSED — memory was stale, V1 already shipped |
| Messaging milestone (#42) | 93% done (1 open, 14 closed) |
| Untracked junk in working tree | `img*`, `php*`, `phpstan/`, `waaseyaa-sync-*` directories — not mine, leaving alone |

---

## Track A — Functional audit + polish + data cleanup

### Phase 1: Audit (dispatched in parallel — see Deliverables)

Agent `a1a4b6144659fc75b` is walking every public route on minoo.live right now and producing `/home/jones/dev/minoo/docs/audits/2026-04-06-functional-audit.md`. Expected output: per-route findings + a "Proposed GitHub issues" section grouped by severity (CRITICAL / POLISH / DATA / PERFORMANCE).

**When the audit lands:**
1. Read the audit file.
2. Batch-create issues via `gh issue create --title ... --body ...` for each proposed item. Put CRITICAL bugs in a new **"Polish Sprint"** milestone (create it if needed). Put POLISH items in the same milestone. Put DATA items under the existing Content Enrichment Pipeline (#39) or create a Data Quality milestone.
3. Work top-down by severity.

### Phase 2: Quick wins (do these first, they stream well)

Even before the audit lands, there are known wins from this session's gotchas and the user's earlier triage:

- **File #636: Close the messaging milestone.** `Messaging (#42)` is 93% done. Check what the final issue is and either ship it or move it to a later milestone so #42 can close. Closing a milestone is a visible "progress" signal for the stream and for blog content.
- **File #637: Dedupe issues flagged in triage.** #605 / #341 (dictionary audio), #519 / #512 ("did you mean" search). Close or merge the duplicates.
- **File #638: Update MEMORY.md about V1 Release (#19) being closed.** The previous memory said target 2026-04-22 — the memory was stale. Not a code change, a personal-memory fix.
- **Fix #633 (FeedController cookie).** Small refactor, good first stream demo ("watch me land a clean fix live").
- **Fix #634 (VolunteerController scalar skills).** A real bug caught during code review of #632. Also a good live demo.

### Phase 3: Data cleanup candidates (hypothesis, verify on audit)

- **Community registry (637 First Nations from CIRNAC)** — how many have real names, leadership, coordinates? The audit may reveal placeholder rows.
- **NC content enrichment** — `NC content enrichment pipeline for businesses and people (#379)` is stuck. Worth a separate thread.
- **Dictionary entries with JSON-wrapped `definition` field** — already a known gotcha (`cleanDefinition()` pattern). Audit whether any pages leak raw `["bear"]` values.
- **Feed trending/upcoming** — does the feed show real content or stale placeholders from seeding?

### Phase 4: Performance

- Curl `%{time_total}` on hot pages. Anything >2s gets an issue.
- Check for N+1 DB queries on feed, community detail, search.
- NC timeout settings already tuned (search uses 15s, noted in memory).

### Phase 5: Polish

- Empty-state copy for every listing page.
- Loading states for anything that hits NC.
- 404 / 500 error pages that are real (not blank 200s).
- Mobile audit: use Playwright MCP to screenshot each page at 375px.
- Light mode parity check: every page works in both themes.

---

## Track B — Build in public + user acquisition

### Phase 1: Incident ship-note (dispatched in parallel — see Deliverables)

Agent `a8a1e8b72c02983f0` is drafting three pieces right now:

- **Blog post** → `content/drafts/2026-04-06-three-stacked-bugs-and-alpha-107.md`
- **Substack issue** → `content/drafts/substack-2026-04-06-incident-to-ship.md`
- **Social posts (6 total)** → `content/drafts/social-2026-04-06-incident.md`

Voice: Russell's first-person, technical, no em dashes, strong CTAs.

**When drafts land:**
1. Read each, edit for voice, tighten, add any missing hashtags.
2. Publish the blog post to the Hugo blog (not sure which repo yet, but probably `jonesrussell42/jonesrussell42.github.io` or similar — check).
3. Post the Substack issue.
4. Schedule the 6 social posts via Buffer or just post them live during the stream.

### Phase 2: Content cadence while live-streaming

Target: one shippable piece of content per stream.

- **Ship-note per stream** — short post at the end of each stream recapping what landed. Link to the commit(s). "Here's what we did on stream today."
- **Weekly long-form** — one Substack issue per week that ties multiple ship-notes together into a narrative.
- **Issue-to-content pipeline** — every closed issue is a micro-story worth tweeting. Open issues are teases.

### Phase 3: CTAs (use these everywhere)

Primary CTAs, ordered by priority:
1. **"Try it: https://minoo.live"** — product visit
2. **"Follow the build: https://www.twitch.tv/[TWITCH_HANDLE_TBD]"** — Twitch stream (TODO: fill in handle)
3. **"Star the repo: https://github.com/waaseyaa/minoo"** — GitHub social proof
4. **"Subscribe to Ahnii!: https://jonesrussell42.substack.com"** — email list
5. **"Follow on X/Bluesky/LinkedIn"** — multi-platform reach

Every blog post, Substack issue, and social thread should end with at least 3 of these.

### Phase 4: Twitch stream setup notes (for when you get to the new machine)

- **Desktop layout:** terminal window + editor + browser + webcam. Don't hide the terminal; typing IS the content.
- **Stream deck / overlay:** pin the current issue title + milestone name top-right so viewers always know what's happening.
- **Pacing:** narrate every decision, every rollback, every "wait, that's weird." Lulls happen; fill them by reading the audit file aloud.
- **Safety guardrails for live coding:**
  - Never run `gh pr merge` without reading the diff aloud first.
  - Never `ssh deployer@minoo.live` on stream without explaining what command you're about to run.
  - Keep real secrets out of the terminal scroll. Check `.env` isn't open in the editor.
  - Memory files in `/home/jones/.claude/projects/-home-jones-dev-minoo/memory/` contain personal context — don't cat them on stream.
  - Close any private Substack drafts and email clients.
- **Opening segment:** 2 minutes. "Hi I'm Russell, I'm Anishinaabe from Sagamok, I'm building Minoo, here's the site, here's what we're doing today." Read from today's plan file.
- **Closing segment:** 2 minutes. "Here's what we shipped, here's the commit hash, CTAs, see you next stream."

---

## Deliverables from this planning session (will be ready when you open the next machine)

| File | Producer | Status at time of handoff |
|---|---|---|
| `docs/plans/2026-04-06-polish-acquisition-sprint.md` | Main agent (this file) | Written, ready to commit |
| `docs/audits/2026-04-06-functional-audit.md` | Background agent A | Running, expected in ~10 min |
| `content/drafts/2026-04-06-three-stacked-bugs-and-alpha-107.md` | Background agent B | Running, expected in ~5 min |
| `content/drafts/substack-2026-04-06-incident-to-ship.md` | Background agent B | Running, expected in ~5 min |
| `content/drafts/social-2026-04-06-incident.md` | Background agent B | Running, expected in ~5 min |

All of these will be committed and pushed to `main` before the handoff closes, so `git pull` on the next machine gives you everything.

---

## Resume script (for the next machine)

When you open Claude Code on the new machine:

```
cd ~/dev/minoo
git pull --ff-only
cat docs/plans/2026-04-06-polish-acquisition-sprint.md
cat docs/audits/2026-04-06-functional-audit.md
ls content/drafts/
```

Then say to Claude Code something like:

> "Resume the polish sprint. Read docs/plans/2026-04-06-polish-acquisition-sprint.md. I'm about to start streaming. Start with the audit file and create the Polish Sprint milestone, then batch-file the CRITICAL and POLISH issues from the audit. Keep it visible so I can narrate."

Or, if you want to open with content:

> "Read content/drafts/2026-04-06-three-stacked-bugs-and-alpha-107.md and the substack draft. Tighten them in Russell's voice. Then publish checklist me through posting them."

---

## Open decisions (answer on stream or before)

1. **Twitch handle?** Fill in the TODO above once you've created the channel.
2. **Hugo blog repo path?** Confirm where drafts publish to so the agent can move them.
3. **Create a "Polish Sprint" milestone?** I'd say yes, and target it to close in a week. Gives the stream a visible finish line.
4. **Delete the `.bak-wsod` file on production?** Left it as a safety net. Can be cleaned up on the next successful deploy.
5. **Queue and ai-vector split failures** — framework split workflow failed on 2/30 packages at the tooling install step. Non-blocking right now (minoo uses the older tags fine). File a framework issue if it persists.

---

## Session notes worth preserving

- The `claudia-memory` daemon was silent at this session's start (memory tools unavailable) but MEMORY.md was loaded via the auto-memory context. If that stays broken on the new machine, tell Claude Code "my memory daemon isn't running, work from context files only" and they'll handle it.
- The `context-mode` MCP plugin disconnected mid-session. No action needed; it was only being used to keep large tool outputs out of context.
- Three new claudia memory files were written this session:
  - `feedback_production_verification.md`
  - `feedback_incident_layering.md`
  - (plus the MEMORY.md index update)
