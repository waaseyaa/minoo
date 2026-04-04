/**
 * Journey — Minoo's Journey hidden-object game client.
 *
 * Architecture:
 *   - Mounts on [data-game="journey"]
 *   - Scene list → scene view → completion card
 *   - Tap coordinates are sent as percentages (0.0–1.0) of the rendered
 *     image. The server validates hits; the client never sees hotspot coords.
 *   - Ojibwe / English label toggle on the object list
 *   - Hint returns a quadrant ("top-left") + label, not exact position
 */
(function () {
  'use strict';

  var g = MinooGames.create({
    statsKey: 'journey-stats',
    apiBase:  '/api/games/journey',
    cssPrefix: 'journey',
  });

  // ── Root guard ──────────────────────────────────────────────────────────
  var root = document.querySelector('[data-game="journey"]');
  if (!root) return;

  // ── DOM refs ────────────────────────────────────────────────────────────
  var scenesView    = root.querySelector('.journey__scenes');
  var sceneView     = root.querySelector('.journey__scene-view');
  var completeView  = root.querySelector('.journey__complete');

  var sceneListEl   = root.querySelector('.journey__scene-list');
  var sceneImg      = root.querySelector('.journey__scene-img');
  var sceneTapLayer = root.querySelector('.journey__tap-layer');
  var objectListEl  = root.querySelector('.journey__object-list');
  var foundCountEl  = root.querySelector('.journey__found-count');
  var totalCountEl  = root.querySelector('.journey__total-count');
  var hintBtn       = root.querySelector('.journey__hint-btn');
  var hintsLeftEl   = root.querySelector('.journey__hints-left');
  var langToggle    = root.querySelector('.journey__lang-toggle');
  var completionEl  = root.querySelector('.journey__completion-card');

  // ── State ───────────────────────────────────────────────────────────────
  var state = {
    sessionToken:  null,
    objects:       [],   // [{id, key, label_en, label_oj}]
    foundIds:      [],
    totalObjects:  0,
    hintsRemaining: 3,
    showOjibwe:    true,
    loading:       false,
  };

  // ── Boot ─────────────────────────────────────────────────────────────────
  loadSceneList();

  // ── Scene list ────────────────────────────────────────────────────────────
  function loadSceneList() {
    show(scenesView);
    hide(sceneView);
    hide(completeView);

    g.apiGet('/scenes').then(function (data) {
      renderSceneList(data.scenes || []);
    }).catch(function () {
      g.showError('Could not load scenes. Please try again.');
    });
  }

  function renderSceneList(scenes) {
    sceneListEl.innerHTML = '';
    scenes.forEach(function (scene) {
      var btn = document.createElement('button');
      btn.className = 'journey__scene-btn';
      btn.dataset.slug = scene.slug;
      btn.innerHTML =
        '<span class="journey__scene-btn-title">' + g.escapeHtml(scene.title_en) + '</span>' +
        '<span class="journey__scene-btn-oj">' + g.escapeHtml(scene.title_oj) + '</span>';
      btn.addEventListener('click', function () {
        startScene(scene.slug);
      });
      sceneListEl.appendChild(btn);
    });
  }

  // ── Scene loading ─────────────────────────────────────────────────────────
  function startScene(slug) {
    if (state.loading) return;
    state.loading = true;

    g.apiGet('/scene/' + encodeURIComponent(slug)).then(function (data) {
      state.loading        = false;
      state.sessionToken   = data.session_token;
      state.objects        = data.scene.objects;
      state.foundIds       = [];
      state.totalObjects   = data.scene.total_objects;
      state.hintsRemaining = data.hints_remaining;

      sceneImg.src = data.scene.background_url;
      sceneImg.alt = data.scene.title_en;

      renderObjectList();
      updateFoundCounter();
      updateHintBtn();

      show(sceneView);
      hide(scenesView);
      hide(completeView);
    }).catch(function () {
      state.loading = false;
      g.showError('Could not load scene. Please try again.');
    });
  }

  // ── Tap handling ──────────────────────────────────────────────────────────
  sceneTapLayer.addEventListener('click', function (e) {
    if (state.loading || !state.sessionToken) return;

    var rect = sceneTapLayer.getBoundingClientRect();
    var x    = (e.clientX - rect.left)  / rect.width;
    var y    = (e.clientY - rect.top)   / rect.height;

    x = Math.max(0, Math.min(1, x));
    y = Math.max(0, Math.min(1, y));

    state.loading = true;
    sceneTapLayer.classList.add('journey__tap-layer--busy');

    g.api('/tap', {
      session_token: state.sessionToken,
      x: x,
      y: y,
    }).then(function (data) {
      state.loading = false;
      sceneTapLayer.classList.remove('journey__tap-layer--busy');

      if (!data.found) {
        showMissRipple(x, y);
        return;
      }

      state.foundIds.push(data.object_id);
      markObjectFound(data.object_id, data.label_en, data.label_oj);
      showFoundRipple(x, y, data.label_oj, data.label_en);
      updateFoundCounter();

      if (data.scene_complete) {
        finishScene();
      }
    }).catch(function () {
      state.loading = false;
      sceneTapLayer.classList.remove('journey__tap-layer--busy');
      g.showError('Could not register tap. Please try again.');
    });
  });

  // Touch support: translate touchstart → synthetic click position
  sceneTapLayer.addEventListener('touchstart', function (e) {
    e.preventDefault();
    var touch = e.touches[0];
    var rect  = sceneTapLayer.getBoundingClientRect();
    var x     = (touch.clientX - rect.left)  / rect.width;
    var y     = (touch.clientY - rect.top)   / rect.height;
    // Fire as a synthetic click with corrected coordinates
    var synth = new MouseEvent('click', {
      clientX: touch.clientX,
      clientY: touch.clientY,
      bubbles: true,
    });
    sceneTapLayer.dispatchEvent(synth);
  }, { passive: false });

  // ── Hint ──────────────────────────────────────────────────────────────────
  hintBtn.addEventListener('click', function () {
    if (!state.sessionToken || state.hintsRemaining <= 0 || state.loading) return;
    state.loading = true;

    g.api('/hint', { session_token: state.sessionToken }).then(function (data) {
      state.loading        = false;
      state.hintsRemaining = data.hints_remaining;
      updateHintBtn();
      showHintBanner(data.label_en, data.label_oj, data.quadrant);
      g.announce('Hint: look in the ' + data.quadrant + ' area for the ' + data.label_en);
    }).catch(function () {
      state.loading = false;
      g.showError('Could not get hint.');
    });
  });

  // ── Language toggle ───────────────────────────────────────────────────────
  langToggle.addEventListener('click', function () {
    state.showOjibwe = !state.showOjibwe;
    langToggle.setAttribute('aria-pressed', String(state.showOjibwe));
    langToggle.textContent = state.showOjibwe ? 'Show English' : 'Show Ojibwe';
    renderObjectList();
  });

  // ── Scene completion ──────────────────────────────────────────────────────
  function finishScene() {
    g.api('/complete', { session_token: state.sessionToken }).then(function (data) {
      renderCompletionCard(data);
      hide(sceneView);
      show(completeView);
    }).catch(function () {
      g.showError('Could not record completion.');
    });
  }

  function renderCompletionCard(data) {
    var stars = '';
    for (var i = 0; i < 3; i++) {
      stars += '<span class="journey__star' + (i < data.stars ? ' journey__star--earned' : '') + '" aria-hidden="true">★</span>';
    }

    var homestead = '';
    if (data.homestead_item) {
      homestead =
        '<p class="journey__homestead-unlock">' +
          'Homestead unlocked: <strong>' + g.escapeHtml(data.homestead_item.label_en) + '</strong>' +
        '</p>';
    }

    completionEl.innerHTML =
      '<div class="journey__stars" aria-label="' + data.stars + ' out of 3 stars">' + stars + '</div>' +
      '<blockquote class="journey__narrative">' +
        '<p class="journey__narrative-oj">' + g.escapeHtml(data.narrative_card.text_oj) + '</p>' +
        '<p class="journey__narrative-en">' + g.escapeHtml(data.narrative_card.text_en) + '</p>' +
      '</blockquote>' +
      homestead +
      '<button class="journey__back-btn">Back to scenes</button>';

    completionEl.querySelector('.journey__back-btn').addEventListener('click', loadSceneList);
  }

  // ── Object list rendering ─────────────────────────────────────────────────
  function renderObjectList() {
    objectListEl.innerHTML = '';
    state.objects.forEach(function (obj) {
      var found = state.foundIds.indexOf(obj.id) !== -1;
      var li    = document.createElement('li');
      li.className    = 'journey__object-item' + (found ? ' journey__object-item--found' : '');
      li.dataset.id   = obj.id;
      li.textContent  = state.showOjibwe ? obj.label_oj : obj.label_en;
      li.setAttribute('aria-label', obj.label_en + (found ? ', found' : ''));
      objectListEl.appendChild(li);
    });
  }

  function markObjectFound(id, labelEn, labelOj) {
    var li = objectListEl.querySelector('[data-id="' + id + '"]');
    if (!li) return;
    li.classList.add('journey__object-item--found');
    li.setAttribute('aria-label', labelEn + ', found');
    g.announce(labelEn + ' — ' + labelOj + ' — found!');
  }

  // ── Ripple effects ────────────────────────────────────────────────────────
  function showFoundRipple(xPct, yPct, labelOj, labelEn) {
    spawnRipple(xPct, yPct, 'journey__ripple--found', labelOj + ' — ' + labelEn);
  }

  function showMissRipple(xPct, yPct) {
    spawnRipple(xPct, yPct, 'journey__ripple--miss', '');
  }

  function spawnRipple(xPct, yPct, className, labelText) {
    var ripple = document.createElement('div');
    ripple.className = 'journey__ripple ' + className;
    ripple.style.left = (xPct * 100) + '%';
    ripple.style.top  = (yPct * 100) + '%';

    if (labelText) {
      var label = document.createElement('span');
      label.className   = 'journey__ripple-label';
      label.textContent = labelText;
      ripple.appendChild(label);
    }

    sceneTapLayer.appendChild(ripple);
    ripple.addEventListener('animationend', function () {
      ripple.remove();
    });
  }

  // ── Hint banner ───────────────────────────────────────────────────────────
  function showHintBanner(labelEn, labelOj, quadrant) {
    var banner = root.querySelector('.journey__hint-banner');
    if (!banner) {
      banner = document.createElement('div');
      banner.className = 'journey__hint-banner';
      banner.setAttribute('role', 'alert');
      sceneView.insertBefore(banner, sceneTapLayer);
    }
    banner.textContent = 'Hint: ' + labelEn + ' (' + labelOj + ') — look ' + quadrant;
    banner.hidden = false;
    setTimeout(function () { banner.hidden = true; }, 4000);
  }

  // ── UI helpers ────────────────────────────────────────────────────────────
  function updateFoundCounter() {
    foundCountEl.textContent = String(state.foundIds.length);
    totalCountEl.textContent = String(state.totalObjects);
  }

  function updateHintBtn() {
    hintsLeftEl.textContent = String(state.hintsRemaining);
    hintBtn.disabled = state.hintsRemaining <= 0;
    hintBtn.setAttribute('aria-disabled', String(state.hintsRemaining <= 0));
  }

  function show(el) { if (el) el.hidden = false; }
  function hide(el) { if (el) el.hidden = true; }

}());
