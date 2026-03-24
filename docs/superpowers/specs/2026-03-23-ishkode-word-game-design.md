# Ishkode — Ojibwe Word Game

**Date:** 2026-03-23
**Status:** Design approved

## Summary

Ishkode ("fire" in Ojibwe) is a hangman-style word guessing game that pulls dynamically from Minoo's dictionary database. Players guess Ojibwe words letter by letter while a campfire burns — correct guesses keep it alive, wrong guesses let it fade. Every round ends with a teaching moment: stem, example sentence, and related words from the database.

## Goals

- Build language engagement through play — make Ojibwe vocabulary stick
- Create daily habit with shared community challenge (Wordle model)
- Drive traffic to existing dictionary entries via deep links
- Establish the games infrastructure pattern for future Minoo games

## Game Concept

### Name & Metaphor

**Ishkode** (ᐃᔥᑯᑌ) — Ojibwe for "fire." The campfire metaphor replaces the hangman figure: fire burns bright with correct guesses, fades to embers and cold stones on loss, roars back on a win. Fire is central to gathering, storytelling, and ceremony — a natural fit for learning language together.

### Two Directions

- **English → Ojibwe:** Player sees English definition + part of speech as clue, guesses the Ojibwe word letter by letter. Active recall.
- **Ojibwe → English:** Player sees the Ojibwe word being revealed, guesses letters to uncover it, then sees the English meaning. Spelling familiarity.

Player can choose direction per session. Daily challenge has a fixed direction (alternates).

### Three Modes

| Mode | Description | Session Length | Stats |
|------|-------------|---------------|-------|
| **Daily Challenge** | Same word for everyone, one attempt per day | 1 word | Win/loss, guesses used, streak |
| **Practice** | Unlimited random words, adaptive difficulty | Play as long as you want | Games played, win rate |
| **Streak** | Endless until you lose, tracks longest run | Until first loss | Current streak, best streak |

### Adaptive Difficulty

Difficulty tiers based on word length + part of speech:

| Tier | Word Length | Part of Speech | Max Wrong Guesses |
|------|-----------|----------------|-------------------|
| Easy | ≤5 chars | nouns (ni, na) | 7 |
| Medium | 6-8 chars | nouns + verbs (vai, vii) | 6 |
| Hard | 9+ chars | all types (vti, vta) | 5 |

- **Practice mode:** Starts at Easy, escalates after 3 consecutive wins, drops back on 2 consecutive losses.
- **Streak mode:** Starts at Medium.
- **Daily challenge:** Rotates — easy Mon/Wed/Fri, medium Tue/Thu, hard Sat/Sun.

### Win & Loss States

**Win — "Miigwech! You kept the fire burning"**
- Campfire roars back (CSS scale + spark particles, 1-second animation)
- Revealed word with definition, part of speech
- Teaching moment: stem, example sentence (from DB), related words
- Stats: guesses used, current streak, win rate
- Actions: Next Word, Share

**Loss — "The fire faded — but every word is a spark"**
- Cold stones with smoke wisps (CSS animation)
- Same teaching moment — learning happens on loss too
- Actions: Try Again, View Entry (deep link to /language/{slug})
- No shame language — encouragement only

### Share Mechanic

Wordle-style emoji grid, no spoilers:

```
🔥 Ishkode — Daily Challenge
March 23, 2026 · English → Ojibwe
🔥🔥🪨🔥🔥🔥🪨
5/7 guesses · fire still burning
minoo.live/games/ishkode
```

- 🔥 = correct guess, 🪨 = wrong guess
- Sequence shows the journey, not the word
- Native share API (mobile) → clipboard fallback (desktop)
- No social media SDKs

## Architecture

### Approach: Hybrid (client-side play + server validation)

Two validation strategies depending on mode:

- **Daily Challenge:** Server-validated per guess via `POST /api/games/ishkode/guess`. The word is never sent to the client. This prevents inspect-element cheating on the competitive shared challenge.
- **Practice & Streak:** Client-validated for snappy UX. The `/word` endpoint returns the word in an obfuscated payload (base64-encoded, decoded in JS). No network round-trip per letter. Scores from these modes are lower-stakes — speed matters more than cheat-proofing.

