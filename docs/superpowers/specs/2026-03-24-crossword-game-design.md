# Crossword Game Design

**Status:** Draft
**Date:** 2026-03-24
**Origin:** Elder-designed word game — crossword concept from Russell's mother (fluent Nishnaabemwin speaker)
**Working title:** "Crossword" (pending Nishnaabemwin name from Elder)

## Overview

An Ojibwe crossword puzzle game for the Minoo games hub. Players solve riddle-style clues to fill a crossword grid with Ojibwe words. The second game on the platform after Shkoda (fire/hangman).

The game teaches at three layers simultaneously: reasoning (riddle clues), translation (English concept → Ojibwe word), and spelling (writing the word letter-by-letter). This pedagogy was designed by an Elder — the game structure itself is a teaching tool.

## Game Mechanics

### Core Flow

1. Player reads a clue — either a riddle ("After zero, what comes next") or a definition ("the Ojibwe word for bear")
2. Player figures out the English concept ("first")
3. Player finds the matching Ojibwe word — from the word bank (easy/medium) or from memory (hard)
4. Player types the Ojibwe word letter-by-letter into the crossword grid

### Modes

- **Daily Challenge:** One puzzle per day, same for everyone. Small grid (7×7), 4-6 words. Social — people can discuss "today's puzzle." Day-of-week determines difficulty (Mon/Wed/Fri: easy, Tue/Thu: medium, Sat/Sun: hard).
- **Practice:** Unlimited random puzzles from the word pool. Small grid (7×7). Player picks difficulty tier.
- **Themed Collections:** Curated puzzle packs around topics (Animals, Family, Seasons, Ceremonies, Foods, Nature). Medium grid (10×10), 8-12 words. Players work through puzzles in a theme sequentially. Progress tracked per theme.

### Difficulty Progression

| Aspect | Easy | Medium | Hard |
|--------|------|--------|------|
| Word bank | Ojibwe + English visible | Ojibwe only (no English) | No word bank |
| Word length | Short (3-5 chars) | Mixed lengths | Longer words |
| Clue style | Direct definitions | Mix of definition + riddle | Elder-crafted riddles preferred |
| Hints | Unlimited (reveal a letter) | 2 per puzzle | None |

### Validation

- **CHECK button** validates the currently selected word (not the whole grid at once)
- Correct letters turn green, wrong letters get cleared, correct letters stay
- No lose state — players can keep trying, use hints, or abandon
- This is intentional: the game is about learning, not gatekeeping

### Completion

Puzzle completes when all words are correctly filled. Completion screen shows:
- Time taken, hints used, difficulty tier
- **Teaching section:** Each word in the puzzle with Ojibwe word, English meaning, part of speech, example sentence (from dictionary entry data)
- **"From the Elder" callout:** If any clues had Elder-authored riddles, show them attributed: *"It keeps you warm at night in the bush" — Elder [name]*
- **Share text:** Grid emoji pattern + stats (Wordle/Shkoda style)

### Stats

- localStorage for anonymous players, database for authenticated
- Tracks: puzzles completed, average time, themes completed (N/total), current streak, best streak
- Abandoned puzzles tracked separately from completed

## Page Layout

### Desktop (three-column)

```
┌─────────────────────────────────────────────────────┐
│ Games › Crossword › Daily Challenge                 │
├─────────────────────────────────────────────────────┤
│  [Daily]  [Practice]  [Themes]                      │
├──────────┬────────────────────┬─────────────────────┤
│  ACROSS  │                    │   WORD BANK         │
│  1. ...  │   ┌─┬─┬─┬─┬─┬─┐  │   shkoda — fire     │
│  3. ...  │   │S│H│K│O│D│A│  │   ziibi — river      │
│  5. ...  │   └─┴─┴─┴─┴─┴─┘  │   mkwa — bear       │
│          │   ┌─┬─┬─┬─┬─┐    │   nibi — water       │
│  DOWN    │   │Z│I│I│_│ │    │                       │
│  1. ...  │   └─┴─┴─┴─┴─┘    │   Difficulty: Easy   │
│  2. ...  │       ...         │   Word bank visible   │
│  4. ...  │                    │                       │
│          │  ┌────────────────┐│                       │
│          │  │3 ACROSS: Flows ││                       │
│          │  │but has no legs ││                       │
│          │  └────────────────┘│                       │
├──────────┴────────────────────┴─────────────────────┤
│  [A][B][C][D][E][G][H][I]                           │
│  [K][M][N][O][P][S][W][Z]                           │
│  [ʼ]  [HINT]  [DEL]  [CHECK ✓]                     │
└─────────────────────────────────────────────────────┘
```

