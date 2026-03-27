# Games Milestone Fixes

**Date:** 2026-03-27
**Issues:** #532, #558, #559, #560
**Milestone:** Games (#47)

## Context

The Games hub launched with Shkoda (hangman) and Crossword. Four issues remain from launch:
- Shkoda campfire visual bug on win
- Crossword practice mode crashes for missing tiers
- Crossword themes tab has no empty state
- Only easy-tier crossword puzzles exist

All four are independent and can be implemented in parallel.

---

## #532 — Campfire should roar on win

**Problem:** When a player wins Shkoda with few guesses remaining, the campfire shows a dying flame (e.g. state `1`) instead of a full roar. `updateFire()` sets numeric state based on remaining guesses, then `endGame(true)` sets `'win'` — but the visual stays on the dying state.

**Root cause:** `updateFire()` is called on the winning guess before `endGame()` runs. The numeric state overwrites/races the `'win'` state.

**Fix:**
1. Add early return in `updateFire()`: `if (state.gameOver) return;`
2. Verify `spawnSparks()` is called on win and `spawnSmoke()` on loss
3. Verify CSS `[data-fire-state="win"]` rules have correct specificity

**Files:**
- `public/js/shkoda.js` — `updateFire()`, `endGame()`

**Test:** Win a Shkoda game with 1 guess remaining — fire should show full roar + spark animation.

---

## #558 — Practice mode graceful error for missing tiers

**Problem:** `/api/games/crossword/random?tier=medium` returns 503 when no puzzles exist. JS shows generic error instead of helpful message.

**Fix:**
1. In `startGame()` (crossword.js), detect `data.error` or 503 response
2. Show "No puzzles available for this difficulty yet" in the puzzle area
3. Add a "Try Easy" button that calls `startGame('easy')`
4. Server-side: ensure `random()` returns 404 (not 503) with `{ error: 'no_puzzles', tier: 'medium' }` — 404 is more semantically correct

**Files:**
- `public/js/crossword.js` — `startGame()` error handling
- `src/Controller/CrosswordController.php` — `random()` response code

**Test:** Request medium/hard practice without generated puzzles — should show friendly message + easy fallback.

---

## #559 — Themes tab empty state

**Problem:** `renderThemes()` clears the container when `themes.length === 0`, leaving a blank area.

**Fix:**
1. In `renderThemes()`, when array is empty, inject placeholder HTML:
   ```html
   <div class="crossword__themes-empty">
     <p class="crossword__themes-empty-title">Themed Puzzle Packs Coming Soon</p>
     <p class="crossword__themes-empty-body">
       We're working on themed collections like Animals, Family, and Seasons.
     </p>
   </div>
   ```
2. Add CSS in `@layer components` for `.crossword__themes-empty` — centered, muted text, padding

**Files:**
- `public/js/crossword.js` — `renderThemes()`
- `public/css/minoo.css` — `.crossword__themes-empty` styles

**Test:** Open crossword page, click Themes tab — should show coming soon message.

---

## #560 — Generate medium and hard tier puzzles

**Problem:** Only easy-tier puzzles exist in the database. The generation script (`scripts/populate_crossword_puzzles.php`) has medium/hard entries defined but was only run for easy.

**Fix:**
1. Review `scripts/populate_crossword_puzzles.php` — verify medium/hard generation works
2. Make the script idempotent (skip puzzles that already exist by slug)
3. Verify `CrosswordEngine::generateGrid()` works for all tier word lists
4. Run locally to confirm puzzles generate without error
5. Document the CLI command for production deployment

**Files:**
- `scripts/populate_crossword_puzzles.php` — generation script
- `src/Support/CrosswordEngine.php` — grid generation

**Test:** Run script locally, then hit `/api/games/crossword/random?tier=medium` and `?tier=hard` — should return valid puzzles.

---

## Implementation Strategy

Four parallel worktree agents:
1. **Agent 1 (#532):** Shkoda fire state fix — JS only
2. **Agent 2 (#558):** Crossword practice error handling — JS + PHP controller
3. **Agent 3 (#559):** Crossword themes empty state — JS + CSS
4. **Agent 4 (#560):** Puzzle generation script — PHP script review + idempotency

Each agent creates a feature branch (`fix/532-campfire-win`, `fix/558-practice-error`, `fix/559-themes-empty`, `feat/560-puzzle-generation`), writes tests where applicable, and commits.

## Verification

After merging all 4:
1. `./vendor/bin/phpunit` — all tests pass
2. Playwright: open `/games`, play Shkoda to win — campfire roars
3. Playwright: open `/games/crossword`, click Practice > Medium — shows friendly error or puzzle
4. Playwright: open `/games/crossword`, click Themes — shows coming soon message
5. Run puzzle generation script — medium/hard puzzles created
