# Games Milestone Fixes Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fix 4 Games milestone issues: campfire win state (#532), practice mode error handling (#558), themes empty state (#559), and puzzle generation (#560).

**Architecture:** Four independent fixes — each is a standalone branch targeting `main`. #532 is JS-only (shkoda.js). #558 is JS+PHP (crossword.js + CrosswordController). #559 is JS+CSS (crossword.js + minoo.css). #560 is PHP script verification + idempotency.

**Tech Stack:** Vanilla JS, PHP 8.4, CSS (oklch design tokens), PHPUnit 10.5

---

### Task 1: Fix campfire win state (#532)

**Branch:** `fix/532-campfire-win`
**Issue:** `gh issue develop 532 --checkout`

**Files:**
- Modify: `public/js/shkoda.js:134-147` (updateFire)

The bug: `updateFire()` on line 211/248 sets `fireEl.dataset.fireState` to the numeric remaining-guesses count. Then `endGame()` on line 322 sets it to `'win'`. But `updateFire()` is called synchronously before `endGame()` — so the sequence is: guess → `updateFire(false)` sets state to `1` → `endGame(true, data)` sets state to `'win'`. The override should work... unless the issue is that `updateFire()` on line 591 (game start) also needs guarding on restart.

Looking more carefully: the actual bug is likely a CSS specificity issue or that `endGame` fires after a re-render. But the safest fix is to guard `updateFire()` with `state.gameOver` since `endGame` sets `state.gameOver = true` on line 282 BEFORE setting fire state on line 322-328. Wait — `endGame` sets `gameOver = true` first, then sets fire state. And `updateFire` is called BEFORE `endGame`. So the guard won't help for the initial call.

The real fix: in `endGame()`, the fire state override on line 322-323 happens AFTER `state.gameOver = true`. The `updateFire()` call on line 211/248 happens before `endGame()`. The sequence for a winning guess is:
1. `handleServerGuess` → line 211: `updateFire(!data.correct)` → sets fire to remaining count (e.g. 1)
2. line 215-216: `endGame(true, data)` → sets `gameOver = true`, then `fireEl.dataset.fireState = 'win'`

So the override should work. The bug is more subtle — likely the issue is that `data.correct === true` for a winning guess means `updateFire(false)` doesn't shake, but it still sets the numeric state. The `endGame` override to `'win'` should take effect. Let me re-read the issue: "campfire shows state matching remaining guesses (e.g. 1 remaining = nearly dead)". This means `endGame()`'s override isn't working or isn't being reached.

Actually, looking at `handleClientGuess()` (practice mode): after `updateFire(isWrong)` on line 248, the win check is on line 253-259, then `completeGame('won')` on line 262, which makes an API call and calls `endGame` in the `.then()`. That async gap means the fire state shows the numeric value until the API returns. **That's the bug for practice mode.**

For daily mode (`handleServerGuess`), `endGame` is called synchronously at line 216, so it should be fine.

The fix: move fire state override into `endGame` AND add a guard to `updateFire()` for `gameOver`. Also, for practice mode, set fire state immediately on win detection before the async `completeGame` call.

- [ ] **Step 1: Fix `updateFire()` to guard on gameOver**

In `public/js/shkoda.js`, add early return at the top of `updateFire()`:

```javascript
  function updateFire(wasWrong) {
    if (state.gameOver) return;
    var remaining = state.maxWrong - state.wrongGuesses.length;
```

- [ ] **Step 2: Fix practice mode win — set fire state before async completeGame**

In `public/js/shkoda.js`, in `handleClientGuess()`, set fire state immediately on win detection before calling `completeGame()`. After the `if (allRevealed)` check (line 261):

```javascript
    if (allRevealed) {
      state.gameOver = true;
      fireEl.dataset.fireState = 'win';
      spawnSparks();
      completeGame('won');
    } else if (state.wrongGuesses.length >= state.maxWrong) {
      state.gameOver = true;
      fireEl.dataset.fireState = '0';
      spawnSmoke();
      completeGame('lost');
    }
```

- [ ] **Step 3: Prevent double fire-state set in endGame**

In `endGame()`, only set fire state if not already set (practice mode sets it early):