### Mobile (stacked)

- Grid on top (scrollable if needed)
- Active clue bar below grid
- Keyboard at bottom (fixed)
- Clue list and word bank accessible via slide-out panels (swipe or button)
- Tap cell to select word; tap again to toggle across/down direction

### Grid Interaction

- Click/tap a cell to select the word it belongs to
- Click same cell again to toggle between across and down (at intersections)
- Active word cells glow with highlight border
- Cursor blinks at next empty cell in the selected word
- Arrow keys navigate between cells (desktop)
- Tab/Shift-Tab move to next/previous word

### Keyboard

- On-screen keyboard on mobile, physical keyboard on desktop
- Ojibwe-specific: ʼ (glottal stop) key
- HINT button: reveals one letter in the selected word (easy: unlimited, medium: 2 total, hard: none)
- DEL: clears current cell and moves cursor back
- CHECK: validates the currently filled word

## Data Model

### New Entity: `CrosswordPuzzle` (config entity)

Stores a complete pre-generated puzzle.

| Field | Type | Description |
|-------|------|-------------|
| `id` | string (PK) | e.g. `daily-2026-03-25`, `animals-003` |
| `grid_size` | integer | 7 or 10 |
| `words` | JSON | Array of placements: `{dictionary_entry_id, row, col, direction, word}` |
| `clues` | JSON | Map of word index → `{auto, elder, elder_author}` |
| `theme` | string (nullable) | Theme slug, null for daily/practice |
| `difficulty_tier` | string | `easy`, `medium`, `hard` |

### Extended: `GameSession`

Add fields to existing entity:

| Field | Type | Description |
|-------|------|-------------|
| `game_type` | string | `shkoda` or `crossword` (add `VALID_GAME_TYPES` constant) |
| `puzzle_id` | string (nullable) | References CrosswordPuzzle ID |
| `grid_state` | JSON | Per-cell fill state and per-word completion status |

Existing fields reused: `mode`, `user_id`, `status`, `daily_date`, `difficulty_tier`, `created_at`.

**Constructor changes required:**
- `dictionary_entry_id` and `direction` must become optional (nullable) — crossword sessions have multiple words (no single entry) and no direction concept
- Add `game_type` to constructor with default `shkoda` for backward compatibility
- Add `VALID_GAME_TYPES = ['shkoda', 'crossword']` constant with validation
- Crossword uses existing modes: `daily`, `practice`. Add `themed` to `VALID_MODES` for themed collections. `streak` is Shkoda-only.

**Status lifecycle for crossword:**
- `in_progress` → `completed` (all words filled correctly)
- `in_progress` → `abandoned` (player gives up or starts a new puzzle)
- Add `abandoned` to `VALID_STATUSES`. No `won`/`lost` distinction — crosswords are completed or abandoned.

**Stats query isolation:**
- Both ShkodaController and CrosswordController stats queries MUST filter by `game_type` to avoid cross-contamination. Existing Shkoda stats queries need updating to add `game_type = 'shkoda'` condition. Backfill: existing GameSession rows without `game_type` are implicitly `shkoda`.

**Migration required:** Add `game_type`, `puzzle_id`, `grid_state` columns. See New Files table for migration file.

**fieldDefinitions additions in GameServiceProvider:**
```php
'game_type'  => ['type' => 'string', 'default' => 'shkoda'],
'puzzle_id'  => ['type' => 'string', 'nullable' => true],
'grid_state' => ['type' => 'json', 'nullable' => true],
```

### Clue Structure

