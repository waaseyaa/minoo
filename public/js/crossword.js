/**
 * Crossword — Ojibwe Crossword Game
 *
 * Client-side game engine. Handles:
 * - Mode switching (daily/practice/themes)
 * - Grid rendering and cell selection
 * - Keyboard input (on-screen + physical)
 * - Word checking via server API (POST /api/games/crossword/check)
 * - Hint system (POST /api/games/crossword/hint)
 * - Game completion (POST /api/games/crossword/complete)
 * - Teaching data display on completion
 * - Share text generation (clipboard)
 * - localStorage for anonymous stats + daily completion tracking
 * - Theme browser with progress bars
 */
(function () {
  'use strict';

  // ── Constants (shared from games-common.js) ──
  var g = MinooGames.create({ statsKey: 'crossword-stats', apiBase: '/api/games/crossword', cssPrefix: 'crossword' });
  var KEYBOARD_ROWS = MinooGames.KEYBOARD_ROWS;
  var VALID_LETTERS = MinooGames.VALID_LETTERS;

  // ── DOM refs ──
  var root = document.querySelector('[data-game="crossword"]');
  if (!root) return;

  var tabsEl = root.querySelector('.crossword__tabs');
  var difficultyEl = root.querySelector('.crossword__difficulty');
  var themesEl = root.querySelector('.crossword__themes');
  var themesListEl = root.querySelector('.crossword__themes-list');
  var gameEl = root.querySelector('.crossword__game');
  var gridEl = root.querySelector('.crossword__grid');
  var acrossCluesEl = root.querySelector('.crossword__clues-list[data-direction="across"]');
  var downCluesEl = root.querySelector('.crossword__clues-list[data-direction="down"]');
  var wordBankListEl = root.querySelector('.crossword__word-bank-list');
  var difficultyBadgeEl = root.querySelector('.crossword__difficulty-badge');
  var activeClueEl = root.querySelector('.crossword__active-clue');
  var keyboardEl = root.querySelector('.crossword__keyboard');
  var completeEl = root.querySelector('.crossword__complete');
  var completeTitleEl = root.querySelector('.crossword__complete-title');
  var completeStatsEl = root.querySelector('.crossword__complete-stats');
  var completeTeachingsEl = root.querySelector('.crossword__complete-teachings');
  var completeActionsEl = root.querySelector('.crossword__complete-actions');
  var loadingEl = root.querySelector('.crossword__loading');

  // ── State ──
  var state = {
    mode: 'daily',
    tier: 'easy',
    themeSlug: null,
    sessionToken: null,
    puzzleId: null,
    gridSize: 0,
    placements: [],
    clues: {},
    wordBank: null,
    grid: [],
    selectedCell: null,
    selectedDirection: 'across',
    selectedWordIndex: null,
    completedWords: new Set(),
    hintsUsed: 0,
    maxHints: 0,
    startTime: null,
    gameOver: false
  };

  // ── Helpers (delegated to games-common.js) ──
  var todayKey = g.todayKey;
  var loadStats = g.loadStats;
  var saveStats = g.saveStats;
  var api = g.api;
  var apiGet = g.apiGet;
  var escapeHtml = g.escapeHtml;

  function themeKey(slug) {
    return 'crossword-theme-' + slug;
  }

  function formatTime(ms) {
    var seconds = Math.floor(ms / 1000);
    var m = Math.floor(seconds / 60);
    var s = seconds % 60;
    return m + ':' + (s < 10 ? '0' : '') + s;
  }

  /** Return {row, col} for position `i` within a placement. */
  function cellCoord(placement, i) {
    return {
      row: placement.direction === 'across' ? placement.row : placement.row + i,
      col: placement.direction === 'across' ? placement.col + i : placement.col
    };
  }

  function getCellEl(row, col) {
    return gridEl.querySelector('[data-row="' + row + '"][data-col="' + col + '"]');
  }

  // ── Loading / visibility ──
  function showLoading(show) {
    if (loadingEl) loadingEl.hidden = !show;
  }

  function showGame(show) {
    if (gameEl) gameEl.hidden = !show;
  }

  function showComplete(show) {
    if (completeEl) completeEl.hidden = !show;
  }

  // ── Grid building ──
  function buildGrid(size, placements) {
    // Initialize empty grid
    var grid = [];
    for (var r = 0; r < size; r++) {
      grid[r] = [];
      for (var c = 0; c < size; c++) {
        grid[r][c] = { letter: null, wordIndices: [], isBlack: true, number: null, userLetter: '' };
      }
    }

    // Mark cells used by words
    placements.forEach(function (placement, idx) {
      for (var i = 0; i < placement.length; i++) {
        var pos = cellCoord(placement, i);
        if (pos.row < size && pos.col < size) {
          grid[pos.row][pos.col].isBlack = false;
          grid[pos.row][pos.col].wordIndices.push(idx);
          if (placement.letter_at && placement.letter_at[i]) {
            grid[pos.row][pos.col].letter = placement.letter_at[i];
          }
        }
      }
    });

    // Assign clue numbers
    var num = 1;
    for (var r2 = 0; r2 < size; r2++) {
      for (var c2 = 0; c2 < size; c2++) {
        if (grid[r2][c2].isBlack) continue;
        var needsNumber = false;
        // Check if this cell starts any word
        for (var wi = 0; wi < grid[r2][c2].wordIndices.length; wi++) {
          var p = placements[grid[r2][c2].wordIndices[wi]];
          if (p.row === r2 && p.col === c2) {
            needsNumber = true;
            break;
          }
        }
        if (needsNumber) {
          grid[r2][c2].number = num++;
        }
      }
    }

    return grid;
  }

  // ── Render grid ──
  function renderGrid() {
    gridEl.innerHTML = '';
    gridEl.style.gridTemplateColumns = 'repeat(' + state.gridSize + ', 1fr)';
    gridEl.style.gridTemplateRows = 'repeat(' + state.gridSize + ', 1fr)';

    for (var r = 0; r < state.gridSize; r++) {
      for (var c = 0; c < state.gridSize; c++) {
        var cellData = state.grid[r][c];
        var cell = document.createElement('div');
        cell.className = 'crossword__cell';
        cell.dataset.row = r;
        cell.dataset.col = c;

        if (cellData.isBlack) {
          cell.classList.add('crossword__cell--black');
        } else {
          // Number label
          if (cellData.number !== null) {
            var numSpan = document.createElement('span');
            numSpan.className = 'crossword__cell-number';
            numSpan.textContent = cellData.number;
            cell.appendChild(numSpan);
          }

          // Letter display
          var letterSpan = document.createElement('span');
          letterSpan.className = 'crossword__cell-letter';
          letterSpan.textContent = cellData.userLetter || '';
          cell.appendChild(letterSpan);

          cell.setAttribute('tabindex', '0');
          cell.setAttribute('role', 'gridcell');
          cell.setAttribute('aria-label', 'Row ' + (r + 1) + ', Column ' + (c + 1));

          cell.addEventListener('click', handleCellClick.bind(null, r, c));
        }

        gridEl.appendChild(cell);
      }
    }
  }

  // ── Clue rendering ──
  function renderClues() {
    acrossCluesEl.innerHTML = '';
    downCluesEl.innerHTML = '';

    state.placements.forEach(function (placement, idx) {
      var cell = state.grid[placement.row][placement.col];
      var num = cell.number;
      if (num === null) return;

      var clueObj = state.clues[idx] || {};
      var clueText = typeof clueObj === 'string' ? clueObj : (clueObj.text || '');
      var li = document.createElement('div');
      li.className = 'crossword__clue';
      li.dataset.wordIndex = idx;
      li.innerHTML = '<strong>' + num + '.</strong> ' + escapeHtml(clueText);

      li.addEventListener('click', function () {
        selectWord(idx);
      });

      if (placement.direction === 'across') {
        acrossCluesEl.appendChild(li);
      } else {
        downCluesEl.appendChild(li);
      }
    });
  }

  // ── Word bank rendering ──
  function renderWordBank() {
    wordBankListEl.innerHTML = '';
    if (!state.wordBank) return;

    state.wordBank.forEach(function (item, idx) {
      var span = document.createElement('span');
      span.className = 'crossword__word-bank-item';
      span.dataset.bankIndex = idx;
      var label = typeof item === 'string' ? item : (item.word || '');
      if (typeof item === 'object' && item.meaning) {
        label += ' — ' + item.meaning;
      }
      span.textContent = label;
      if (state.completedWords.has(idx)) {
        span.classList.add('crossword__word-bank-item--found');
      }
      wordBankListEl.appendChild(span);
    });
  }

  // ── Cell selection ──
  function handleCellClick(row, col) {
    if (state.gameOver) return;
    var cellData = state.grid[row][col];
    if (cellData.isBlack) return;

    // If clicking same cell, toggle direction at intersections
    if (state.selectedCell && state.selectedCell.row === row && state.selectedCell.col === col) {
      if (cellData.wordIndices.length > 1) {
        state.selectedDirection = state.selectedDirection === 'across' ? 'down' : 'across';
      }
    } else {
      // Select the cell and pick direction based on available words
      var hasAcross = false;
      var hasDown = false;
      cellData.wordIndices.forEach(function (wi) {
        if (state.placements[wi].direction === 'across') hasAcross = true;
        if (state.placements[wi].direction === 'down') hasDown = true;
      });
      if (hasAcross && !hasDown) {
        state.selectedDirection = 'across';
      } else if (hasDown && !hasAcross) {
        state.selectedDirection = 'down';
      }
      // Otherwise keep current direction
    }

    state.selectedCell = { row: row, col: col };

    // Find the word index matching the selected direction
    state.selectedWordIndex = null;
    cellData.wordIndices.forEach(function (wi) {
      if (state.placements[wi].direction === state.selectedDirection) {
        state.selectedWordIndex = wi;
      }
    });
    // Fallback: pick first word
    if (state.selectedWordIndex === null && cellData.wordIndices.length > 0) {
      state.selectedWordIndex = cellData.wordIndices[0];
      state.selectedDirection = state.placements[state.selectedWordIndex].direction;
    }

    updateSelectionUI();
  }

  function selectWord(wordIndex) {
    if (state.gameOver) return;
    var placement = state.placements[wordIndex];
    state.selectedWordIndex = wordIndex;
    state.selectedDirection = placement.direction;
    state.selectedCell = { row: placement.row, col: placement.col };
    // Advance to first empty cell in this word
    for (var i = 0; i < placement.length; i++) {
      var pos = cellCoord(placement, i);
      if (!state.grid[pos.row][pos.col].userLetter) {
        state.selectedCell = pos;
        break;
      }
    }
    updateSelectionUI();
  }

  function updateSelectionUI() {
    // Clear all highlights
    var allCells = gridEl.querySelectorAll('.crossword__cell');
    allCells.forEach(function (el) {
      el.classList.remove('crossword__cell--active', 'crossword__cell--highlight');
    });

    // Clear clue highlights
    var allClues = root.querySelectorAll('.crossword__clue');
    allClues.forEach(function (el) {
      el.classList.remove('crossword__clue--active');
    });

    if (state.selectedWordIndex === null) {
      activeClueEl.textContent = '';
      return;
    }

    // Highlight word cells
    var placement = state.placements[state.selectedWordIndex];
    for (var i = 0; i < placement.length; i++) {
      var pos = cellCoord(placement, i);
      var el = getCellEl(pos.row, pos.col);
      if (el) {
        el.classList.add('crossword__cell--highlight');
      }
    }

    // Highlight active cell
    if (state.selectedCell) {
      var activeEl = getCellEl(state.selectedCell.row, state.selectedCell.col);
      if (activeEl) {
        activeEl.classList.add('crossword__cell--active');
        activeEl.focus();
      }
    }

    // Highlight active clue
    var clueEl = root.querySelector('.crossword__clue[data-word-index="' + state.selectedWordIndex + '"]');
    if (clueEl) {
      clueEl.classList.add('crossword__clue--active');
      clueEl.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
    }

    // Show active clue text
    var num = state.grid[placement.row][placement.col].number;
    var dir = placement.direction === 'across' ? 'A' : 'D';
    var clueObj = state.clues[state.selectedWordIndex] || {};
    var clueText = typeof clueObj === 'string' ? clueObj : (clueObj.text || '');
    activeClueEl.textContent = num + dir + ': ' + clueText;
  }

  // ── Cursor movement ──
  function advanceCursor() {
    if (!state.selectedCell || state.selectedWordIndex === null) return;
    var placement = state.placements[state.selectedWordIndex];
    var nextRow = state.selectedCell.row + (state.selectedDirection === 'down' ? 1 : 0);
    var nextCol = state.selectedCell.col + (state.selectedDirection === 'across' ? 1 : 0);

    // Stay within the word
    if (state.selectedDirection === 'across') {
      if (nextCol >= placement.col + placement.length) return;
    } else {
      if (nextRow >= placement.row + placement.length) return;
    }

    if (nextRow < state.gridSize && nextCol < state.gridSize && !state.grid[nextRow][nextCol].isBlack) {
      state.selectedCell = { row: nextRow, col: nextCol };
      updateSelectionUI();
    }
  }

  function retreatCursor() {
    if (!state.selectedCell || state.selectedWordIndex === null) return;
    var placement = state.placements[state.selectedWordIndex];
    var prevRow = state.selectedCell.row - (state.selectedDirection === 'down' ? 1 : 0);
    var prevCol = state.selectedCell.col - (state.selectedDirection === 'across' ? 1 : 0);

    // Stay within the word
    if (state.selectedDirection === 'across') {
      if (prevCol < placement.col) return;
    } else {
      if (prevRow < placement.row) return;
    }

    if (prevRow >= 0 && prevCol >= 0 && !state.grid[prevRow][prevCol].isBlack) {
      state.selectedCell = { row: prevRow, col: prevCol };
      updateSelectionUI();
    }
  }

  function selectNextWord() {
    if (state.selectedWordIndex === null) {
      if (state.placements.length > 0) selectWord(0);
      return;
    }
    var next = (state.selectedWordIndex + 1) % state.placements.length;
    // Skip completed words
    var start = next;
    do {
      if (!state.completedWords.has(next)) {
        selectWord(next);
        return;
      }
      next = (next + 1) % state.placements.length;
    } while (next !== start);
    // All complete
    selectWord(next);
  }

  function selectPrevWord() {
    if (state.selectedWordIndex === null) {
      if (state.placements.length > 0) selectWord(state.placements.length - 1);
      return;
    }
    var prev = (state.selectedWordIndex - 1 + state.placements.length) % state.placements.length;
    var start = prev;
    do {
      if (!state.completedWords.has(prev)) {
        selectWord(prev);
        return;
      }
      prev = (prev - 1 + state.placements.length) % state.placements.length;
    } while (prev !== start);
    selectWord(prev);
  }

  // ── Keyboard input ──
  function handleLetterInput(letter) {
    if (state.gameOver) return;
    if (!state.selectedCell) return;

    var r = state.selectedCell.row;
    var c = state.selectedCell.col;
    var cellData = state.grid[r][c];
    if (cellData.isBlack) return;

    // Set the letter
    cellData.userLetter = letter.toUpperCase();

    // Update DOM
    var cellEl = getCellEl(r, c);
    if (cellEl) {
      var letterSpan = cellEl.querySelector('.crossword__cell-letter');
      if (letterSpan) letterSpan.textContent = cellData.userLetter;
    }

    advanceCursor();
  }

  function handleBackspace() {
    if (state.gameOver) return;
    if (!state.selectedCell) return;

    var r = state.selectedCell.row;
    var c = state.selectedCell.col;
    var cellData = state.grid[r][c];

    if (!cellData.userLetter) {
      // Move back first when current cell is empty
      retreatCursor();
      r = state.selectedCell.row;
      c = state.selectedCell.col;
    }
    // Clear the cell
    state.grid[r][c].userLetter = '';
    var cellEl = getCellEl(r, c);
    if (cellEl) {
      var letterSpan = cellEl.querySelector('.crossword__cell-letter');
      if (letterSpan) letterSpan.textContent = '';
    }
  }

  // ── On-screen keyboard ──
  function renderKeyboard() {
    var rows = keyboardEl.querySelectorAll('.crossword__keyboard-row');

    // Row 1
    if (rows[0]) {
      rows[0].innerHTML = '';
      KEYBOARD_ROWS[0].forEach(function (letter) {
        rows[0].appendChild(createKeyButton(letter));
      });
    }

    // Row 2
    if (rows[1]) {
      rows[1].innerHTML = '';
      KEYBOARD_ROWS[1].forEach(function (letter) {
        rows[1].appendChild(createKeyButton(letter));
      });
    }

    // Row 3: glottal stop + action keys
    if (rows[2]) {
      rows[2].innerHTML = '';
      rows[2].appendChild(createKeyButton('\u02BC'));

      var hintBtn = document.createElement('button');
      hintBtn.className = 'crossword__key crossword__key--action';
      hintBtn.textContent = 'HINT';
      hintBtn.type = 'button';
      hintBtn.setAttribute('aria-label', 'Get hint');
      hintBtn.addEventListener('click', handleHint);
      rows[2].appendChild(hintBtn);

      var delBtn = document.createElement('button');
      delBtn.className = 'crossword__key crossword__key--action';
      delBtn.textContent = 'DEL';
      delBtn.type = 'button';
      delBtn.setAttribute('aria-label', 'Delete letter');
      delBtn.addEventListener('click', handleBackspace);
      rows[2].appendChild(delBtn);

      var checkBtn = document.createElement('button');
      checkBtn.className = 'crossword__key crossword__key--action crossword__key--check';
      checkBtn.innerHTML = 'CHECK &#10003;';
      checkBtn.type = 'button';
      checkBtn.setAttribute('aria-label', 'Check word');
      checkBtn.addEventListener('click', handleCheckWord);
      rows[2].appendChild(checkBtn);
    }
  }

  function createKeyButton(letter) {
    var btn = document.createElement('button');
    btn.className = 'crossword__key';
    btn.dataset.letter = letter.toLowerCase();
    btn.textContent = letter;
    btn.type = 'button';
    btn.setAttribute('aria-label', letter === '\u02BC' ? 'glottal stop' : letter);
    btn.addEventListener('click', function () {
      handleLetterInput(letter.toLowerCase());
    });
    return btn;
  }

  // ── Physical keyboard ──
  function initPhysicalKeyboard() {
    document.addEventListener('keydown', function (e) {
      if (state.gameOver) return;
      if (e.ctrlKey || e.metaKey || e.altKey) return;
      // Don't capture when focus is outside the game
      if (!root.contains(document.activeElement) && document.activeElement !== document.body) return;

      var key = e.key;

      // Arrow keys
      if (key === 'ArrowRight') {
        e.preventDefault();
        if (state.selectedDirection !== 'across') {
          state.selectedDirection = 'across';
          updateSelectionUI();
        } else {
          advanceCursor();
        }
        return;
      }
      if (key === 'ArrowLeft') {
        e.preventDefault();
        if (state.selectedDirection !== 'across') {
          state.selectedDirection = 'across';
          updateSelectionUI();
        } else {
          retreatCursor();
        }
        return;
      }
      if (key === 'ArrowDown') {
        e.preventDefault();
        if (state.selectedDirection !== 'down') {
          state.selectedDirection = 'down';
          updateSelectionUI();
        } else {
          advanceCursor();
        }
        return;
      }
      if (key === 'ArrowUp') {
        e.preventDefault();
        if (state.selectedDirection !== 'down') {
          state.selectedDirection = 'down';
          updateSelectionUI();
        } else {
          retreatCursor();
        }
        return;
      }

      // Tab / Shift+Tab = next/prev word
      if (key === 'Tab') {
        e.preventDefault();
        if (e.shiftKey) {
          selectPrevWord();
        } else {
          selectNextWord();
        }
        return;
      }

      // Backspace
      if (key === 'Backspace') {
        e.preventDefault();
        handleBackspace();
        return;
      }

      // Enter = check word
      if (key === 'Enter') {
        e.preventDefault();
        handleCheckWord();
        return;
      }

      // Letter input
      var lower = key.toLowerCase();
      if (key === "'") lower = '\u02BC';
      if (VALID_LETTERS.has(lower)) {
        e.preventDefault();
        handleLetterInput(lower);
      }
    });
  }

  // ── Word checking ──
  function handleCheckWord() {
    if (state.gameOver) return;
    if (state.selectedWordIndex === null) return;
    if (state.completedWords.has(state.selectedWordIndex)) return;

    var placement = state.placements[state.selectedWordIndex];
    var letters = [];
    var allFilled = true;

    for (var i = 0; i < placement.length; i++) {
      var pos = cellCoord(placement, i);
      var userLetter = state.grid[pos.row][pos.col].userLetter;
      if (!userLetter) {
        allFilled = false;
        break;
      }
      letters.push(userLetter.toLowerCase());
    }

    if (!allFilled) return;

    api('/check', {
      session_token: state.sessionToken,
      word_index: state.selectedWordIndex,
      letters: letters
    }).then(function (data) {
      if (data.correct) {
        markWordCorrect(state.selectedWordIndex);
        // Strike from word bank
        if (data.bank_index !== undefined && data.bank_index !== null) {
          markWordBankFound(data.bank_index);
        }
        checkAllComplete();
      } else {
        markWordWrong(state.selectedWordIndex, data.wrong_positions || []);
      }
    }).catch(function () {
      // Network error — allow retry
    });
  }

  function markWordCorrect(wordIndex) {
    state.completedWords.add(wordIndex);
    var placement = state.placements[wordIndex];

    for (var i = 0; i < placement.length; i++) {
      var pos = cellCoord(placement, i);
      var el = getCellEl(pos.row, pos.col);
      if (el) {
        el.classList.add('crossword__cell--correct');
      }
    }

    // Update clue styling
    var clueEl = root.querySelector('.crossword__clue[data-word-index="' + wordIndex + '"]');
    if (clueEl) {
      clueEl.classList.add('crossword__clue--complete');
    }

    // Move to next incomplete word
    selectNextWord();
  }

  function markWordWrong(wordIndex, wrongPositions) {
    var placement = state.placements[wordIndex];

    // Build list of positions to clear: server-specified or all non-correct
    var positions = wrongPositions.length > 0
      ? wrongPositions
      : Array.from({ length: placement.length }, function (_, i) { return i; });

    positions.forEach(function (i) {
      var coord = cellCoord(placement, i);
      var el = getCellEl(coord.row, coord.col);
      if (el && !el.classList.contains('crossword__cell--correct')) {
        state.grid[coord.row][coord.col].userLetter = '';
        el.classList.add('crossword__cell--wrong');
        var letterSpan = el.querySelector('.crossword__cell-letter');
        if (letterSpan) letterSpan.textContent = '';
        setTimeout(function (target) {
          target.classList.remove('crossword__cell--wrong');
        }, 600, el);
      }
    });
  }

  function markWordBankFound(bankIndex) {
    var item = wordBankListEl.querySelector('[data-bank-index="' + bankIndex + '"]');
    if (item) {
      item.classList.add('crossword__word-bank-item--found');
    }
  }

  // ── Hint system ──
  function handleHint() {
    if (state.gameOver) return;
    if (state.selectedWordIndex === null) return;
    if (state.completedWords.has(state.selectedWordIndex)) return;
    if (state.maxHints > 0 && state.hintsUsed >= state.maxHints) return;

    // Find first empty position in the selected word
    var placement = state.placements[state.selectedWordIndex];
    var hintPosition = null;

    for (var i = 0; i < placement.length; i++) {
      var pos = cellCoord(placement, i);
      if (!state.grid[pos.row][pos.col].userLetter) {
        hintPosition = i;
        break;
      }
    }

    if (hintPosition === null) return;

    api('/hint', {
      session_token: state.sessionToken,
      word_index: state.selectedWordIndex,
      position: hintPosition
    }).then(function (data) {
      if (data.letter) {
        state.hintsUsed++;
        var coord = cellCoord(placement, hintPosition);
        state.grid[coord.row][coord.col].userLetter = data.letter.toUpperCase();

        var el = getCellEl(coord.row, coord.col);
        if (el) {
          var letterSpan = el.querySelector('.crossword__cell-letter');
          if (letterSpan) letterSpan.textContent = data.letter.toUpperCase();
          el.classList.add('crossword__cell--hint');
        }
      }
    }).catch(function () {
      // Network error — allow retry
    });
  }

  // ── Completion check ──
  function checkAllComplete() {
    if (state.completedWords.size < state.placements.length) return;

    var elapsed = Date.now() - state.startTime;

    api('/complete', {
      session_token: state.sessionToken,
      time_ms: elapsed,
      hints_used: state.hintsUsed
    }).then(function (data) {
      endGame(data);
    }).catch(function () {
      endGame({});
    });
  }

  // ── End game ──
  function endGame(data) {
    state.gameOver = true;
    data = data || {};

    var stats = loadStats();
    stats.games_played++;
    stats.wins++;
    stats.current_streak++;
    if (stats.current_streak > stats.best_streak) {
      stats.best_streak = stats.current_streak;
    }
    saveStats(stats);

    // Mark daily as completed
    if (state.mode === 'daily') {
      try { localStorage.setItem(todayKey(), 'done'); } catch (_) { /* quota */ }
    }

    // Mark theme progress
    if (state.mode === 'themes' && state.themeSlug) {
      try {
        var progress = JSON.parse(localStorage.getItem(themeKey(state.themeSlug)) || '{}');
        progress.completed = (progress.completed || 0) + 1;
        localStorage.setItem(themeKey(state.themeSlug), JSON.stringify(progress));
      } catch (_) { /* quota */ }
    }

    showComplete(true);
    showGame(false);

    var elapsed = Date.now() - state.startTime;

    // Title
    completeTitleEl.textContent = 'Miigwech! Puzzle complete!';

    // Stats
    completeStatsEl.innerHTML = renderStatsHtml(stats, elapsed);

    // Teaching data
    var teachingHtml = '';
    if (data.teachings && data.teachings.length > 0) {
      data.teachings.forEach(function (t) {
        teachingHtml += '<div class="crossword__teaching">';
        teachingHtml += '<p><strong>' + escapeHtml(t.word || '') + '</strong>';
        if (t.definition) {
          teachingHtml += ' &mdash; ' + escapeHtml(t.definition);
        }
        teachingHtml += '</p>';
        if (t.example_sentence) {
          teachingHtml += '<p class="crossword__teaching-example">' + escapeHtml(t.example_sentence) + '</p>';
        }
        if (t.slug) {
          teachingHtml += '<p><a href="/language/' + encodeURIComponent(t.slug) + '">View full entry</a></p>';
        }
        teachingHtml += '</div>';
      });
    }
    completeTeachingsEl.innerHTML = teachingHtml;

    // Wire action buttons (template provides [data-action] buttons)
    var nextBtn = completeActionsEl.querySelector('[data-action="next"]');
    var shareBtn = completeActionsEl.querySelector('[data-action="share"]');

    if (nextBtn) {
      var newNext = nextBtn.cloneNode(true);
      nextBtn.replaceWith(newNext);
      newNext.addEventListener('click', function () { startGame(); });
    }
    if (shareBtn) {
      var newShare = shareBtn.cloneNode(true);
      shareBtn.replaceWith(newShare);
      newShare.addEventListener('click', function () { shareResult(elapsed, newShare); });
    }
  }

  function renderStatsHtml(stats, elapsed) {
    var html = g.renderStatsHtml(stats);

    if (elapsed !== undefined) {
      html += '<div class="game-stat"><div class="game-stat__value">' + formatTime(elapsed) + '</div><div class="game-stat__label">Time</div></div>';
    }

    if (state.hintsUsed > 0) {
      html += '<div class="game-stat"><div class="game-stat__value">' + state.hintsUsed + '</div><div class="game-stat__label">Hints</div></div>';
    }

    return html;
  }

  // ── Share ──
  function shareResult(elapsed, btn) {
    var gridText = buildShareGrid();
    var date = state.mode === 'daily' ? new Date().toISOString().slice(0, 10) : 'Practice';
    var label = state.mode === 'daily' ? 'Daily Challenge'
      : state.mode === 'themes' ? 'Theme: ' + (state.themeSlug || '')
      : 'Practice (' + state.tier + ')';

    var text = '\uD83D\uDCDD Crossword \u2014 ' + label +
      '\n' + date +
      '\n' + gridText +
      '\n' + state.completedWords.size + ' words \u00B7 ' + formatTime(elapsed) +
      (state.hintsUsed > 0 ? ' \u00B7 ' + state.hintsUsed + ' hint' + (state.hintsUsed !== 1 ? 's' : '') : '') +
      '\nminoo.live/games/crossword';

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

  function buildShareGrid() {
    var lines = [];
    for (var r = 0; r < state.gridSize; r++) {
      var line = '';
      for (var c = 0; c < state.gridSize; c++) {
        if (state.grid[r][c].isBlack) {
          line += '\u2B1B'; // black square
        } else {
          line += '\uD83D\uDFE9'; // green square
        }
      }
      lines.push(line);
    }
    return lines.join('\n');
  }

  // ── Tabs ──
  function initTabs() {
    var tabs = tabsEl.querySelectorAll('.crossword__tab');
    tabs.forEach(function (tab) {
      tab.addEventListener('click', function () {
        tabs.forEach(function (t) {
          t.classList.remove('crossword__tab--active');
          t.setAttribute('aria-selected', 'false');
        });
        tab.classList.add('crossword__tab--active');
        tab.setAttribute('aria-selected', 'true');
        state.mode = tab.dataset.mode;

        // Show/hide difficulty and theme selectors
        difficultyEl.hidden = state.mode !== 'practice';
        themesEl.hidden = state.mode !== 'themes';

        if (state.mode === 'themes') {
          loadThemes();
        } else {
          startGame();
        }
      });
    });
  }

  // ── Difficulty ──
  function initDifficulty() {
    var tiers = difficultyEl.querySelectorAll('.crossword__tier');
    tiers.forEach(function (tierBtn) {
      tierBtn.addEventListener('click', function () {
        tiers.forEach(function (t) {
          t.classList.remove('crossword__tier--active');
        });
        tierBtn.classList.add('crossword__tier--active');
        state.tier = tierBtn.dataset.tier;
        startGame();
      });
    });
  }

  // ── Theme browser ──
  function loadThemes() {
    showLoading(true);
    showGame(false);
    showComplete(false);

    apiGet('/themes').then(function (data) {
      showLoading(false);
      renderThemes(data.themes || []);
    }).catch(function () {
      showLoading(false);
      themesListEl.innerHTML = '<p>Could not load themes. Please try again.</p>';
    });
  }

  function renderThemes(themes) {
    themesListEl.innerHTML = '';

    themes.forEach(function (theme) {
      var card = document.createElement('div');
      card.className = 'crossword__theme-card';

      var progress = 0;
      try {
        var stored = JSON.parse(localStorage.getItem(themeKey(theme.slug)) || '{}');
        progress = stored.completed || 0;
      } catch (_) { /* ignore */ }

      var total = theme.puzzle_count || 1;
      var pct = Math.min(Math.round((progress / total) * 100), 100);

      card.innerHTML = '<h4 class="crossword__theme-name">' + escapeHtml(theme.name) + '</h4>' +
        '<p class="crossword__theme-desc">' + escapeHtml(theme.description || '') + '</p>' +
        '<div class="crossword__theme-progress">' +
          '<div class="crossword__theme-progress-bar" style="width: ' + pct + '%;"></div>' +
        '</div>' +
        '<span class="crossword__theme-count">' + progress + '/' + total + ' puzzles</span>';

      card.addEventListener('click', function () {
        state.themeSlug = theme.slug;
        themesEl.hidden = true;
        startGame();
      });

      themesListEl.appendChild(card);
    });
  }

  // ── Start game ──
  function startGame() {
    // Reset state
    state.sessionToken = null;
    state.puzzleId = null;
    state.gridSize = 0;
    state.placements = [];
    state.clues = {};
    state.wordBank = null;
    state.grid = [];
    state.selectedCell = null;
    state.selectedDirection = 'across';
    state.selectedWordIndex = null;
    state.completedWords = new Set();
    state.hintsUsed = 0;
    state.maxHints = 0;
    state.startTime = null;
    state.gameOver = false;

    // Reset UI
    showComplete(false);
    showGame(false);
    showLoading(true);

    // Check daily dedup
    if (state.mode === 'daily') {
      try {
        if (localStorage.getItem(todayKey())) {
          showLoading(false);
          showComplete(true);
          completeTitleEl.textContent = 'You already played today\'s crossword!';
          completeTeachingsEl.innerHTML = '<p>Come back tomorrow for a new puzzle.</p>';
          var stats = loadStats();
          completeStatsEl.innerHTML = renderStatsHtml(stats);
          completeActionsEl.innerHTML = '';
          return;
        }
      } catch (_) { /* no localStorage */ }
    }

    // Build endpoint
    var endpoint;
    if (state.mode === 'daily') {
      endpoint = '/daily';
    } else if (state.mode === 'themes' && state.themeSlug) {
      endpoint = '/theme/' + encodeURIComponent(state.themeSlug);
    } else {
      endpoint = '/random?tier=' + encodeURIComponent(state.tier);
    }

    apiGet(endpoint).then(function (data) {
      if (data.error) {
        showLoading(false);
        loadingEl.hidden = false;
        loadingEl.querySelector('p').textContent = data.error;
        return;
      }

      state.sessionToken = data.session_token;
      state.puzzleId = data.puzzle_id;
      state.gridSize = data.grid_size;
      state.placements = data.placements || [];
      state.clues = data.clues || {};
      state.wordBank = data.word_bank || null;
      state.maxHints = data.max_hints || 3;
      state.startTime = Date.now();

      // Build internal grid
      state.grid = buildGrid(state.gridSize, state.placements);

      // Render
      showLoading(false);
      showGame(true);
      renderGrid();
      renderClues();
      renderWordBank();
      renderKeyboard();

      // Show difficulty badge
      if (difficultyBadgeEl && data.tier) {
        difficultyBadgeEl.textContent = data.tier.charAt(0).toUpperCase() + data.tier.slice(1);
      }

      // Select first word
      if (state.placements.length > 0) {
        selectWord(0);
      }
    }).catch(function () {
      showLoading(false);
      if (loadingEl) {
        loadingEl.hidden = false;
        var p = loadingEl.querySelector('p');
        if (p) p.textContent = 'Could not load puzzle. Please try again.';
      }
    });
  }

  // ── Init ──
  initTabs();
  initDifficulty();
  initPhysicalKeyboard();
  startGame();
})();
