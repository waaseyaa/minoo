# Matcher Game Design

Word-matching game for Minoo's games hub. Players drag lines between Ojibwe words and their English definitions.

## Overview

- **Mechanic:** Drag-to-connect (desktop) / tap-to-connect (mobile)
- **Match type at launch:** Word-to-Definition (Ojibwe word <-> English definition)
- **Future match types:** Word-to-Image, Audio-to-Word, Sentence-to-Translation
- **Modes:** Daily, Practice
- **Difficulty tiers:** Easy (4 pairs), Medium (6 pairs), Hard (8 pairs)
- **Direction toggle:** Ojibwe-to-English / English-to-Ojibwe
- **Game name:** TBD — pending Ojibwe word for "match" or "connect" from Russell's mother

## Architecture

Follows the established Shkoda/Crossword pattern exactly.

### New Files

| File | Purpose |
|---|---|
| `src/Controller/MatcherController.php` | HTTP controller, uses `GameControllerTrait` |
| `src/Support/MatcherEngine.php` | Word selection, daily seeding, match validation |
| `templates/matcher.html.twig` | Game page template (extends `base.html.twig`) |
| `tests/Minoo/Unit/Support/MatcherEngineTest.php` | Engine unit tests |

### Modified Files

| File | Change |
|---|---|
| `src/Provider/GameServiceProvider.php` | Add matcher routes + `'matcher'` to `game_type` validation |
| `src/Entity/GameSession.php` | Add `'matcher'` to allowed `game_type` values |
| `src/Access/GameAccessPolicy.php` | Already covers `game_session` — no change needed |
| `templates/games.html.twig` | Add matcher card to games hub |
| `public/css/minoo.css` | Add matcher component styles in `@layer components` |

### No New Entity Types

Reuses existing `game_session` with `game_type: 'matcher'` and `daily_challenge` with `game_type: 'matcher'`. No new entities, providers, or access policies needed.

### Routes

| Method | Path | Handler | Purpose |
|---|---|---|---|
| GET | `/games/matcher` | `MatcherController::page` | Game page |
| GET | `/api/games/matcher/daily` | `MatcherController::daily` | Daily challenge pairs |
| GET | `/api/games/matcher/practice` | `MatcherController::practice` | Random pairs by difficulty |
| POST | `/api/games/matcher/match` | `MatcherController::match` | Validate a single match attempt |
| POST | `/api/games/matcher/complete` | `MatcherController::complete` | Finish game, record stats |
| GET | `/api/games/matcher/stats` | `MatcherController::stats` | Player stats |

### API Contracts

**GET `/api/games/matcher/daily`**
```json
{
  "token": "uuid",
  "pairs": [
    {"id": "deid_123", "ojibwe": "makwa", "english": "bear"},
    {"id": "deid_456", "ojibwe": "nibi", "english": "water"}
  ],
  "difficulty": "easy",
  "direction": "ojibwe_to_english"
}
```

**GET `/api/games/matcher/practice?difficulty=medium&direction=english_to_ojibwe`**
```json
{
  "token": "uuid",
  "pairs": [
    {"id": "deid_123", "ojibwe": "makwa", "english": "bear"}
  ],
  "difficulty": "medium",
  "direction": "english_to_ojibwe"
}
```

**POST `/api/games/matcher/match`**
Request: `{"token": "uuid", "left_id": "deid_123", "right_id": "deid_456"}`
Response: `{"correct": true}` or `{"correct": false, "expected": "deid_789"}`

**POST `/api/games/matcher/complete`**
Request: `{"token": "uuid"}`
Response:
```json
{
  "time_seconds": 42,
  "attempts": 6,
  "wrong_count": 2,
  "accuracy": 66.7,
  "pairs_count": 4
}
```

**GET `/api/games/matcher/stats`**
```json
{
  "games_played": 10,
  "win_rate": 80.0,
  "best_time": 18,
  "avg_accuracy": 85.5,
  "current_streak": 3,
  "best_streak": 5
}
```

## Game Session Fields

Stored in existing `game_session._data` JSON blob:

| Field | Type | Description |
|---|---|---|
| `game_type` | string | `'matcher'` (required) |
| `mode` | string | `'daily'` or `'practice'` |
| `direction` | string | `'english_to_ojibwe'` or `'ojibwe_to_english'` |
| `difficulty_tier` | string | `'easy'`, `'medium'`, or `'hard'` |
| `pairs` | array | `[{id, ojibwe, english}]` — the word set for this round |
| `matches` | array | `[{left_id, right_id, correct}]` — attempt log |
| `wrong_count` | int | Number of incorrect attempts |
| `started_at` | string | ISO timestamp, set on first match attempt |
| `completed_at` | string | ISO timestamp, set on completion |

## MatcherEngine

New class: `src/Support/MatcherEngine.php`

### Methods

- **`selectWords(int $count, ?string $dailyDate = null): array`** — Pulls random dictionary entries with non-empty definitions. For daily mode, seeds RNG with `crc32("matcher-{$date}")`. Filters out entries where definition is empty, malformed JSON, or only linguistic abbreviations. Ensures no duplicate definitions in a single round.

- **`validateMatch(string $leftId, string $rightId, array $pairs): array`** — Checks if the pairing is correct. Returns `['correct' => bool, 'expected' => string|null]`.

- **`cleanDefinition(string $raw): string`** — Reuses JSON-unwrapping pattern from `GameControllerTrait::cleanDefinition()` plus OPD abbreviation expansion.