```json
{
  "0": {
    "auto": "the Ojibwe word for fire",
    "elder": "It keeps you warm at night in the bush",
    "elder_author": "Elder Name"
  },
  "1": {
    "auto": "the Ojibwe word for river",
    "elder": null,
    "elder_author": null
  }
}
```

The clue resolver checks for `elder` first, falls back to `auto`. Auto-clues are generated from dictionary entry definitions during puzzle generation.

## Architecture

### New Files

| File | Purpose |
|------|---------|
| `src/Entity/CrosswordPuzzle.php` | Config entity for pre-generated puzzles |
| `src/Support/CrosswordEngine.php` | Grid generation, quality scoring, clue resolution, word validation |
| `src/Controller/CrosswordController.php` | HTTP endpoints for the game |
| `templates/crossword.html.twig` | Game page template |
| `public/js/crossword.js` | Client-side game logic (vanilla JS, IIFE pattern like Shkoda) |
| `migrations/YYYYMMDD_HHMMSS_add_crossword_fields_to_game_session.php` | Add `game_type`, `puzzle_id`, `grid_state` to game_session table |
| `migrations/YYYYMMDD_HHMMSS_create_crossword_puzzle_table.php` | Create crossword_puzzle table (config entity schema) |

### Modified Files

| File | Change |
|------|--------|
| `src/Provider/GameServiceProvider.php` | Register CrosswordPuzzle entity type, add crossword routes |
| `src/Entity/GameSession.php` | Add `game_type`, `puzzle_id`, `grid_state` fields |
| `templates/games.html.twig` | Add crossword game card |
| `templates/components/sidebar-nav.html.twig` | Add crossword nav link |
| `public/css/minoo.css` | Crossword component styles |

### Reused Infrastructure

- `GameAccessPolicy` — same permissions model (public play, auth for stats)
- `GameSession` — extended with new fields
- `GameServiceProvider` — extended with new routes and entity type
- Stats pattern — localStorage + database, same as Shkoda
- Dictionary entries — same word pool as Shkoda

### API Routes

| Method | Route | Auth | Purpose |
|--------|-------|------|---------|
| GET | `/games/crossword` | Public | Render game page |
| GET | `/api/games/crossword/daily` | Public | Today's puzzle data |
| GET | `/api/games/crossword/random` | Public | Random practice puzzle |
| GET | `/api/games/crossword/themes` | Public | List available theme packs with progress |
| GET | `/api/games/crossword/theme/{slug}` | Public | Next unsolved puzzle in theme |
| POST | `/api/games/crossword/check` | Public | Validate a word in the grid (see request schema below) |
| POST | `/api/games/crossword/complete` | Public | Submit finished puzzle, return stats + teaching data (see request schema below) |
| GET | `/api/games/crossword/stats` | Auth | Player stats |

### API Request/Response Schemas

**POST `/api/games/crossword/check`**
```json
// Request
{"session_token": "abc123", "word_index": 0, "letters": ["S","H","K","O","D","A"]}
// Response (correct)
{"correct": true, "word_index": 0, "teaching": {"word": "shkoda", "meaning": "fire", "pos": "ni"}}
// Response (wrong)
{"correct": false, "word_index": 0, "correct_positions": [0,1,2], "wrong_positions": [3,4,5]}
```

**POST `/api/games/crossword/complete`**
```json
// Request
{"session_token": "abc123"}
// Response
{"completed": true, "time_seconds": 142, "hints_used": 1, "words": [
  {"word": "shkoda", "meaning": "fire", "pos": "ni", "example": "...", "elder_clue": "It keeps you warm...", "elder_author": "Elder Name"}
], "stats": {"puzzles_completed": 12, "current_streak": 3, "best_streak": 7, "avg_time": 180}}
```

**GET `/api/games/crossword/themes`**
```json
// Response (auth user — server tracks progress)
{"themes": [{"slug": "animals", "name": "Animals", "total": 20, "completed": 12}, ...]}
// Response (anonymous — no server progress, client tracks via localStorage)
{"themes": [{"slug": "animals", "name": "Animals", "total": 20}, ...]}
```

**GET `/api/games/crossword/theme/{slug}` (anonymous)**
Accepts `?completed=1,3,5` query param — client sends IDs of locally-tracked completed puzzles. Server returns next puzzle not in that list.

