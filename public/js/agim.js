(function () {
  'use strict';

  const root = document.getElementById('agim-game');
  if (!root) return;

  const apiBase = root.dataset.apiBase;
  const els = {
    tabs:          root.querySelectorAll('.agim__tab'),
    numeral:       document.getElementById('agim-numeral'),
    form:          document.getElementById('agim-form'),
    input:         document.getElementById('agim-answer'),
    feedback:      document.getElementById('agim-feedback'),
    progress:      document.getElementById('agim-progress'),
    progressFill:  document.getElementById('agim-progress-fill'),
    progressLabel: document.getElementById('agim-progress-label'),
    reveal:        document.getElementById('agim-reveal'),
    revealSummary: document.getElementById('agim-reveal-summary'),
    teachings:     document.getElementById('agim-teachings'),
    playAgain:     document.getElementById('agim-play-again'),
    loading:       document.getElementById('agim-loading'),
    announcer:     document.getElementById('agim-announcer'),
  };

  let state = {
    sessionToken: null,
    level: 1,
    total: 0,
    remaining: 0,
    currentNumeral: null,
  };

  // --- Helpers ---

  async function apiPost(path, body) {
    const res = await fetch(apiBase + path, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(body),
    });
    return res.json();
  }

  async function apiGet(path, params) {
    const qs = params ? '?' + new URLSearchParams(params).toString() : '';
    const res = await fetch(apiBase + path + qs);
    return res.json();
  }

  function show(el) { el.hidden = false; }
  function hide(el) { el.hidden = true; }

  function announce(msg) {
    els.announcer.textContent = '';
    requestAnimationFrame(() => { els.announcer.textContent = msg; });
  }

  function updateProgress() {
    const done = state.total - state.remaining;
    const pct = state.total > 0 ? Math.round((done / state.total) * 100) : 0;
    els.progressFill.style.width = pct + '%';
    els.progressLabel.textContent = done + ' / ' + state.total;
  }

  function showFeedback(correct, expectedWord) {
    els.feedback.className = 'agim__feedback agim__feedback--' + (correct ? 'correct' : 'wrong');
    els.feedback.textContent = correct
      ? '✓ ' + expectedWord
      : '✗ ' + expectedWord + ' — try again later';
    show(els.feedback);
    setTimeout(() => hide(els.feedback), 1800);
  }

  // --- Game flow ---

  async function startGame(level) {
    hide(els.reveal);
    hide(els.feedback);
    hide(els.progress);
    show(els.numeral);
    show(els.form);
    els.numeral.textContent = '';
    els.input.value = '';
    show(els.loading);

    const data = await apiGet('/start', { level });
    hide(els.loading);

    if (data.error) {
      els.numeral.textContent = 'Could not start — try again.';
      return;
    }

    state.sessionToken = data.session_token;
    state.level = data.level;
    state.total = data.total;
    state.remaining = data.total;
    state.currentNumeral = data.numeral;

    show(els.progress);
    updateProgress();
    showNumeral(data.numeral);
    els.input.focus();
  }

  function showNumeral(n) {
    els.numeral.textContent = n;
    announce('What is ' + n + ' in Ojibwe?');
  }

  async function submitAnswer(answer) {
    const data = await apiPost('/answer', {
      session_token: state.sessionToken,
      numeral: state.currentNumeral,
      answer,
    });

    state.remaining = data.remaining;
    updateProgress();
    showFeedback(data.correct, data.expected_word);
    els.input.value = '';

    if (data.remaining === 0) {
      await finishGame();
      return;
    }

    const prompt = await apiGet('/prompt', { session_token: state.sessionToken });
    if (prompt.numeral) {
      state.currentNumeral = prompt.numeral;
      showNumeral(prompt.numeral);
    }
    els.input.focus();
  }

  async function finishGame() {
    const data = await apiPost('/complete', { session_token: state.sessionToken });

    hide(els.numeral);
    hide(els.form);

    const secs = data.time_seconds || 0;
    const mins = Math.floor(secs / 60);
    const secsRem = secs % 60;
    els.revealSummary.textContent =
      'Finished in ' + (mins > 0 ? mins + 'm ' : '') + secsRem + 's';

    els.teachings.innerHTML = '';
    for (const t of (data.teachings || [])) {
      const li = document.createElement('li');
      li.className = 'agim__teaching-item';
      li.innerHTML =
        '<span class="agim__teaching-numeral">' + t.numeral + '</span>' +
        ' <span class="agim__teaching-word">' + t.word + '</span>' +
        (t.meaning ? ' — <span class="agim__teaching-meaning">' + t.meaning + '</span>' : '');
      els.teachings.appendChild(li);
    }

    show(els.reveal);
    announce('Miigwech! You completed level ' + state.level);
  }

  // --- Events ---

  els.tabs.forEach(function (tab) {
    tab.addEventListener('click', function () {
      els.tabs.forEach(function (t) {
        t.classList.remove('agim__tab--active');
        t.setAttribute('aria-selected', 'false');
      });
      tab.classList.add('agim__tab--active');
      tab.setAttribute('aria-selected', 'true');
      startGame(parseInt(tab.dataset.level, 10));
    });
  });

  els.form.addEventListener('submit', function (e) {
    e.preventDefault();
    const answer = els.input.value.trim();
    if (answer === '') return;
    submitAnswer(answer);
  });

  els.playAgain.addEventListener('click', function () {
    show(els.numeral);
    show(els.form);
    startGame(state.level);
  });

  // Boot
  startGame(1);
})();
