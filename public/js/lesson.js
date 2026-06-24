/**
 * Lesson 1 (Kitchen) interactions: audio playback, practice mode, mark-known.
 * Cards are server-rendered; this only wires behavior. Audio is streamed from
 * the gated /lesson/media/audio/<id> endpoint, one clip at a time.
 */
(function () {
  'use strict';

  var root = document.getElementById('lesson');
  if (!root) {
    return;
  }

  var stateKey = 'minoo_lesson1_known_v1';
  var known;
  try {
    known = new Set(JSON.parse(localStorage.getItem(stateKey) || '[]'));
  } catch (e) {
    known = new Set();
  }

  var knownCountEl = document.getElementById('lesson-known-count');
  function updateKnownCount() {
    if (knownCountEl) {
      knownCountEl.textContent = String(known.size);
    }
  }

  var current = null;
  function play(card) {
    var id = card.getAttribute('data-id');
    if (!id) {
      return;
    }
    if (current) {
      current.audio.pause();
      current.audio.currentTime = 0;
      if (current.btn) {
        current.btn.classList.remove('is-playing');
      }
    }
    var audio = new Audio('/lesson/media/audio/' + id);
    audio.preload = 'none';
    var btn = card.querySelector('.lesson-card__play');
    if (btn) {
      btn.classList.add('is-playing');
    }
    current = { audio: audio, btn: btn };
    audio.addEventListener('ended', function () {
      if (btn) {
        btn.classList.remove('is-playing');
      }
      if (current && current.audio === audio) {
        current = null;
      }
    });
    audio.play().catch(function () {
      if (btn) {
        btn.classList.remove('is-playing');
      }
    });
  }

  function reveal(card) {
    var en = card.querySelector('.lesson-card__english');
    if (en) {
      en.classList.add('is-revealed');
    }
  }

  var autoplayCb = document.getElementById('lesson-autoplay');
  var practiceCb = document.getElementById('lesson-practice');

  root.querySelectorAll('.lesson-card').forEach(function (card) {
    if (known.has(card.getAttribute('data-id'))) {
      card.classList.add('is-known');
    }

    // Cards with a teaching reel carry their own audio via the <video>; the
    // separate audio playback only applies to thumbnail-only fallback cards.
    var hasVideo = !!card.querySelector('.lesson-card__video');

    card.addEventListener('click', function (e) {
      if (e.target.closest('.lesson-card__known') || e.target.closest('.lesson-card__source') || e.target.closest('video')) {
        return;
      }
      reveal(card);
      if (!hasVideo && (!autoplayCb || autoplayCb.checked)) {
        play(card);
      }
    });

    var playBtn = card.querySelector('.lesson-card__play');
    if (playBtn) {
      playBtn.addEventListener('click', function (e) {
        e.stopPropagation();
        reveal(card);
        play(card);
      });
    }

    var knownBtn = card.querySelector('.lesson-card__known');
    if (knownBtn) {
      knownBtn.addEventListener('click', function (e) {
        e.stopPropagation();
        var id = card.getAttribute('data-id');
        if (known.has(id)) {
          known.delete(id);
          card.classList.remove('is-known');
        } else {
          known.add(id);
          card.classList.add('is-known');
        }
        try {
          localStorage.setItem(stateKey, JSON.stringify(Array.from(known)));
        } catch (e2) {
          /* ignore storage failures */
        }
        updateKnownCount();
      });
    }
  });

  if (practiceCb) {
    practiceCb.addEventListener('change', function () {
      document.body.classList.toggle('lesson-practice', practiceCb.checked);
      if (!practiceCb.checked) {
        root.querySelectorAll('.lesson-card__english.is-revealed').forEach(function (el) {
          el.classList.remove('is-revealed');
        });
      }
    });
  }

  updateKnownCount();
})();