Both modes submit the completed game via `POST /api/games/ishkode/complete` for stats persistence. This hybrid approach gives instant gameplay where it matters and trustworthy scoring where it counts.

### Data Model

**No new entity types for word data** — reads from existing `dictionary_entry` and `example_sentence` tables.

Two new entities:

#### GameSession (content entity)

| Field | Type | Description |
|-------|------|-------------|
| `gsid` | int (PK) | Session ID |
| `uuid` | string | Public identifier |
| `user_id` | int (nullable) | Null for anonymous players |
| `mode` | string | `daily`, `practice`, `streak` |
| `direction` | string | `ojibwe_to_english`, `english_to_ojibwe` |
| `dictionary_entry_id` | entity_reference | FK → dictionary_entry |
| `guesses` | text_long (JSON) | Array of letters guessed, in order |
| `wrong_count` | int | Number of incorrect guesses |
| `status` | string | `in_progress`, `won`, `lost` |
| `daily_date` | string (nullable) | YYYY-MM-DD for daily challenge mode |
| `difficulty_tier` | string | `easy`, `medium`, `hard` |
| `created_at` | datetime | Session start |
| `updated_at` | datetime | Last guess |

#### DailyChallenge (config entity — extends ConfigEntityBase)

Uses `ConfigEntityBase` with `keys: ['id' => 'date', 'label' => 'date']`. No UUID — config entities don't require one.

| Field | Type | Description |
|-------|------|-------------|
| `date` | string (PK) | YYYY-MM-DD — also serves as the label |
| `dictionary_entry_id` | entity_reference | FK → dictionary_entry |
| `direction` | string | `ojibwe_to_english`, `english_to_ojibwe` |
| `difficulty_tier` | string | `easy`, `medium`, `hard` |

Daily challenges are generated ahead of time (cron or manual script). Falls back to deterministic random selection seeded by the date if no pre-generated challenge exists.

### API Endpoints

```
GET  /games/ishkode                → Game page (Twig template, loads JS)
GET  /api/games/ishkode/daily      → Today's challenge metadata
GET  /api/games/ishkode/word       → Random word for practice/streak
POST /api/games/ishkode/guess      → Validate a letter guess (daily only)
POST /api/games/ishkode/complete   → Submit completed game, get stats
GET  /api/games/ishkode/stats      → Player stats (auth required)
```

**Note:** The `/guess` endpoint is only used by Daily Challenge mode (server-validated). Practice and Streak modes validate client-side and only call `/complete` at game end.

**GET /api/games/ishkode/daily**
```json
{
  "session_token": "uuid",
  "word_length": 7,
  "clue": "fire",
  "clue_detail": "noun (inanimate)",
  "direction": "english_to_ojibwe",
  "difficulty": "easy",
  "max_wrong": 7,
  "date": "2026-03-23"
}
```

**POST /api/games/ishkode/guess**
```json
// Request
{ "session_token": "uuid", "letter": "k" }

// Response
{
  "correct": true,
  "positions": [3],
  "remaining_wrong": 5,
  "game_over": false
}
```

**POST /api/games/ishkode/complete** (on game end)
```json
// Response
{
  "word": "ishkode",
  "definition": "fire",
  "part_of_speech": "ni",
  "stem": "/ishkode-/",
  "example_ojibwe": "Ishkode-waaboo minikwen.",
  "example_english": "Drink some coffee (fire-water).",
  "slug": "ishkode",
  "stats": {
    "games_played": 42,
    "win_rate": 0.87,
    "current_streak": 3,
    "best_streak": 11
  }
}
```

The actual word is only revealed in the `/complete` response — never sent to the client during gameplay. This prevents inspect-element cheating on daily challenges.

### Authentication

- **Public play:** No login required. Session token (UUID) tracks the game server-side. Stats stored in localStorage.
- **Login for persistence:** Authenticated users get server-side stat tracking, leaderboard eligibility, and cross-device progress.
- **GameAccessPolicy:** Public access to game page and play endpoints. Auth required for `/stats` endpoint.

### Keyboard

On-screen keyboard includes standard Latin letters plus Ojibwe-specific characters:
- Glottal stop (ʼ)
- Long vowels handled as double letters (aa, ii, oo) — no special characters needed
- Keyboard layout groups vowels and common Ojibwe consonants (sh, zh treated as digraphs — each letter guessed individually)

