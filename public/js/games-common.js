/**
 * Games Common — Shared utilities for Minoo game engines.
 *
 * Provides: API helpers, localStorage stats, HTML escaping, date keys,
 * stats rendering. Each game calls MinooGames.create({ ... }) to get
 * an instance configured with its own stats key, API base, and CSS prefix.
 *
 * @example
 *   var g = MinooGames.create({
 *     statsKey: 'shkoda-stats',
 *     apiBase: '/api/games/shkoda',
 *     cssPrefix: 'shkoda'
 *   });
 *   g.api('/guess', { letter: 'a' });
 *   g.loadStats();
 */
var MinooGames = (function () {
  'use strict';

  var KEYBOARD_ROWS = [
    ['A', 'B', 'C', 'D', 'E', 'G', 'H', 'I', 'J'],
    ['K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R'],
    ['S', 'T', 'W', 'Y', 'Z', '\u02BC'] // ʼ = glottal stop
  ];

  var VALID_LETTERS = new Set(KEYBOARD_ROWS.flat().map(function (k) {
    return k.toLowerCase();
  }));

  var DEFAULT_STATS = {
    games_played: 0, wins: 0, current_streak: 0, best_streak: 0
  };

  function escapeHtml(str) {
    var div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
  }

  function create(config) {
    var statsKey = config.statsKey;
    var apiBase = config.apiBase;
    var cssPrefix = config.cssPrefix;

    function todayKey() {
      return cssPrefix + '-daily-' + new Date().toISOString().slice(0, 10);
    }

    function loadStats() {
      try {
        return JSON.parse(localStorage.getItem(statsKey)) || Object.assign({}, DEFAULT_STATS);
      } catch (_) {
        return Object.assign({}, DEFAULT_STATS);
      }
    }

    function saveStats(s) {
      try { localStorage.setItem(statsKey, JSON.stringify(s)); } catch (_) { /* quota */ }
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

    function renderStatsHtml(stats) {
      return '<div class="game-stat"><div class="game-stat__value">' + stats.games_played + '</div><div class="game-stat__label">Played</div></div>' +
        '<div class="game-stat"><div class="game-stat__value">' + stats.wins + '</div><div class="game-stat__label">Won</div></div>' +
        '<div class="game-stat"><div class="game-stat__value">' + stats.current_streak + '</div><div class="game-stat__label">Streak</div></div>' +
        '<div class="game-stat"><div class="game-stat__value">' + stats.best_streak + '</div><div class="game-stat__label">Best</div></div>';
    }

    return {
      todayKey: todayKey,
      loadStats: loadStats,
      saveStats: saveStats,
      api: api,
      apiGet: apiGet,
      renderStatsHtml: renderStatsHtml,
      escapeHtml: escapeHtml
    };
  }

  return {
    create: create,
    KEYBOARD_ROWS: KEYBOARD_ROWS,
    VALID_LETTERS: VALID_LETTERS,
    escapeHtml: escapeHtml
  };
})();
