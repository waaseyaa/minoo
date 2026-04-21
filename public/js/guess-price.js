/**
 * Guess the Price — client-only flow (catalog JSON, no session).
 */
(function () {
  'use strict';

  var WIN_TOLERANCE = 0.1;
  var FLIP_MS = 900;

  var root = document.getElementById('guess-price-game');
  if (!root) return;

  var catalogUrl = root.getAttribute('data-catalog-url') || '/data/games/guess-price/items.json';
  var i18nEl = document.getElementById('guess-price-i18n');
  var i18n = {};
  try {
    i18n = JSON.parse(i18nEl ? i18nEl.textContent : '{}');
  } catch (e) {
    i18n = {};
  }

  var g = typeof MinooGames !== 'undefined'
    ? MinooGames.create({ statsKey: 'guess-price', apiBase: '/', cssPrefix: 'guess-price' })
    : null;

  function announce(msg) {
    if (g && g.announce) g.announce(msg);
  }

  function showError(msg) {
    if (g && g.showError) g.showError(msg);
  }

  function fmtMoney(amount) {
    return new Intl.NumberFormat('en-CA', { style: 'currency', currency: 'CAD' }).format(amount);
  }

  function shuffle(arr) {
    var a = arr.slice();
    for (var i = a.length - 1; i > 0; i--) {
      var j = Math.floor(Math.random() * (i + 1));
      var t = a[i];
      a[i] = a[j];
      a[j] = t;
    }
    return a;
  }

  function pickRoundItems(catalog) {
    var n = catalog.length;
    var maxPerRound = Math.min(5, n);
    var minPerRound = Math.min(3, maxPerRound);
    var count = minPerRound + Math.floor(Math.random() * (maxPerRound - minPerRound + 1));
    return shuffle(catalog).slice(0, count);
  }

  function validateItem(it) {
    return it && typeof it.id === 'string' && typeof it.name === 'string'
      && typeof it.image === 'string' && typeof it.actual_price === 'number' && it.actual_price > 0;
  }

  var state = {
    catalog: [],
    roundItems: [],
    completed: {},
    selectedId: null,
    currentItem: null,
    currentGuess: 0,
    lastWin: false,
    phase: 'loading',
    sliderMax: 100,
    reducedMotion: false,
  };

  var els = {
    loading: document.getElementById('guess-price-loading'),
    error: document.getElementById('guess-price-error'),
    errorMsg: document.getElementById('guess-price-error-msg'),
    retry: document.getElementById('guess-price-retry'),
    main: document.getElementById('guess-price-main'),
    progress: document.getElementById('guess-price-progress'),
    screenSelect: document.getElementById('guess-price-screen-select'),
    screenGuess: document.getElementById('guess-price-screen-guess'),
    screenReveal: document.getElementById('guess-price-screen-reveal'),
    screenResult: document.getElementById('guess-price-screen-result'),
    screenAgain: document.getElementById('guess-price-screen-again'),
    selectGrid: document.getElementById('guess-price-select-grid'),
    btnContinue: document.getElementById('guess-price-btn-continue'),
    itemImg: document.getElementById('guess-price-item-img'),
    itemName: document.getElementById('guess-price-item-name'),
    guessFor: document.getElementById('guess-price-guess-for'),
    slider: document.getElementById('guess-price-slider'),
    number: document.getElementById('guess-price-number'),
    btnLock: document.getElementById('guess-price-btn-lock'),
    flipCard: document.getElementById('guess-price-flip-card'),
    flipGuess: document.getElementById('guess-price-flip-guess'),
    flipActual: document.getElementById('guess-price-flip-actual'),
    resultTitle: document.getElementById('guess-price-result-title'),
    resultBody: document.getElementById('guess-price-result-body'),
    btnNext: document.getElementById('guess-price-btn-next'),
    btnPlayAgain: document.getElementById('guess-price-btn-play-again'),
  };

  function setPhase(phase) {
    state.phase = phase;
    var map = {
      select: els.screenSelect,
      guess: els.screenGuess,
      reveal: els.screenReveal,
      result: els.screenResult,
      again: els.screenAgain,
    };
    var list = [els.screenSelect, els.screenGuess, els.screenReveal, els.screenResult, els.screenAgain];
    list.forEach(function (el) {
      if (!el) return;
      var on = el === map[phase];
      el.hidden = !on;
      el.setAttribute('aria-hidden', on ? 'false' : 'true');
    });
    var focusEl = null;
    if (phase === 'select') focusEl = els.btnContinue;
    if (phase === 'guess') focusEl = els.slider;
    if (phase === 'reveal') focusEl = els.flipCard;
    if (phase === 'result') focusEl = els.btnNext;
    if (phase === 'again') focusEl = els.btnPlayAgain;
    if (focusEl) setTimeout(function () { focusEl.focus(); }, 80);
  }

  function updateProgress() {
    var total = state.roundItems.length;
    var done = Object.keys(state.completed).length;
    var tpl = i18n.itemProgress || '';
    var text = tpl.replace('{current}', String(done + 1)).replace('{total}', String(total));
    if (els.progress) els.progress.textContent = text;
  }

  function itemById(id) {
    for (var i = 0; i < state.roundItems.length; i++) {
      if (state.roundItems[i].id === id) return state.roundItems[i];
    }
    return null;
  }

  function hasMoreItems() {
    for (var i = 0; i < state.roundItems.length; i++) {
      if (!state.completed[state.roundItems[i].id]) return true;
    }
    return false;
  }

  function computeSliderMax() {
    var maxP = 1;
    for (var i = 0; i < state.roundItems.length; i++) {
      if (state.roundItems[i].actual_price > maxP) maxP = state.roundItems[i].actual_price;
    }
    return Math.max(10, Math.ceil(maxP * 1.5));
  }

  function renderSelectGrid() {
    if (!els.selectGrid) return;
    els.selectGrid.textContent = '';
    state.selectedId = null;
    if (els.btnContinue) els.btnContinue.disabled = true;

    for (var i = 0; i < state.roundItems.length; i++) {
      var it = state.roundItems[i];
      if (state.completed[it.id]) continue;
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'guess-price__pick';
      btn.setAttribute('role', 'radio');
      btn.setAttribute('aria-checked', 'false');
      btn.dataset.id = it.id;
      var inner = document.createElement('span');
      inner.className = 'guess-price__pick-inner';
      var img = document.createElement('img');
      img.src = it.image;
      img.alt = '';
      img.width = 120;
      img.height = 120;
      img.loading = 'lazy';
      img.decoding = 'async';
      var cap = document.createElement('span');
      cap.className = 'guess-price__pick-name';
      cap.textContent = it.name;
      inner.appendChild(img);
      inner.appendChild(cap);
      btn.appendChild(inner);
      els.selectGrid.appendChild(btn);
    }
  }

  function syncSliderFromNumber() {
    var v = parseFloat(els.number.value);
    if (Number.isNaN(v) || v < 0) v = 0;
    if (v > state.sliderMax) v = state.sliderMax;
    els.number.value = String(v);
    els.slider.value = String(v);
  }

  function syncNumberFromSlider() {
    var v = parseFloat(els.slider.value);
    els.number.value = String(v);
  }

  function showGuessForItem(item) {
    state.currentItem = item;
    els.itemImg.src = item.image;
    els.itemImg.alt = item.name;
    els.itemName.textContent = item.name;
    els.guessFor.textContent = i18n.guessFor || '';
    state.sliderMax = computeSliderMax();
    els.slider.max = String(state.sliderMax);
    els.slider.step = '0.25';
    els.number.min = '0';
    els.number.max = String(state.sliderMax);
    var mid = Math.round(state.sliderMax / 2);
    els.slider.value = String(mid);
    els.number.value = String(mid);
    state.currentGuess = mid;
    setPhase('guess');
    updateProgress();
  }

  function isWin(guess, actual) {
    var diff = Math.abs(guess - actual);
    return diff <= actual * WIN_TOLERANCE;
  }

  function goReveal() {
    state.currentGuess = parseFloat(els.number.value);
    if (Number.isNaN(state.currentGuess)) state.currentGuess = 0;
    state.currentGuess = Math.min(state.sliderMax, Math.max(0, state.currentGuess));

    els.flipGuess.textContent = fmtMoney(state.currentGuess);
    els.flipActual.textContent = fmtMoney(state.currentItem.actual_price);
    if (els.flipCard) {
      els.flipCard.classList.remove('guess-price__flip-card--flipped');
    }
    setPhase('reveal');

    function afterFlip() {
      state.lastWin = isWin(state.currentGuess, state.currentItem.actual_price);
      state.completed[state.currentItem.id] = true;
      els.resultTitle.textContent = state.lastWin ? (i18n.winTitle || '') : (i18n.loseTitle || '');
      els.resultBody.textContent = state.lastWin ? (i18n.winExplain || '') : (i18n.loseExplain || '');
      if (hasMoreItems()) {
        els.btnNext.textContent = i18n.nextItem || '';
      } else {
        els.btnNext.textContent = i18n.finishRound || 'Finish';
      }
      setPhase('result');
      announce((state.lastWin ? i18n.winTitle : i18n.loseTitle) + '. ' + els.resultBody.textContent);
    }

    if (state.reducedMotion) {
      if (els.flipCard) els.flipCard.classList.add('guess-price__flip-card--flipped');
      setTimeout(afterFlip, 50);
      return;
    }

    requestAnimationFrame(function () {
      requestAnimationFrame(function () {
        if (els.flipCard) els.flipCard.classList.add('guess-price__flip-card--flipped');
      });
    });

    var card = els.flipCard;
    if (card) {
      var done = false;
      function onEnd(ev) {
        if (ev.propertyName !== 'transform') return;
        if (done) return;
        done = true;
        card.removeEventListener('transitionend', onEnd);
        afterFlip();
      }
      card.addEventListener('transitionend', onEnd);
      setTimeout(function () {
        if (done) return;
        done = true;
        card.removeEventListener('transitionend', onEnd);
        afterFlip();
      }, FLIP_MS + 200);
    } else {
      setTimeout(afterFlip, FLIP_MS);
    }
  }

  function startRound() {
    state.roundItems = pickRoundItems(state.catalog);
    state.completed = {};
    state.selectedId = null;
    if (els.flipCard) els.flipCard.classList.remove('guess-price__flip-card--flipped');
    renderSelectGrid();
    setPhase('select');
    announce(i18n.selectTitle || '');
    if (window.scrollY > 0) window.scrollTo({ top: 0, behavior: 'smooth' });
  }

  function loadCatalog() {
    if (els.loading) els.loading.hidden = false;
    if (els.error) els.error.hidden = true;
    if (els.main) els.main.hidden = true;

    fetch(catalogUrl, { credentials: 'same-origin' })
      .then(function (r) {
        if (!r.ok) throw new Error('bad status');
        return r.json();
      })
      .then(function (data) {
        if (!Array.isArray(data) || data.length === 0) throw new Error('empty');
        var valid = data.filter(validateItem);
        if (valid.length < 3) throw new Error('not enough');
        state.catalog = valid;
        if (els.loading) els.loading.hidden = true;
        if (els.main) els.main.hidden = false;
        startRound();
      })
      .catch(function () {
        if (els.loading) els.loading.hidden = true;
        if (els.error) els.error.hidden = false;
        if (els.errorMsg) els.errorMsg.textContent = i18n.errorLoad || '';
        showError(i18n.errorLoad || '');
      });
  }

  function onSelectGridClick(ev) {
    var t = ev.target.closest('.guess-price__pick');
    if (!t || !els.selectGrid.contains(t)) return;
    var id = t.dataset.id;
    state.selectedId = id;
    var opts = els.selectGrid.querySelectorAll('.guess-price__pick');
    for (var i = 0; i < opts.length; i++) {
      opts[i].setAttribute('aria-checked', opts[i] === t ? 'true' : 'false');
      opts[i].classList.toggle('guess-price__pick--active', opts[i] === t);
    }
    if (els.btnContinue) els.btnContinue.disabled = false;
  }

  root.addEventListener('click', onSelectGridClick);

  if (els.btnContinue) {
    els.btnContinue.addEventListener('click', function () {
      if (!state.selectedId) return;
      var item = itemById(state.selectedId);
      if (item) showGuessForItem(item);
    });
  }

  if (els.slider) {
    els.slider.addEventListener('input', syncNumberFromSlider);
  }
  if (els.number) {
    els.number.addEventListener('input', syncSliderFromNumber);
  }

  if (els.btnLock) {
    els.btnLock.addEventListener('click', goReveal);
  }

  if (els.btnNext) {
    els.btnNext.addEventListener('click', function () {
      if (hasMoreItems()) {
        state.selectedId = null;
        renderSelectGrid();
        setPhase('select');
        announce(i18n.selectTitle || '');
      } else {
        setPhase('again');
        announce(i18n.allDoneTitle || '');
      }
    });
  }

  if (els.btnPlayAgain) {
    els.btnPlayAgain.addEventListener('click', function () {
      startRound();
    });
  }

  if (els.retry) {
    els.retry.addEventListener('click', loadCatalog);
  }

  state.reducedMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  loadCatalog();
})();
