/**
 * Ishkode — Ojibwe Word Game
 *
 * Client-side game engine. Handles:
 * - Mode switching (daily/practice/streak)
 * - Direction toggle (english_to_ojibwe / ojibwe_to_english)
 * - Keyboard rendering and input (on-screen + physical)
 * - Campfire state management (data-fire-state attribute)
 * - Letter blank rendering and reveal
 * - Daily mode: server-validated per guess (POST /api/games/ishkode/guess)
 * - Practice/streak: client-validated (word decoded from base64)
 * - Game completion (POST /api/games/ishkode/complete)
 * - Reveal screen with teaching data
 * - Share text generation (clipboard)
 * - localStorage for anonymous stats + daily completion tracking
 */
(function () {
  'use strict';

  // ── Constants ──
  var KEYBOARD_ROWS = [
    ['A', 'B', 'C', 'D', 'E', 'G', 'H', 'I', 'J'],
    ['K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R'],
    ['S', 'T', 'W', 'Y', 'Z', '\u02BC'] // ʼ = glottal stop
  ];
  var VALID_LETTERS = new Set(KEYBOARD_ROWS.flat().map(function (k) { return k.toLowerCase(); }));
  var STATS_KEY = 'ishkode-stats';

  // ── DOM refs ──
  var game = document.getElementById('ishkode-game');
  if (!game) return;

  var apiBase = game.dataset.apiBase || '/api/games/ishkode';
  var fireEl = document.getElementById('ishkode-fire');
  var remainingEl = document.getElementById('ishkode-remaining');
  var clueWordEl = document.getElementById('ishkode-clue-word');
  var clueDetailEl = document.getElementById('ishkode-clue-detail');
  var blanksEl = document.getElementById('ishkode-blanks');
  var wrongLettersEl = document.getElementById('ishkode-wrong-letters');
  var keyboardEl = document.getElementById('ishkode-keyboard');
  var revealEl = document.getElementById('ishkode-reveal');
  var revealMessageEl = document.getElementById('ishkode-reveal-message');
  var revealWordEl = document.getElementById('ishkode-reveal-word');
  var teachingEl = document.getElementById('ishkode-teaching');
  var statsEl = document.getElementById('ishkode-stats');
  var actionsEl = document.getElementById('ishkode-actions');
  var loadingEl = document.getElementById('ishkode-loading');
  var clueEl = document.getElementById('ishkode-clue');
  var wrongEl = document.getElementById('ishkode-wrong');

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

  // ── Helpers ──
  function todayKey() {
    return 'ishkode-daily-' + new Date().toISOString().slice(0, 10);
  }

  function loadStats() {
    try {
      return JSON.parse(localStorage.getItem(STATS_KEY)) || {
        games_played: 0, wins: 0, current_streak: 0, best_streak: 0
      };
    } catch (_) {
      return { games_played: 0, wins: 0, current_streak: 0, best_streak: 0 };
    }
  }

  function saveStats(s) {
    try { localStorage.setItem(STATS_KEY, JSON.stringify(s)); } catch (_) { /* quota */ }
  }

  function api(path, body) {
    return fetch(apiBase + path, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(body)
    }).then(function (r) {
      if (!r.ok) throw new Error('API error: ' + r.status);
      return r.json();
    });
  }

  function apiGet(path) {
    return fetch(apiBase + path).then(function (r) {
      if (!r.ok) throw new Error('API error: ' + r.status);
      return r.json();
    });
  }

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
      cell.className = 'ishkode__blank';
      cell.dataset.index = i;
      if (state.revealedPositions.indexOf(i) !== -1) {
        cell.textContent = state.word ? state.word[i] : '';
        cell.classList.add('ishkode__blank--revealed');
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
        cell.classList.add('ishkode__blank--revealed');
        cell.classList.add('ishkode__blank--free');
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
        cell.classList.add('ishkode__blank--revealed');
      }
    });
  }

  function renderWrongGuesses() {
    wrongLettersEl.innerHTML = '';
    state.wrongGuesses.forEach(function (letter) {
      var badge = document.createElement('span');
      badge.className = 'ishkode__wrong-letter';
      badge.textContent = letter;
      wrongLettersEl.appendChild(badge);
    });
  }

  function updateFire() {
    var remaining = state.maxWrong - state.wrongGuesses.length;
    if (remaining < 0) remaining = 0;
    fireEl.dataset.fireState = remaining;
    remainingEl.textContent = remaining + ' guess' + (remaining !== 1 ? 'es' : '') + ' remaining';
  }

  function renderKeyboard() {
    keyboardEl.innerHTML = '';
    KEYBOARD_ROWS.forEach(function (row) {
      var rowDiv = document.createElement('div');
      rowDiv.className = 'ishkode__kb-row';
      row.forEach(function (letter) {
        var btn = document.createElement('button');
        btn.className = 'ishkode__key';
        btn.dataset.letter = letter.toLowerCase();
        btn.textContent = letter;
        btn.type = 'button';
        btn.setAttribute('aria-label', letter === '\u02BC' ? 'glottal stop' : letter);

        var lower = letter.toLowerCase();
        if (state.wrongGuesses.indexOf(lower) !== -1) {
          btn.classList.add('ishkode__key--wrong');
          btn.disabled = true;
        } else if (state.guesses.indexOf(lower) !== -1) {
          btn.classList.add('ishkode__key--correct');
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
      } else {
        state.wrongGuesses.push(letter);
      }

      updateFire();
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

    if (positions.length > 0) {
      revealLetterPositions(letter, positions);
    } else {
      state.wrongGuesses.push(letter);
    }

    updateFire();
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
        if (cell && !cell.classList.contains('ishkode__blank--revealed')) {
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
    revealMessageEl.style.color = won ? 'var(--ishkode-correct)' : 'var(--ishkode-wrong)';

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
    shareBtn.className = 'ishkode__btn';
    shareBtn.textContent = 'Share';
    shareBtn.addEventListener('click', function () { shareResult(won, shareBtn); });
    actionsEl.appendChild(shareBtn);

    if (state.mode === 'practice' || (state.mode === 'streak' && won)) {
      var nextBtn = document.createElement('button');
      nextBtn.className = 'ishkode__btn ishkode__btn--primary';
      nextBtn.textContent = 'Next Word';
      nextBtn.addEventListener('click', function () { startGame(); });
      actionsEl.appendChild(nextBtn);
    } else if (state.mode === 'streak' && !won) {
      var replayBtn = document.createElement('button');
      replayBtn.className = 'ishkode__btn ishkode__btn--primary';
      replayBtn.textContent = 'Streak Over \u2014 Play Again';
      replayBtn.addEventListener('click', function () { startGame(); });
      actionsEl.appendChild(replayBtn);
    }
  }

  function renderStatsHtml(stats) {
    return '<div class="ishkode__stat"><div class="ishkode__stat-value">' + stats.games_played + '</div><div class="ishkode__stat-label">Played</div></div>' +
      '<div class="ishkode__stat"><div class="ishkode__stat-value">' + stats.wins + '</div><div class="ishkode__stat-label">Won</div></div>' +
      '<div class="ishkode__stat"><div class="ishkode__stat-value">' + stats.current_streak + '</div><div class="ishkode__stat-label">Streak</div></div>' +
      '<div class="ishkode__stat"><div class="ishkode__stat-value">' + stats.best_streak + '</div><div class="ishkode__stat-label">Best</div></div>';
  }

  function escapeHtml(str) {
    var div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
  }

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
    var text = '\uD83D\uDD25 Ishkode \u2014 ' + (state.mode === 'daily' ? 'Daily Challenge' : state.mode.charAt(0).toUpperCase() + state.mode.slice(1)) +
      '\n' + date + ' \u00B7 ' + dirLabel +
      '\n' + emojis +
      '\n' + total + ' guesses \u00B7 ' + outcome +
      '\nhttps://minoo.live/games/ishkode';

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
      spark.className = 'ishkode__spark';
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
      puff.className = 'ishkode__smoke';
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
    var tabs = game.querySelectorAll('.ishkode__tab');
    tabs.forEach(function (tab) {
      tab.addEventListener('click', function () {
        tabs.forEach(function (t) {
          t.classList.remove('ishkode__tab--active');
          t.setAttribute('aria-selected', 'false');
        });
        tab.classList.add('ishkode__tab--active');
        tab.setAttribute('aria-selected', 'true');
        state.mode = tab.dataset.mode;
        startGame();
      });
    });
  }

  function initDirection() {
    var swapBtn = document.getElementById('ishkode-dir-swap');
    var fromLabel = document.getElementById('ishkode-dir-from');
    var toLabel = document.getElementById('ishkode-dir-to');
    if (!swapBtn) return;

    function updateLabels() {
      if (state.direction === 'english_to_ojibwe') {
        fromLabel.textContent = 'English';
        toLabel.textContent = 'Ojibwe';
        fromLabel.classList.add('ishkode__dir-label--active');
        toLabel.classList.remove('ishkode__dir-label--active');
      } else {
        fromLabel.textContent = 'Ojibwe';
        toLabel.textContent = 'English';
        fromLabel.classList.add('ishkode__dir-label--active');
        toLabel.classList.remove('ishkode__dir-label--active');
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
          revealMessageEl.textContent = 'You already played today\'s Ishkode!';
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
      var clueLabel = game.querySelector('.ishkode__clue-label');
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
