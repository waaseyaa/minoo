# Dictionary Sync Verification Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Verify `waaseyaa/minoo#330` end to end against the live North Cloud dictionary API and record evidence for each acceptance criterion without making behavior changes in the first pass.

**Architecture:** The verification runs in four stages: live API shape checks, dry-run sync verification, real sync into an isolated SQLite database, and SSR/data inspection against that isolated dataset. Results are written to a dedicated verification doc in the repo and summarized back to the GitHub issue.

**Tech Stack:** PHP 8.4, Waaseyaa console kernel, SQLite, `bin/sync-dictionary`, GitHub CLI, live North Cloud HTTP endpoints, Twig/SSR rendering

---

## File Structure

- Create: `docs/superpowers/specs/2026-03-20-dictionary-sync-verification-results.md`
  - Primary evidence log for commands, outputs, sample payloads, and acceptance-criteria status.
- Modify: `docs/superpowers/plans/2026-03-20-dictionary-sync-verification.md`
  - Check off completed steps as execution proceeds.
- Modify: issue `waaseyaa/minoo#330` via `gh issue comment`
  - Concise public summary after verification evidence is complete.
- Read only: `bin/sync-dictionary`
  - Verification target for dry-run and real sync behavior.
- Read only: `src/Support/NorthCloudClient.php`
  - Confirms expected live endpoint paths and response assumptions.
- Read only: `src/Ingestion/EntityMapper/DictionaryEntryMapper.php`
  - Confirms field mapping and consent/status rules being verified.
- Read only: `templates/language.html.twig`
  - Confirms what listing/detail SSR output should contain.

### Task 1: Establish Verification Workspace

**Files:**
- Create: `docs/superpowers/specs/2026-03-20-dictionary-sync-verification-results.md`
- Modify: `docs/superpowers/plans/2026-03-20-dictionary-sync-verification.md`

- [ ] **Step 1: Create the results document with verification sections**

Add sections for:
- environment and timestamp
- live NC endpoint checks
- dry-run sync
- isolated DB real sync
- post-sync entity inspection
- SSR verification
- acceptance-criteria checklist
- follow-up recommendations

- [ ] **Step 2: Record the exact environment inputs**

Run:
```bash
date -Iseconds
php -v | head -n 1
git rev-parse --short HEAD
```

Expected:
- current timestamp captured
- PHP version captured
- current commit captured

- [ ] **Step 3: Create an isolated temp database path**

Run:
```bash
tmp_db="$(mktemp /tmp/minoo-dictionary-sync-XXXXXX.sqlite)"
echo "$tmp_db"
```

Expected:
- one disposable SQLite file path under `/tmp`

- [ ] **Step 4: Commit the plan checkpoint**

Run:
```bash
git add docs/superpowers/plans/2026-03-20-dictionary-sync-verification.md
git commit -m "docs: add dictionary sync verification plan (#330)"
```

Expected:
- plan committed before verification execution begins

### Task 2: Verify Live North Cloud API Shape

**Files:**
- Modify: `docs/superpowers/specs/2026-03-20-dictionary-sync-verification-results.md`

- [ ] **Step 1: Probe the live entries endpoint**

Run:
```bash
curl -fsS "https://northcloud.one/api/v1/dictionary/entries?limit=3&offset=0"
```

Expected:
- JSON body with `entries`
- `total` present
- at least one entry sample available to inspect

- [ ] **Step 2: Capture a normalized sample of entry fields**

Run:
```bash
curl -fsS "https://northcloud.one/api/v1/dictionary/entries?limit=3&offset=0" \
  | jq '{total, sample: (.entries[0] | {lemma, definitions, word_class_normalized, inflections, source_url, attribution, consent_public_display})}'
```

Expected:
- one compact sample confirming or disproving mapper assumptions

- [ ] **Step 3: Probe the live search endpoint**

Run:
```bash
curl -fsS "https://northcloud.one/api/v1/dictionary/search?q=makwa" \
  | jq '{total, first: (.entries[0] | {lemma, definitions, word_class_normalized, source_url, attribution, consent_public_display})}'
```

Expected:
- search returns entries
- payload shape is consistent with `NorthCloudClient::searchDictionary()`

- [ ] **Step 4: Record any payload mismatches instead of fixing them**

If any key differs from code assumptions, write the mismatch into the results doc with the exact observed JSON fragment.

- [ ] **Step 5: Commit the live API verification checkpoint**

Run:
```bash
git add docs/superpowers/specs/2026-03-20-dictionary-sync-verification-results.md docs/superpowers/plans/2026-03-20-dictionary-sync-verification.md
git commit -m "docs: record live NC dictionary API verification (#330)"
```

Expected:
- results for live endpoint checks committed

### Task 3: Verify Dry-Run Sync Behavior

**Files:**
- Modify: `docs/superpowers/specs/2026-03-20-dictionary-sync-verification-results.md`

- [ ] **Step 1: Run the dry-run command against live NC**

Run:
```bash
php bin/sync-dictionary --dry-run
```

Expected:
- output begins with `DRY RUN`
- pages are fetched without fatal errors
- create/update preview lines appear
- final summary line is printed

- [ ] **Step 2: Capture summary evidence**

Record:
- fetched count
- created count
- updated count
- error count
- whether pagination terminated normally

- [ ] **Step 3: Verify dry-run did not write to the isolated DB**

Run:
```bash
WAASEYAA_DB="$tmp_db" php bin/waaseyaa migrate
sqlite3 "$tmp_db" "select count(*) from dictionary_entry;"
WAASEYAA_DB="$tmp_db" php bin/sync-dictionary --dry-run >/tmp/minoo-dry-run.log
sqlite3 "$tmp_db" "select count(*) from dictionary_entry;"
```