- **`dailyPairs(string $date, string $difficulty): array`** — Deterministic pair selection for daily challenge. Pair count determined by difficulty tier.

### Word Filtering Rules

1. Must have non-null, non-empty `word` field (the Ojibwe term)
2. Must have non-null, non-empty `definition` field
3. Exclude entries where cleaned definition is only a part-of-speech abbreviation
4. No duplicate definitions within a single round (avoids ambiguous matches)

## Game Flow

### Play Sequence

1. Player navigates to `/games/matcher`
2. Mode tabs at top: Daily / Practice
3. Practice mode shows difficulty selector (Easy / Medium / Hard) and direction toggle
4. Daily mode uses fixed direction (`ojibwe_to_english`) and easy difficulty
5. Frontend fetches pairs from API, shuffles each column independently
6. Words appear in two columns with SVG overlay area between them

### Interaction

- **Desktop:** Click-drag from a word on one side to a word on the other. SVG line follows cursor. Release on target = attempt.
- **Mobile:** Tap a word on left side (highlights with active state), tap a word on right side. Line draws automatically between them.
- **Correct match:** Line locks in green (`--color-correct`), both words dim with checkmark. Subtle pulse animation.
- **Wrong match:** Line turns red briefly (`--color-wrong`), shake animation on both words, line disappears. Wrong count increments.
- Timer starts on first interaction, not on page load.

### Completion

- All pairs matched: completion card slides in
- Shows: time, total attempts, accuracy percentage, share button
- Daily mode: one attempt per day, completed daily shows results on revisit

### Daily Mode

- Same pairs for everyone, seeded by date (same `crc32` pattern as ShkodaEngine)
- Uses `daily_challenge` entity with `game_type: 'matcher'`
- Direction fixed to `ojibwe_to_english`
- Difficulty: easy (4 pairs)

## Template & CSS

### `matcher.html.twig`

Extends `base.html.twig`. Structure:
- Breadcrumb: Games > Matcher
- Mode tabs (daily / practice)
- Difficulty selector (practice only)
- Direction toggle (practice only)
- Game board: two word columns + SVG overlay for connection lines
- Stats row (time, attempts)
- Completion modal
- All game logic in inline `<script>` (no build step, same as Shkoda/Crossword)

### CSS Additions (`minoo.css`, `@layer components`)

| Class | Purpose |
|---|---|
| `.matcher-board` | Flexbox layout: left column + SVG zone + right column |
| `.matcher-column` | Vertical stack of word cards |
| `.matcher-word` | Word card with states: default, hover, active, matched, wrong |
| `.matcher-word--matched` | Dimmed + checkmark state |
| `.matcher-word--wrong` | Shake animation + red flash |
| `.matcher-word--active` | Highlighted selection (mobile tap mode) |
| `.matcher-svg` | SVG overlay positioned absolute over the board |
| `.matcher-line` | SVG line styles: drawing (dashed), locked (solid green), error (solid red) |
| `.matcher-complete` | Completion card overlay |

Reuses existing tokens: `--color-correct`, `--color-wrong`, `.game-stat`, `.game-btn`, `.game-toast`.

Mobile breakpoint: columns compress, tap mode auto-activates via media query + touch detection.

### Games Hub Update (`games.html.twig`)

Add matcher card between Crossword and "More games coming" section:
- Icon/illustration TBD (connection lines motif)
- "Word Match" label, description: "Draw lines between Ojibwe words and English definitions"
- "Play now" link to `/games/matcher`

## Stats

Reuses `GameStatsCalculator::build()` — already filters by `game_type`. No changes needed to the calculator. Stats returned:

- `games_played` — total completed matcher sessions
- `win_rate` — percentage of games with zero wrong matches
- `best_time` — fastest completion
- `avg_accuracy` — average accuracy across games
- `current_streak` — consecutive daily completions
- `best_streak` — all-time best daily streak

Completion response also includes per-game `accuracy` (correct attempts / total attempts * 100).

## Access Control

No changes. `GameAccessPolicy` already covers `game_session` and `daily_challenge` entity types. The matcher uses these same entities with `game_type: 'matcher'`.

## Testing

### Unit Tests (`MatcherEngineTest.php`)

- `selectWords()` returns correct count for each difficulty
- `selectWords()` excludes entries without definitions
- `selectWords()` excludes entries with only abbreviation definitions
- `selectWords()` produces no duplicate definitions in a round
- `dailyPairs()` is deterministic for same date
- `dailyPairs()` differs across dates
- `validateMatch()` returns correct for valid pair
- `validateMatch()` returns incorrect + expected for invalid pair
- `cleanDefinition()` unwraps JSON arrays
- `cleanDefinition()` expands OPD abbreviations

### Integration Tests

- `MatcherController` daily endpoint returns pairs with valid structure
- Practice endpoint respects difficulty parameter (pair count)
- Match endpoint validates correct/incorrect attempts
- Complete endpoint records stats
- Stats endpoint returns data filtered to `game_type: 'matcher'`
- Anonymous sessions work (no auth required)
- Authenticated sessions track user_id for stats

## Future Enhancements (Out of Scope)

- **Streak mode** — consecutive days of daily play
- **Word-to-Image** match type (needs image assets)
- **Audio-to-Word** match type (needs audio assets)
- **Sentence-to-Translation** match type (uses `example_sentence` entity)
- **Near-match difficulty** — harder tiers include words with similar definitions
- **Leaderboard** — fastest times per difficulty
- **Ojibwe game name** — pending consultation with Elder
