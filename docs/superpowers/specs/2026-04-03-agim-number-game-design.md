# Agim — Ojibwe Number Game Design

**Date:** 2026-04-03  
**Status:** Approved

## Overview

Agim ("count" in Nishnaabemwin) is a progressive number-learning game. Players see an Arabic numeral and type the corresponding Ojibwe word. Numbers are introduced in batches of five, unlocking the next batch only after the current one is mastered.

## Data

All 19 Ojibwe cardinal numbers (1–19) are already in the `dictionary_entry` table sourced from the Ojibwe People's Dictionary (UMN). The number 20 (`niizhtana`) is absent from the DB and is excluded from v1.

Seed data is a static PHP array in `GameServiceProvider` mapping numeral → Ojibwe word → dictionary entry ID:

```php
private const NUMBERS = [
    1  => ['word' => 'bezhig',           'deid' => 4578],
    2  => ['word' => 'niizh',            'deid' => 16239],
    3  => ['word' => 'niswi',            'deid' => 16928],
    4  => ['word' => 'niiwin',           'deid' => 16158],
    5  => ['word' => 'naanan',           'deid' => 15013],
    6  => ['word' => 'ningodwaaswi',     'deid' => 16582],
    7  => ['word' => 'niizhwaaswi',      'deid' => 16355],
    8  => ['word' => 'nishwaaswi',       'deid' => 16810],
    9  => ['word' => 'zhaangaswi',       'deid' => 20922],
    10 => ['word' => 'midaaswi',         'deid' => 13612],
    11 => ['word' => 'ashi-bezhig',      'deid' => 2355],
    12 => ['word' => 'ashi-niizh',       'deid' => 2378],
    13 => ['word' => 'ashi-niswi',       'deid' => 2393],
    14 => ['word' => 'ashi-niiwin',      'deid' => 2375],
    15 => ['word' => 'ashi-naanan',      'deid' => 2371],
    16 => ['word' => 'ashi-ningodwaaswi','deid' => 2387],
    17 => ['word' => 'ashi-niizhwaaswi', 'deid' => 2383],
    18 => ['word' => 'ashi-nishwaaswi',  'deid' => 2390],
    19 => ['word' => 'ashi-zhaangaswi',  'deid' => 2396],
];
```

## Levels

| Level | `difficulty_tier` | Numbers | Unlock condition |
|-------|-------------------|---------|-----------------|
| 1 | `easy`   | 1–5  | Always available |
| 2 | `medium` | 1–10 | Complete Level 1 |
| 3 | `hard`   | 1–15 | Complete Level 2 |
| 4 | `streak` | 1–19 | Complete Level 3 |

"Complete" means answering every number in the level correctly at least once in a single session. Wrong answers are retried until correct — the level does not fail, it just continues until all are answered correctly.

## Session Lifecycle

1. `GET /api/games/agim/start?level={1-4}` — creates a `GameSession` (`game_type=agim`, `difficulty_tier` set to level tier, `status=in_progress`). Returns `session_token`, the ordered list of numerals for this level, and total count.
2. `GET /api/games/agim/prompt` — returns the next numeral to answer (server-tracked via `guesses` JSON on the session). Returns `{numeral, remaining}`.
3. `POST /api/games/agim/answer` — `{session_token, numeral, answer}`. Normalises input (trim, lowercase, strip diacritics for comparison tolerance). Returns `{correct: bool, expected_word, deid}`. Incorrect answers re-queue the numeral.
4. `POST /api/games/agim/complete` — called when all numerals answered correctly. Sets `status=completed`, returns teaching data (full dictionary entry for each number in the level) and stats.

Comparison is **case-insensitive** and **diacritic-tolerant** (e.g. `niiwin` and `nîwin` both accepted) using `Normalizer::normalize()` + `iconv` transliteration, consistent with `CrosswordEngine::validateWord()`.

## Architecture

### New files

| File | Purpose |
|------|---------|
| `src/Controller/AgimController.php` | HTTP controller — `start`, `prompt`, `answer`, `complete`, `stats` actions |
| `templates/agim.html.twig` | Game page — extends `base.html.twig` |
| `tests/Minoo/Unit/Controller/AgimControllerTest.php` | Unit tests |

### Modified files

| File | Change |
|------|--------|
| `src/Provider/GameServiceProvider.php` | Add `NUMBERS` constant, register Agim routes, add `agim` to allowed `game_type` values |

### No new entity types

`GameSession` already supports all required fields. `game_type = 'agim'` is a new value; no schema migration needed (stored in `_data` JSON blob).

## Routes

All registered in `GameServiceProvider::routes()`:

| Route name | Method | Path | Auth |
|---|---|---|---|
| `games.agim` | GET | `/games/agim` | guest |
| `api.games.agim.start` | GET | `/api/games/agim/start` | guest |
| `api.games.agim.prompt` | GET | `/api/games/agim/prompt` | guest |
| `api.games.agim.answer` | POST | `/api/games/agim/answer` | guest |
| `api.games.agim.complete` | POST | `/api/games/agim/complete` | guest |
| `api.games.agim.stats` | GET | `/api/games/agim/stats` | required |

## Template

`agim.html.twig` follows the existing game page pattern:

- Header with game name ("Agim") and level selector (tabs: 1–5, 1–10, 1–15, 1–19)
- Numeral display (large, centered)
- Text input for Ojibwe word entry
- Submit button
- Feedback area: correct (green flash + Ojibwe word) / incorrect (red flash + correct word shown before retrying)
- Progress bar: X of N answered
- On completion: teaching panel showing all numbers in the level with their dictionary definitions

No audio in v1.

## Games Hub

Add Agim card to `templates/games.html.twig` alongside Shkoda, Crossword, and Matcher. Remove it from the "coming soon" placeholder section if present (it isn't — "Listening Quiz" and "Sentence Builder" are the current placeholders; Agim is new).

## Testing

- `AgimControllerTest` covers: start session, prompt sequencing, correct answer, incorrect answer (re-queue), level completion, diacritic-tolerant comparison
- Integration test for full session lifecycle (start → answer all → complete) using in-memory SQLite

## Out of scope (v1)

- Audio pronunciation
- `niizhtana` (20) — not in DB
- Ordinal numbers
- Spaced-repetition or cross-session progress tracking
- Leaderboards