```javascript
    // Trigger animation — override fire state for win/loss
    // (practice mode may have set this already before async complete call)
    if (won && fireEl.dataset.fireState !== 'win') {
      fireEl.dataset.fireState = 'win';
      spawnSparks();
    } else if (!won && fireEl.dataset.fireState !== '0') {
      fireEl.dataset.fireState = '0';
      spawnSmoke();
    }
```

- [ ] **Step 4: Verify CSS win state selectors exist**

Confirm `[data-fire-state="win"]` rules are present in `minoo.css`. No changes expected — just verify.

- [ ] **Step 5: Commit**

```bash
git add public/js/shkoda.js
git commit -m "$(cat <<'EOF'
fix(#532): campfire roars on win regardless of remaining guesses

Guard updateFire() with gameOver check so endGame() has final say on
fire state. In practice mode, set fire state immediately on win/loss
detection before async completeGame() call to prevent flash of dying
fire. Sparks/smoke animations now fire reliably.

Co-Authored-By: Claude Opus 4.6 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

### Task 2: Crossword practice mode graceful error (#558)

**Branch:** `fix/558-practice-error`
**Issue:** `gh issue develop 558 --checkout`

**Files:**
- Modify: `src/Controller/CrosswordController.php:95-96` (random endpoint)
- Modify: `public/js/crossword.js:1074-1119` (startGame error handling)
- Create: `tests/Minoo/Unit/Controller/CrosswordControllerTest.php`

- [ ] **Step 1: Write failing test — random returns 404 when no puzzles exist**

Create `tests/Minoo/Unit/Controller/CrosswordControllerTest.php`:

```php
<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Controller;

use Minoo\Controller\CrosswordController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Access\Gate\GateInterface;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Entity\Storage\EntityQueryInterface;
use Waaseyaa\Entity\Storage\EntityStorageInterface;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

#[CoversClass(CrosswordController::class)]
final class CrosswordControllerTest extends TestCase
{
    private EntityTypeManager $entityTypeManager;
    private Environment $twig;
    private GateInterface $gate;
    private EntityStorageInterface $puzzleStorage;
    private EntityQueryInterface $puzzleQuery;
    private AccountInterface $account;
    private HttpRequest $request;

    protected function setUp(): void
    {
        $this->puzzleQuery = $this->createMock(EntityQueryInterface::class);
        $this->puzzleQuery->method('condition')->willReturnSelf();
        $this->puzzleQuery->method('sort')->willReturnSelf();
        $this->puzzleQuery->method('range')->willReturnSelf();

        $this->puzzleStorage = $this->createMock(EntityStorageInterface::class);
        $this->puzzleStorage->method('getQuery')->willReturn($this->puzzleQuery);

        $this->entityTypeManager = $this->createMock(EntityTypeManager::class);
        $this->entityTypeManager->method('getStorage')
            ->willReturnCallback(fn(string $type) => match ($type) {
                'crossword_puzzle' => $this->puzzleStorage,
                default => $this->createMock(EntityStorageInterface::class),
            });

        $this->twig = new Environment(new ArrayLoader([
            'crossword.html.twig' => '{{ path }}',
        ]));

        $this->gate = $this->createMock(GateInterface::class);
        $this->account = $this->createMock(AccountInterface::class);
        $this->request = HttpRequest::create('/');
    }

    #[Test]
    public function random_returns_404_when_no_puzzles_for_tier(): void
    {
        $this->puzzleQuery->method('execute')->willReturn([]);

        $controller = new CrosswordController($this->entityTypeManager, $this->twig, $this->gate);
        $response = $controller->random([], ['tier' => 'medium'], $this->account, $this->request);

        $this->assertSame(404, $response->statusCode);
        $content = json_decode($response->content, true);
        $this->assertSame('no_puzzles', $content['error']);
        $this->assertSame('medium', $content['tier']);
    }