Expected:
- count is unchanged before and after the dry run

- [ ] **Step 4: Commit the dry-run verification checkpoint**

Run:
```bash
git add docs/superpowers/specs/2026-03-20-dictionary-sync-verification-results.md docs/superpowers/plans/2026-03-20-dictionary-sync-verification.md
git commit -m "docs: record dictionary sync dry-run verification (#330)"
```

Expected:
- dry-run evidence committed

### Task 4: Run Real Sync Into an Isolated Database

**Files:**
- Modify: `docs/superpowers/specs/2026-03-20-dictionary-sync-verification-results.md`

- [ ] **Step 1: Prepare the isolated schema**

Run:
```bash
WAASEYAA_DB="$tmp_db" php bin/waaseyaa migrate
```

Expected:
- migrations complete successfully on the temp database

- [ ] **Step 2: Run the real sync against the isolated DB**

Run:
```bash
WAASEYAA_DB="$tmp_db" php bin/sync-dictionary
```

Expected:
- pages fetched successfully
- final summary line printed
- no fatal errors

- [ ] **Step 3: Capture row-count evidence**

Run:
```bash
sqlite3 "$tmp_db" "select count(*) from dictionary_entry;"
```

Expected:
- a non-zero entry count

- [ ] **Step 4: Inspect representative stored fields**

Run:
```bash
sqlite3 -header -column "$tmp_db" "select word, definition, part_of_speech, status, consent_public, attribution_source, attribution_url from dictionary_entry limit 5;"
```

Expected:
- populated dictionary fields
- attribution fields present
- status and consent fields visible for inspection

- [ ] **Step 5: Inspect consent governance distribution**

Run:
```bash
sqlite3 -header -column "$tmp_db" "select consent_public, status, count(*) as count from dictionary_entry group by consent_public, status order by consent_public, status;"
```

Expected:
- entries with `consent_public = 1` are published
- if any `consent_public = 0` exist, they are unpublished

- [ ] **Step 6: Commit the real-sync verification checkpoint**

Run:
```bash
git add docs/superpowers/specs/2026-03-20-dictionary-sync-verification-results.md docs/superpowers/plans/2026-03-20-dictionary-sync-verification.md
git commit -m "docs: record isolated dictionary sync results (#330)"
```

Expected:
- real-sync evidence committed

### Task 5: Verify SSR Output Against Synced Data

**Files:**
- Modify: `docs/superpowers/specs/2026-03-20-dictionary-sync-verification-results.md`
- Read only: `templates/language.html.twig`

- [ ] **Step 1: Choose one synced slug for inspection**

Run:
```bash
slug="$(sqlite3 "$tmp_db" "select slug from dictionary_entry where status = 1 limit 1;")"
echo "$slug"
```

Expected:
- one published dictionary entry slug

- [ ] **Step 2: Start a local PHP server against the isolated DB**

Run:
```bash
WAASEYAA_DB="$tmp_db" php -S 127.0.0.1:8081 -t public >/tmp/minoo-dictionary-verify-server.log 2>&1 &
server_pid=$!
echo "$server_pid"
sleep 2
```

Expected:
- local server PID available
- server running without immediate exit

- [ ] **Step 3: Verify `/language` listing output**

Run:
```bash
curl -fsS "http://127.0.0.1:8081/language" > /tmp/minoo-language-listing.html
```

Expected:
- HTML includes dictionary entry cards
- HTML includes attribution text

- [ ] **Step 4: Verify detail page output**

Run:
```bash
curl -fsS "http://127.0.0.1:8081/language/$slug" > /tmp/minoo-language-detail.html
```

Expected:
- HTML includes word
- definition present
- part of speech present
- inflected forms present when available
- attribution present

- [ ] **Step 5: Record SSR evidence and stop the server**

Run:
```bash
kill "$server_pid"
wait "$server_pid" 2>/dev/null || true
```

Expected:
- local verification server stopped cleanly

- [ ] **Step 6: Commit the SSR verification checkpoint**

Run:
```bash
git add docs/superpowers/specs/2026-03-20-dictionary-sync-verification-results.md docs/superpowers/plans/2026-03-20-dictionary-sync-verification.md
git commit -m "docs: record dictionary SSR verification (#330)"
```

Expected:
- SSR verification evidence committed

### Task 6: Publish Findings

**Files:**
- Modify: `docs/superpowers/specs/2026-03-20-dictionary-sync-verification-results.md`
- Modify: `waaseyaa/minoo#330` via GitHub comment

- [ ] **Step 1: Mark each acceptance criterion as pass, fail, or partial**

Record one status per checkbox in `#330` with a short evidence note and command reference.

- [ ] **Step 2: Write the issue summary comment**

Run:
```bash
gh issue comment 330 --repo waaseyaa/minoo --body "<concise verification summary with pass/fail/partial and link to results doc>"
```

Expected:
- issue comment posted with the current verification state

- [ ] **Step 3: Run a final verification of documentation-only changes**

Run:
```bash
git status --short
```

Expected:
- only intended doc/plan changes remain, or worktree is clean after commit

- [ ] **Step 4: Commit the final verification report**

Run:
```bash
git add docs/superpowers/specs/2026-03-20-dictionary-sync-verification-results.md docs/superpowers/plans/2026-03-20-dictionary-sync-verification.md
git commit -m "docs: publish dictionary sync verification results (#330)"
```

Expected:
- final verification report committed