### Campfire Animation

7 states from full blaze (7 guesses remaining) to cold stones (0). Implementation:

- **Inline SVG** with layered flame paths
- **CSS transitions** driven by `data-fire-state="N"` attribute on the container
- Flame height: `transform: scaleY()` keyed to state
- Glow radius: `filter: drop-shadow()` shrinks with state
- Color shift: bright orange → deep red → gray ash
- **Win animation:** `cubic-bezier` scale-up, spark particles via CSS `@keyframes`, warm glow pulse (1 second)
- **Loss animation:** smoke wisps rising from stones via CSS animation
- No JS animation library — pure CSS transitions

## File Structure

```
src/
├── Entity/GameSession.php              # Content entity — game state
├── Entity/DailyChallenge.php           # Config entity (ConfigEntityBase) — daily word selection
├── Controller/IshkodeController.php    # Game page + all API endpoints
├── Provider/GameServiceProvider.php    # Entity registration, routes
├── Access/GameAccessPolicy.php         # Public play, auth for stats
├── Support/IshkodeEngine.php           # Word selection, guess validation, difficulty, stats
templates/
├── ishkode.html.twig                   # Game page (extends base.html.twig)
public/
├── js/ishkode.js                       # Client-side game engine
├── css/minoo.css                       # Game styles added to @layer components (campfire SVG, keyboard, animations)
migrations/
├── YYYYMMDD_HHMMSS_create_game_session_table.php
├── YYYYMMDD_HHMMSS_create_daily_challenge_table.php
tests/Minoo/Unit/
├── Entity/GameSessionTest.php
├── Entity/DailyChallengeTest.php
├── Access/GameAccessPolicyTest.php
├── Support/IshkodeEngineTest.php
```

### Migrations

Two migrations required for the new entity types:

1. **`create_game_session_table`** — creates `game_session` table with all fields from the GameSession entity
2. **`create_daily_challenge_table`** — creates `daily_challenge` table with `date` as primary key

Generate with `bin/waaseyaa make:migration <name>`, run with `bin/waaseyaa migrate`.

### CSS Integration

Game styles go into the existing `public/css/minoo.css` under `@layer components` — no separate CSS file. This maintains the single-file layer architecture and avoids specificity conflicts. Game-specific custom properties (campfire colors, animation timings) go in `@layer tokens`.

### Routing

The `/games/ishkode` page route is registered in `GameServiceProvider::routes()` pointing to `IshkodeController::page()` — NOT auto-served via path-template (which would serve at `/ishkode`). The `/games/` namespace is intentional for future game types. All API routes (`/api/games/ishkode/*`) are also registered in the service provider.

## Word Selection Rules

Words are selected from `dictionary_entry` where:
- `status = 1` (published)
- `consent_public = 1`
- `definition` is not empty (needed for clue)
- Word length matches difficulty tier
- Part of speech matches difficulty tier

Practice mode avoids recently played words (tracked in localStorage for anonymous, server-side for authenticated). Daily challenge uses pre-generated `DailyChallenge` entries, falling back to deterministic random selection seeded by the date.

### Word Pool Validation

Before launch, verify pool size per difficulty tier with:

```sql
SELECT
  CASE
    WHEN LENGTH(word) <= 5 THEN 'easy'
    WHEN LENGTH(word) <= 8 THEN 'medium'
    ELSE 'hard'
  END AS tier,
  COUNT(*) AS count
FROM dictionary_entry
WHERE status = 1 AND consent_public = 1 AND definition != ''
GROUP BY tier;
```

**Minimum viable pool:** 20 words per tier for practice mode variety, 30+ for daily challenge longevity. If any tier is below threshold, relax the part-of-speech filter for that tier.

## Future Considerations

- **Leaderboard page** — top streaks, daily challenge completion rates
- **Sound effects** — fire crackling, correct/wrong feedback (optional toggle)
- **Word of the day notification** — push or email for daily challenge
- **Additional games** — the `GameSession` entity and `/api/games/` namespace support multiple game types
- **Multiplayer** — two players racing to guess the same word (future game type)
- **Pronunciation audio** — dictionary entries have `audio_url`; teaching moment could include a play button for word pronunciation
- **OPD integration** — as more dictionary entries sync from OPD pipeline, word pool grows automatically