    #[Test]
    public function random_defaults_invalid_tier_to_easy(): void
    {
        $this->puzzleQuery->method('execute')->willReturn([]);

        $controller = new CrosswordController($this->entityTypeManager, $this->twig, $this->gate);
        $response = $controller->random([], ['tier' => 'extreme'], $this->account, $this->request);

        $this->assertSame(404, $response->statusCode);
        $content = json_decode($response->content, true);
        $this->assertSame('easy', $content['tier']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Controller/CrosswordControllerTest.php -v`
Expected: FAIL — `random()` currently returns 503, not 404, and doesn't include `tier` in response.

- [ ] **Step 3: Update CrosswordController::random() to return 404 with tier info**

In `src/Controller/CrosswordController.php`, change lines 95-96:

Old:
```php
        if ($practiceIds === []) {
            return $this->json(['error' => 'No puzzles available'], 503);
        }
```

New:
```php
        if ($practiceIds === []) {
            return $this->json(['error' => 'no_puzzles', 'tier' => $tier], 404);
        }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Controller/CrosswordControllerTest.php -v`
Expected: PASS

- [ ] **Step 5: Update JS error handling in startGame()**

In `public/js/crossword.js`, replace the error handling in `startGame()` (lines 1074-1119). Change the `data.error` block:

Old (lines 1075-1079):
```javascript
      if (data.error) {
        showLoading(false);
        loadingEl.hidden = false;
        loadingEl.querySelector('p').textContent = data.error;
        return;
      }
```

New:
```javascript
      if (data.error) {
        showLoading(false);
        if (data.error === 'no_puzzles') {
          showNoPuzzles(data.tier || state.tier);
        } else {
          loadingEl.hidden = false;
          var errP = loadingEl.querySelector('p');
          if (errP) errP.textContent = data.error;
        }
        return;
      }
```

- [ ] **Step 6: Add showNoPuzzles() function**

In `public/js/crossword.js`, add this function after the `showComplete()` function (around line 113):

```javascript
  function showNoPuzzles(tier) {
    showGame(false);
    showComplete(true);
    completeTitleEl.textContent = 'No puzzles available yet';
    completeTeachingsEl.innerHTML =
      '<p>No ' + escapeHtml(tier) + ' puzzles have been generated yet. ' +
      'Try an easy puzzle instead!</p>';
    completeStatsEl.innerHTML = '';
    completeActionsEl.innerHTML = '';

    var easyBtn = document.createElement('button');
    easyBtn.className = 'game-btn game-btn--primary';
    easyBtn.textContent = 'Play Easy';
    easyBtn.addEventListener('click', function () {
      state.tier = 'easy';
      // Update tier button UI
      var tiers = difficultyEl.querySelectorAll('.crossword__tier');
      tiers.forEach(function (t) {
        t.classList.toggle('crossword__tier--active', t.dataset.tier === 'easy');
      });
      startGame();
    });
    completeActionsEl.appendChild(easyBtn);
  }
```

- [ ] **Step 7: Commit**

```bash
git add src/Controller/CrosswordController.php public/js/crossword.js tests/Minoo/Unit/Controller/CrosswordControllerTest.php
git commit -m "$(cat <<'EOF'
fix(#558): practice mode shows friendly error when no puzzles for tier

Return 404 with error code and tier from random() endpoint instead of
503 with generic message. JS catches the no_puzzles error and shows
a helpful message with "Play Easy" fallback button.

Co-Authored-By: Claude Opus 4.6 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

### Task 3: Themes tab empty state (#559)

**Branch:** `fix/559-themes-empty`
**Issue:** `gh issue develop 559 --checkout`

**Files:**
- Modify: `public/js/crossword.js:991-1022` (renderThemes)
- Modify: `public/css/minoo.css:4580-4585` (add empty state styles)

- [ ] **Step 1: Add empty state to renderThemes()**

In `public/js/crossword.js`, add empty state handling at the start of `renderThemes()`. Replace lines 991-1022:

Old:
```javascript
  function renderThemes(themes) {
    themesListEl.innerHTML = '';

    themes.forEach(function (theme) {
```

New:
```javascript
  function renderThemes(themes) {
    themesListEl.innerHTML = '';

    if (!themes || themes.length === 0) {
      themesListEl.innerHTML =
        '<div class="crossword__themes-empty">' +
          '<p class="crossword__themes-empty-title">Themed Puzzle Packs Coming Soon</p>' +
          '<p class="crossword__themes-empty-body">' +
            'We\u2019re working on themed collections like Animals, Family, and Seasons.' +
          '</p>' +
        '</div>';
      return;
    }

    themes.forEach(function (theme) {
```

- [ ] **Step 2: Add CSS for empty state**

In `public/css/minoo.css`, after the `.crossword__themes-list` rule (after line 4585), add:

```css
  .crossword__themes-empty {
    grid-column: 1 / -1;
    text-align: center;
    padding: var(--space-xl) var(--space-md);
    color: var(--text-muted);
  }

  .crossword__themes-empty-title {
    font-size: var(--text-lg);
    font-weight: 600;
    color: var(--text-secondary);
    margin-block-end: var(--space-xs);
  }

  .crossword__themes-empty-body {
    font-size: var(--text-sm);
    max-inline-size: 36ch;
    margin-inline: auto;
  }
```

- [ ] **Step 3: Bump CSS cache version**

In `templates/base.html.twig`, bump the `?v=N` query string on the CSS `<link>` tag.

- [ ] **Step 4: Commit**

```bash
git add public/js/crossword.js public/css/minoo.css templates/base.html.twig
git commit -m "$(cat <<'EOF'
fix(#559): show 'coming soon' empty state on crossword themes tab

When no themed puzzle packs exist, renderThemes() now shows a centered
placeholder message instead of blank space. Styles use existing design
tokens.

Co-Authored-By: Claude Opus 4.6 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

### Task 4: Verify and harden puzzle generation script (#560)

**Branch:** `feat/560-puzzle-generation`
**Issue:** `gh issue develop 560 --checkout`

**Files:**
- Modify: `scripts/populate_crossword_puzzles.php` (add more practice puzzles, verify idempotency)

The script is already idempotent — `buildPuzzle()` checks for existing puzzles on lines 79-85 and skips them. It generates practice-001 (easy), practice-002 (easy), practice-003 (medium), practice-004 (medium), practice-005 (hard). This means medium and hard puzzles DO get created when the script runs.

The real issue is that the script was only run once and the database on production doesn't have these puzzles. But we should also add more practice puzzles per tier for variety.

- [ ] **Step 1: Add more practice puzzles for medium and hard tiers**

In `scripts/populate_crossword_puzzles.php`, expand the practice tier list and count. Replace lines 167-188:

Old:
```php
echo "\n--- Practice Puzzles ---\n";
$practiceTiers = ['easy', 'easy', 'medium', 'medium', 'hard'];

for ($i = 1; $i <= 5; $i++) {
    $practiceId = sprintf('practice-%03d', $i);
    $tier = $practiceTiers[$i - 1];
```

New:
```php
echo "\n--- Practice Puzzles ---\n";
$practiceTiers = [
    'easy', 'easy', 'easy',
    'medium', 'medium', 'medium',
    'hard', 'hard', 'hard',
];

for ($i = 1; $i <= count($practiceTiers); $i++) {
    $practiceId = sprintf('practice-%03d', $i);
    $tier = $practiceTiers[$i - 1];
```

- [ ] **Step 2: Run the script locally to verify**

Run: `php scripts/populate_crossword_puzzles.php`
Expected: Script creates practice-001 through practice-009 (skipping any that exist), with 3 each of easy/medium/hard. Output shows "Created" or "Skipping" for each.

- [ ] **Step 3: Commit**

```bash
git add scripts/populate_crossword_puzzles.php
git commit -m "$(cat <<'EOF'
feat(#560): expand practice puzzle pool to 9 (3 per tier)

Add 3 easy, 3 medium, 3 hard practice puzzles instead of 2/2/1.
Script remains idempotent — skips existing puzzles. Run on production
to populate medium and hard tiers.

Co-Authored-By: Claude Opus 4.6 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

### Task 5: Run full test suite

After all branches are merged:

- [ ] **Step 1: Run PHPUnit**

Run: `./vendor/bin/phpunit`
Expected: All tests pass (including new CrosswordControllerTest).

- [ ] **Step 2: Run autoloader dump**

Run: `composer dump-autoload`
(Gotcha from CLAUDE.md: worktree autoloader corruption)

- [ ] **Step 3: Close issues**

```bash
gh issue close 532 -c "Fixed in fix/532-campfire-win"
gh issue close 558 -c "Fixed in fix/558-practice-error"
gh issue close 559 -c "Fixed in fix/559-themes-empty"
gh issue close 560 -c "Fixed in feat/560-puzzle-generation"
```
