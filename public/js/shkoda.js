/**
 * Shkoda — Ojibwe Word Game
 *
 * Client-side game engine. Handles:
 * - Mode switching (daily/practice/streak)
 * - Direction toggle (english_to_ojibwe / ojibwe_to_english)
 * - Keyboard rendering and input (on-screen + physical)
 * - Campfire state management (data-fire-state attribute)
 * - Letter blank rendering and reveal
 * - Daily mode: server-validated per guess (POST /api/games/shkoda/guess)
 * - Practice/streak: client-validated (word decoded from base64)
 * - Game completion (POST /api/games/shkoda/complete)
 * - Reveal screen with teaching data
 * - Share text generation (clipboard)
 * - localStorage for anonymous stats + daily completion tracking
 */
(function () {
  'use strict';

  // ── Constants (shared from games-common.js) ──
  var g = MinooGames.create({ statsKey: 'shkoda-stats', apiBase: '/api/games/shkoda', cssPrefix: 'shkoda' });
  var KEYBOARD_ROWS = MinooGames.KEYBOARD_ROWS;
  var VALID_LETTERS = MinooGames.VALID_LETTERS;

  // ── DOM refs ──
  var game = document.getElementById('shkoda-game');
  if (!game) return;

  var fireEl = document.getElementById('shkoda-fire');
  var remainingEl = document.getElementById('shkoda-remaining');
  var clueWordEl = document.getElementById('shkoda-clue-word');
  var clueDetailEl = document.getElementById('shkoda-clue-detail');
  var blanksEl = document.getElementById('shkoda-blanks');
  var wrongLettersEl = document.getElementById('shkoda-wrong-letters');
  var keyboardEl = document.getElementById('shkoda-keyboard');
  var revealEl = document.getElementById('shkoda-reveal');
  var revealMessageEl = document.getElementById('shkoda-reveal-message');
  var revealWordEl = document.getElementById('shkoda-reveal-word');
  var teachingEl = document.getElementById('shkoda-teaching');
  var statsEl = document.getElementById('shkoda-stats');
  var actionsEl = document.getElementById('shkoda-actions');
  var loadingEl = document.getElementById('shkoda-loading');
  var clueEl = document.getElementById('shkoda-clue');
  var wrongEl = document.getElementById('shkoda-wrong');

  // ── State ──
  var state = {
    mode: 'daily',
    direction: 'english_to_ojibwe',
    sessionToken: null,
    maxWrong: 7,
    guesses: [],
    wrongGuesses: [],
    revealedPositions: [],
    wordLength: 0,
    word: null,       // only set for practice/streak (decoded from base64)
    gameOver: false,
    challengeData: null
  };

  // ── Helpers (delegated to games-common.js) ──
  var todayKey = g.todayKey;
  var loadStats = g.loadStats;
  var saveStats = g.saveStats;
  var api = g.api;
  var apiGet = g.apiGet;
  var announce = g.announce;

  // ── Rendering ──
  function showLoading(show) {
    if (loadingEl) loadingEl.hidden = !show;
  }

  function showGameUI(show) {
    [clueEl, blanksEl, wrongEl, keyboardEl, fireEl].forEach(function (el) {
      if (el) el.style.display = show ? '' : 'none';
    });
  }

  function renderBlanks() {
    blanksEl.innerHTML = '';
    for (var i = 0; i < state.wordLength; i++) {
      var cell = document.createElement('span');
      cell.className = 'shkoda__blank';
      cell.dataset.index = i;
      if (state.revealedPositions.indexOf(i) !== -1) {
        cell.textContent = state.word ? state.word[i] : '';
        cell.classList.add('shkoda__blank--revealed');
      }
      blanksEl.appendChild(cell);
    }
  }

  /** Auto-reveal punctuation, symbols, hyphens, apostrophes — like free spaces in Wheel of Fortune. */
  function autoRevealFreeCharacters(freePositions) {
    if (!freePositions || freePositions.length === 0) return;
    freePositions.forEach(function (pos) {
      if (state.revealedPositions.indexOf(pos.index) === -1) {
        state.revealedPositions.push(pos.index);
      }
      var cell = blanksEl.querySelector('[data-index="' + pos.index + '"]');
      if (cell) {
        cell.textContent = pos.char;
        cell.classList.add('shkoda__blank--revealed');
        cell.classList.add('shkoda__blank--free');
      }
    });
  }

  function revealLetterPositions(letter, positions) {
    positions.forEach(function (pos) {
      if (state.revealedPositions.indexOf(pos) === -1) {
        state.revealedPositions.push(pos);
      }
      var cell = blanksEl.querySelector('[data-index="' + pos + '"]');
      if (cell) {
        cell.textContent = letter;
        cell.classList.add('shkoda__blank--revealed');
      }
    });
  }

  function renderWrongGuesses() {
    wrongLettersEl.innerHTML = '';
    state.wrongGuesses.forEach(function (letter) {
      var badge = document.createElement('span');
      badge.className = 'shkoda__wrong-letter';
      badge.textContent = letter;
      wrongLettersEl.appendChild(badge);
    });
  }

  function updateFire(wasWrong) {
    var remaining = state.maxWrong - state.wrongGuesses.length;
    if (remaining < 0) remaining = 0;
    fireEl.dataset.fireState = remaining;
    remainingEl.textContent = remaining + ' guess' + (remaining !== 1 ? 'es' : '') + ' remaining';

    // Shake fire on wrong guess
    if (wasWrong) {
      fireEl.classList.remove('shkoda__fire--shake');
      // Force reflow to restart animation
      void fireEl.offsetWidth;
      fireEl.classList.add('shkoda__fire--shake');
    }
  }

  function renderKeyboard() {
    keyboardEl.innerHTML = '';
    KEYBOARD_ROWS.forEach(function (row) {
      var rowDiv = document.createElement('div');
      rowDiv.className = 'shkoda__kb-row';
      row.forEach(function (letter) {
        var btn = document.createElement('button');
        btn.className = 'shkoda__key';
        btn.dataset.letter = letter.toLowerCase();
        btn.textContent = letter;
        btn.type = 'button';
        btn.setAttribute('aria-label', letter === '\u02BC' ? 'glottal stop' : letter);

        var lower = letter.toLowerCase();
        if (state.wrongGuesses.indexOf(lower) !== -1) {
          btn.classList.add('shkoda__key--wrong');
          btn.disabled = true;
        } else if (state.guesses.indexOf(lower) !== -1) {
          btn.classList.add('shkoda__key--correct');
          btn.disabled = true;
        }

        btn.addEventListener('click', function () {
          handleGuess(lower);
        });
        rowDiv.appendChild(btn);
      });
      keyboardEl.appendChild(rowDiv);
    });
  }

  // ── Game logic ──
  function handleGuess(letter) {
    if (state.gameOver) return;
    if (state.guesses.indexOf(letter) !== -1) return;
    if (state.wrongGuesses.indexOf(letter) !== -1) return;

    if (state.mode === 'daily') {
      handleServerGuess(letter);
    } else {
      handleClientGuess(letter);
    }
  }

  function handleServerGuess(letter) {
    api('/guess', {
      session_token: state.sessionToken,
      letter: letter
    }).then(function (data) {
      if (data.error) return;
      state.guesses.push(letter);

      if (data.correct) {
        revealLetterPositions(letter, data.positions);
        var remaining = state.maxWrong - state.wrongGuesses.length;
        announce('Correct! ' + letter.toUpperCase() + ' is in the word. ' + remaining + ' guesses remaining.');
      } else {
        state.wrongGuesses.push(letter);
        var remaining2 = state.maxWrong - state.wrongGuesses.length;
        announce('Wrong. ' + letter.toUpperCase() + ' is not in the word. ' + remaining2 + ' guesses remaining.');
      }

      updateFire(!data.correct);
      renderKeyboard();
      renderWrongGuesses();

      if (data.status === 'won') {
        endGame(true, data);
      } else if (data.status === 'lost') {
        endGame(false, data);
      }
    }).catch(function () {
      // Network error — allow retry
    });
  }

  function handleClientGuess(letter) {
    if (!state.word) return;

    state.guesses.push(letter);
    var word = state.word.toLowerCase();
    var positions = [];
    for (var i = 0; i < word.length; i++) {
      if (word[i] === letter) {
        positions.push(i);
      }
    }

    var isWrong = positions.length === 0;
    if (!isWrong) {
      revealLetterPositions(letter, positions);
      var rem = state.maxWrong - state.wrongGuesses.length;
      announce('Correct! ' + letter.toUpperCase() + ' is in the word. ' + rem + ' guesses remaining.');
    } else {
      state.wrongGuesses.push(letter);
      var rem2 = state.maxWrong - state.wrongGuesses.length;
      announce('Wrong. ' + letter.toUpperCase() + ' is not in the word. ' + rem2 + ' guesses remaining.');
    }

    updateFire(isWrong);
    renderKeyboard();
    renderWrongGuesses();

    // Check win
    var allRevealed = true;
    for (var j = 0; j < state.wordLength; j++) {
      if (state.revealedPositions.indexOf(j) === -1) {
        allRevealed = false;
        break;
      }
    }

    if (allRevealed) {
      completeGame('won');
    } else if (state.wrongGuesses.length >= state.maxWrong) {
      completeGame('lost');
    }
  }

  function completeGame(status) {
    api('/complete', {
      session_token: state.sessionToken,
      status: status,
      guesses: state.guesses.concat(state.wrongGuesses)
    }).then(function (data) {
      endGame(status === 'won', data);
    }).catch(function () {
      // Still show reveal locally
      endGame(status === 'won', {});
    });
  }

  function endGame(won, data) {
    state.gameOver = true;
    data = data || {};
    var word = data.word || (state.word ? state.word : '');
    announce(won
      ? 'You won! The word was ' + word + '.'
      : 'Game over. The word was ' + word + '.');

    // Update stats
    var stats = loadStats();
    stats.games_played++;
    if (won) {
      stats.wins++;
      stats.current_streak++;
      if (stats.current_streak > stats.best_streak) {
        stats.best_streak = stats.current_streak;
      }
    } else {
      stats.current_streak = 0;
    }
    saveStats(stats);

    // Mark daily as completed
    if (state.mode === 'daily') {
      try { localStorage.setItem(todayKey(), 'done'); } catch (_) { /* quota */ }
    }

    // Reveal all blanks if lost
    if (!won && data.word) {
      for (var i = 0; i < data.word.length; i++) {
        var cell = blanksEl.querySelector('[data-index="' + i + '"]');
        if (cell && !cell.classList.contains('shkoda__blank--revealed')) {
          cell.textContent = data.word[i];
        }
      }
    }

    // Show reveal screen
    showReveal(won, data, stats);

    // Trigger animation — override fire state for win/loss
    if (won) {
      fireEl.dataset.fireState = 'win';
      spawnSparks();
    } else {
      fireEl.dataset.fireState = '0';
      spawnSmoke();
    }
  }

  function showReveal(won, data, stats) {
    revealEl.hidden = false;
    keyboardEl.style.display = 'none';

    revealMessageEl.textContent = won ? 'Miigwech! You got it!' : 'The fire has gone out.';
    revealMessageEl.style.color = won ? 'var(--shkoda-correct)' : 'var(--shkoda-wrong)';

    var word = data.word || (state.word ? state.word : '');
    revealWordEl.textContent = word;

    // Teaching info
    var teachingHtml = '';
    if (data.definition) {
      teachingHtml += '<p><strong>Definition:</strong> ' + escapeHtml(data.definition) + '</p>';
    }
    if (data.stem) {
      teachingHtml += '<p><strong>Stem:</strong> ' + escapeHtml(data.stem) + '</p>';
    }
    if (data.example_sentence) {
      teachingHtml += '<p><strong>Example:</strong> ' + escapeHtml(data.example_sentence) + '</p>';
    }
    if (data.slug) {
      teachingHtml += '<p><a href="/language/' + encodeURIComponent(data.slug) + '">View full entry</a></p>';
    }
    teachingEl.innerHTML = teachingHtml;

    // Stats
    statsEl.innerHTML = renderStatsHtml(stats);

    // Actions
    actionsEl.innerHTML = '';

    var shareBtn = document.createElement('button');
    shareBtn.className = 'game-btn';
    shareBtn.textContent = 'Share';
    shareBtn.addEventListener('click', function () { shareResult(won, shareBtn); });
    actionsEl.appendChild(shareBtn);

    if (state.mode === 'practice' || (state.mode === 'streak' && won)) {
      var nextBtn = document.createElement('button');
      nextBtn.className = 'game-btn game-btn--primary';
      nextBtn.textContent = 'Next Word';
      nextBtn.addEventListener('click', function () { startGame(); });
      actionsEl.appendChild(nextBtn);
    } else if (state.mode === 'streak' && !won) {
      var replayBtn = document.createElement('button');
      replayBtn.className = 'game-btn game-btn--primary';
      replayBtn.textContent = 'Streak Over \u2014 Play Again';
      replayBtn.addEventListener('click', function () { startGame(); });
      actionsEl.appendChild(replayBtn);
    }
  }

  var renderStatsHtml = g.renderStatsHtml;
  var escapeHtml = g.escapeHtml;

  function shareResult(won, btn) {
    // Build guess-by-guess emoji sequence per spec
    var allGuesses = state.guesses.concat(state.wrongGuesses);
    var emojis = '';
    var guessOrder = [];
    // Reconstruct guess order from state (guesses were pushed in order)
    // We need the original order — interleaved correct + wrong
    // Use the combined list stored in completeGame
    allGuesses.forEach(function (letter) {
      emojis += state.guesses.indexOf(letter) !== -1 ? '\uD83D\uDD25' : '\uD83E\uDEA8';
    });
    var total = allGuesses.length;
    var outcome = won ? 'fire still burning' : 'fire went out';
    var dirLabel = state.direction === 'english_to_ojibwe' ? 'English \u2192 Ojibwe' : 'Ojibwe \u2192 English';
    var date = state.mode === 'daily' ? new Date().toISOString().slice(0, 10) : 'Practice';
    var text = '\uD83D\uDD25 Shkoda \u2014 ' + (state.mode === 'daily' ? 'Daily Challenge' : state.mode.charAt(0).toUpperCase() + state.mode.slice(1)) +
      '\n' + date + ' \u00B7 ' + dirLabel +
      '\n' + emojis +
      '\n' + total + ' guesses \u00B7 ' + outcome +
      '\nhttps://minoo.live/games/shkoda';

    if (navigator.share) {
      navigator.share({ text: text }).catch(function () { /* cancelled */ });
    } else if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(text).then(function () {
        var orig = btn.textContent;
        btn.textContent = 'Copied!';
        setTimeout(function () { btn.textContent = orig; }, 1500);
      });
    }
  }

  // ── Animations ──
  function spawnSparks() {
    var rect = fireEl.getBoundingClientRect();
    for (var i = 0; i < 8; i++) {
      var spark = document.createElement('div');
      spark.className = 'shkoda__spark';
      spark.style.left = (rect.left + rect.width / 2 + (Math.random() - 0.5) * 40) + 'px';
      spark.style.top = (rect.top + 20) + 'px';
      spark.style.position = 'fixed';
      document.body.appendChild(spark);
      setTimeout(function (el) { el.remove(); }, 1000, spark);
    }
  }

  function spawnSmoke() {
    var rect = fireEl.getBoundingClientRect();
    for (var i = 0; i < 4; i++) {
      var puff = document.createElement('div');
      puff.className = 'shkoda__smoke';
      puff.style.left = (rect.left + rect.width / 2 + (Math.random() - 0.5) * 20) + 'px';
      puff.style.top = (rect.top + 30) + 'px';
      puff.style.position = 'fixed';
      puff.style.animationDelay = (i * 0.2) + 's';
      document.body.appendChild(puff);
      setTimeout(function (el) { el.remove(); }, 2000, puff);
    }
  }

  // ── Tabs and direction ──
  function initTabs() {
    var tabs = game.querySelectorAll('.shkoda__tab');
    tabs.forEach(function (tab) {
      tab.addEventListener('click', function () {
        tabs.forEach(function (t) {
          t.classList.remove('shkoda__tab--active');
          t.setAttribute('aria-selected', 'false');
        });
        tab.classList.add('shkoda__tab--active');
        tab.setAttribute('aria-selected', 'true');
        state.mode = tab.dataset.mode;
        startGame();
      });
    });
  }

  function initDirection() {
    var swapBtn = document.getElementById('shkoda-dir-swap');
    var fromLabel = document.getElementById('shkoda-dir-from');
    var toLabel = document.getElementById('shkoda-dir-to');
    if (!swapBtn) return;

    function updateLabels() {
      if (state.direction === 'english_to_ojibwe') {
        fromLabel.textContent = 'English';
        toLabel.textContent = 'Ojibwe';
        fromLabel.classList.add('shkoda__dir-label--active');
        toLabel.classList.remove('shkoda__dir-label--active');
      } else {
        fromLabel.textContent = 'Ojibwe';
        toLabel.textContent = 'English';
        fromLabel.classList.add('shkoda__dir-label--active');
        toLabel.classList.remove('shkoda__dir-label--active');
      }
    }

    swapBtn.addEventListener('click', function () {
      state.direction = state.direction === 'english_to_ojibwe'
        ? 'ojibwe_to_english'
        : 'english_to_ojibwe';
      updateLabels();
      startGame();
    });

    updateLabels();
  }

  // ── Physical keyboard ──
  function initPhysicalKeyboard() {
    document.addEventListener('keydown', function (e) {
      if (state.gameOver) return;
      if (e.ctrlKey || e.metaKey || e.altKey) return;
      // Don't capture when focus is outside the game
      if (!game.contains(document.activeElement) && document.activeElement !== document.body) return;

      var key = e.key.toLowerCase();
      // Map apostrophe to glottal stop
      if (key === "'") key = '\u02BC';

      if (VALID_LETTERS.has(key)) {
        e.preventDefault();
        handleGuess(key);
      }
    });
  }

  // ── Start game ──
  function startGame() {
    // Reset state
    state.sessionToken = null;
    state.guesses = [];
    state.wrongGuesses = [];
    state.revealedPositions = [];
    state.word = null;
    state.wordLength = 0;
    state.gameOver = false;
    state.challengeData = null;

    // Reset UI
    revealEl.hidden = true;
    showGameUI(false);
    showLoading(true);
    keyboardEl.style.display = '';

    // Check daily dedup
    if (state.mode === 'daily') {
      try {
        if (localStorage.getItem(todayKey())) {
          showLoading(false);
          revealEl.hidden = false;
          revealMessageEl.textContent = 'You already played today\'s Shkoda!';
          revealMessageEl.style.color = 'var(--text-secondary)';
          revealWordEl.textContent = '';
          teachingEl.innerHTML = '<p>Come back tomorrow for a new word.</p>';
          var stats = loadStats();
          statsEl.innerHTML = renderStatsHtml(stats);
          actionsEl.innerHTML = '';
          return;
        }
      } catch (_) { /* no localStorage */ }
    }

    // Fetch word data
    var endpoint = state.mode === 'daily'
      ? '/daily'
      : '/word?mode=' + encodeURIComponent(state.mode) +
        '&direction=' + encodeURIComponent(state.direction);

    apiGet(endpoint).then(function (data) {
      if (data.error) {
        showLoading(false);
        loadingEl.hidden = false;
        loadingEl.textContent = data.error;
        return;
      }

      state.sessionToken = data.session_token;
      state.wordLength = data.word_length;
      state.maxWrong = data.max_wrong || 7;
      state.challengeData = data;

      // For practice/streak, decode the word from base64
      if (state.mode !== 'daily' && data.word_data) {
        try {
          state.word = atob(data.word_data);
        } catch (_) {
          state.word = null;
        }
      }

      // Update clue
      var clueLabel = game.querySelector('.shkoda__clue-label');
      if (state.direction === 'english_to_ojibwe') {
        if (clueLabel) clueLabel.textContent = 'Guess the Ojibwe word for:';
      } else {
        if (clueLabel) clueLabel.textContent = 'Uncover the Ojibwe word:';
      }
      clueWordEl.textContent = data.clue || '';
      clueDetailEl.textContent = data.clue_detail || '';

      // Show game UI
      showLoading(false);
      showGameUI(true);
      updateFire();
      renderBlanks();

      // Auto-reveal non-letter characters (punctuation, hyphens, apostrophes)
      var freePositions = [];
      if (data.free_positions) {
        // Server-provided (daily mode)
        freePositions = data.free_positions;
      } else if (state.word) {
        // Client-derived (practice/streak)
        for (var fi = 0; fi < state.word.length; fi++) {
          var ch = state.word[fi];
          if (!VALID_LETTERS.has(ch.toLowerCase())) {
            freePositions.push({ index: fi, char: ch });
          }
        }
      }
      autoRevealFreeCharacters(freePositions);
      renderKeyboard();
      renderWrongGuesses();
    }).catch(function () {
      showLoading(false);
      if (loadingEl) {
        loadingEl.hidden = false;
        loadingEl.textContent = 'Could not load word. Please try again.';
      }
    });
  }

  // ── Init ──
  initTabs();
  initDirection();
  initPhysicalKeyboard();
  startGame();
})();