**GET `/api/games/crossword/theme/{slug}` (authenticated)**
Server queries completed GameSessions with `puzzle_id LIKE '{slug}-%'` to determine progress. Returns next unsolved puzzle.

### Theme Progress Tracking

- **Authenticated users:** Server queries `GameSession` records filtered by `game_type = 'crossword'`, `status = 'completed'`, and `puzzle_id` prefix matching the theme slug. No separate progress entity needed.
- **Anonymous users:** Client stores completed puzzle IDs in localStorage (`crossword-theme-{slug}`). Sends completed IDs as query param when requesting next puzzle.

## Grid Generation Algorithm

Runs offline via CLI command, not at request time.

### Algorithm: Greedy Placement with Backtracking

1. Select N candidate words from dictionary pool (filtered by theme and difficulty tier)
2. Sort by length descending (longest first — easier to find intersections)
3. Place first word horizontally at grid center
4. For each remaining word, find best intersection with already-placed words (shared letters)
5. Score candidate placements: connectivity, grid compactness, no letter conflicts
6. Place highest-scoring option. If no valid placement exists, backtrack and try different word.
7. Repeat until target word count reached or candidates exhausted

### Quality Filter

All generated grids must pass:

- Minimum word count: 4 (7×7 daily) or 8 (10×10 themed)
- All words connected — no orphan words floating in the grid
- Grid fill ratio > 30%
- No 2-letter words
- All words have a dictionary entry with a definition (so clues can be generated)

### Ojibwe-Specific Considerations

- Common letters (a, i, n, o, k, w, z) help intersection density
- **One character per cell, not one phoneme per cell.** Ojibwe digraphs (`sh`, `zh`, `ch`) occupy two cells. Intersections on the first letter of a digraph are valid (e.g. `shkoda` can intersect on `s`).
- Glottal stop (ʼ) occupies one cell
- Hyphenated compound words: skip during generation (avoid complexity)

### CLI Command

```bash
bin/waaseyaa crossword:generate --theme animals --size 7 --count 20 --tier easy
bin/waaseyaa crossword:generate --daily --date 2026-03-25
```

- Generates candidate grids, stores those passing quality filter
- Logs reject reasons for failed candidates
- Idempotent — won't duplicate existing puzzle IDs

### Daily Puzzle Selection

Same pattern as Shkoda's DailyChallenge:
- Mon/Wed/Fri: easy
- Tue/Thu: medium
- Sat/Sun: hard

**Fallback when no pre-generated daily puzzle exists:** If `daily-{date}` puzzle is missing, the controller generates one on-the-fly using `CrosswordEngine::generateQuick()` (simpler algorithm, smaller word pool, no quality scoring — just "good enough"). This prevents a blank daily page if the batch job hasn't run. The generated puzzle is stored so subsequent requests for the same day get the same grid.

**Scheduling:** Run `bin/waaseyaa crossword:generate --daily --date {tomorrow}` via cron nightly. The fallback handles missed runs gracefully.

## Games Hub Integration

### Games Page (`/games`)

- Shkoda keeps featured card with campfire animation
- Crossword gets equal-prominence featured card alongside it
- Crossword card needs its own visual identity — placeholder grid icon for now, Elder picks the metaphor later
- "Coming soon" placeholders shift down

### Sidebar Navigation

- Games section adds "Crossword" link alongside Shkoda

### URL Structure

- `/games` — hub
- `/games/shkoda` — fire game
- `/games/crossword` — crossword (URL updates when Elder names it)

## Elder Clue Authoring

Phase 1 (initial release): Elder clues added via script — a PHP script that attaches clue text to existing CrosswordPuzzle entities by puzzle ID and word index.

Phase 2 (future): Admin UI for Elders to browse puzzles and write/edit clues inline. Not in scope for initial build.

## Out of Scope

- Multiplayer / competitive crossword
- Puzzle editor UI (puzzles generated via CLI)
- Admin UI for Elder clue authoring (Phase 2)
- Crossword-specific achievements/badges
- Timed challenge mode
