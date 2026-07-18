/**
 * Engagement — feed card interactions (#817).
 *
 * Wires the server-rendered feed cards (components/domain/feed/card.html.twig
 * + engagement.html.twig) to the social spine write API (#813):
 *
 *   react       POST   /api/engagement/react              (optimistic toggle)
 *   un-react    DELETE /api/engagement/react/{id}
 *   comments    GET    /api/engagement/comments/{type}/{id}
 *   comment     POST   /api/engagement/comment
 *   delete post DELETE /api/engagement/post/{id}
 *   share       navigator.share / clipboard fallback
 *
 * CSRF: POSTs send Content-Type: application/json (exempt in the framework
 * CsrfMiddleware) and every request carries X-XSRF-TOKEN read from the
 * XSRF-TOKEN cookie — required for bodyless DELETEs, which have no
 * content-type exemption.
 *
 * The server renders no per-user reaction state (FeedItem.userReaction is
 * never populated), so the reacted/is-active state is session-local: a
 * successful POST stores the reaction id on the button for the toggle-off
 * DELETE. Vanilla JS, no frameworks — same idiom as games-common.js.
 */
(function () {
  'use strict';

  /** Reaction vocabulary per target type — mirrors the feed.action_* labels
   *  and EngagementController::ALLOWED_REACTION_TYPES. */
  var REACTION_BY_TYPE = {
    post: 'miigwech',
    event: 'interested',
    community_group: 'interested',
    community: 'interested',
    dictionary_entry: 'miigwech'
  };

  /** Escape for BOTH element and double-quoted attribute contexts. All
   *  dynamic values are passed through this before any innerHTML write. */
  function escapeHtml(str) {
    return String(str == null ? '' : str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function xsrfToken() {
    var m = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);
    return m ? m[1] : '';
  }

  function api(method, path, body) {
    var headers = { 'Content-Type': 'application/json' };
    var token = xsrfToken();
    if (token) headers['X-XSRF-TOKEN'] = token;
    return fetch(path, {
      method: method,
      headers: headers,
      credentials: 'same-origin',
      body: body === undefined ? undefined : JSON.stringify(body)
    });
  }

  /** Brief toast at the bottom of the screen (reuses .game-toast styling). */
  function toast(message, linkHref, linkText) {
    var existing = document.querySelector('.game-toast');
    if (existing) existing.remove();

    var el = document.createElement('div');
    el.className = 'game-toast';
    el.setAttribute('role', 'alert');
    el.textContent = message;
    if (linkHref) {
      el.appendChild(document.createTextNode(' '));
      var a = document.createElement('a');
      a.href = linkHref;
      a.textContent = linkText || linkHref;
      el.appendChild(a);
    }
    document.body.appendChild(el);
    setTimeout(function () { el.remove(); }, 4000);
  }

  /** Unobtrusive failure handler: 401 → sign-in prompt, else generic. */
  function failed(res) {
    if (res && res.status === 401) {
      toast('Sign in to join the conversation —', '/login', 'Sign in');
    } else {
      toast('Something went wrong — please try again');
    }
  }

  /** data-id is the composite feed id ("post:12"); returns { type, id }. */
  function target(el) {
    var raw = String(el.dataset.id || '');
    var idx = raw.lastIndexOf(':');
    return {
      type: String(el.dataset.type || ''),
      id: parseInt(idx === -1 ? raw : raw.slice(idx + 1), 10) || 0
    };
  }

  // ── Counts row ("N interested · M comments") ─────────────────────────────

  function counts(engagement) {
    if (engagement.dataset.countsInit !== '1') {
      var r = engagement.querySelector('.feed-card__count--reactions');
      var c = engagement.querySelector('.feed-card__count--comments');
      engagement.dataset.rc = String(r ? parseInt(r.textContent, 10) || 0 : 0);
      engagement.dataset.cc = String(c ? parseInt(c.textContent, 10) || 0 : 0);
      engagement.dataset.countsInit = '1';
    }
    return {
      reactions: parseInt(engagement.dataset.rc, 10) || 0,
      comments: parseInt(engagement.dataset.cc, 10) || 0
    };
  }

  /** Re-render the counts <p> to match the server template's structure. */
  function renderCounts(engagement) {
    var n = counts(engagement);
    var p = engagement.querySelector('.feed-card__counts');

    if (n.reactions <= 0 && n.comments <= 0) {
      if (p) p.remove();
      return;
    }

    var html = '';
    if (n.reactions > 0) {
      html += '<span class="feed-card__count feed-card__count--reactions">' + n.reactions + ' interested</span>';
    }
    if (n.reactions > 0 && n.comments > 0) {
      html += '<span class="feed-card__count-separator">&middot;</span>';
    }
    if (n.comments > 0) {
      html += '<span class="feed-card__count feed-card__count--comments">' + n.comments + ' comments</span>';
    }

    if (!p) {
      p = document.createElement('p');
      p.className = 'feed-card__counts';
      engagement.insertBefore(p, engagement.firstChild);
    }
    p.innerHTML = html;
  }

  function bump(engagement, kind, delta) {
    counts(engagement); // ensure initialised
    var key = kind === 'comments' ? 'cc' : 'rc';
    var next = (parseInt(engagement.dataset[key], 10) || 0) + delta;
    engagement.dataset[key] = String(next < 0 ? 0 : next);
    renderCounts(engagement);
  }

  // ── Reactions (optimistic toggle) ────────────────────────────────────────

  function react(btn) {
    if (btn.dataset.busy === '1') return;
    var engagement = btn.closest('.feed-card__engagement');
    if (!engagement) return;

    var t = target(btn);
    btn.dataset.busy = '1';

    if (!btn.classList.contains('is-active')) {
      // Optimistic: mark reacted + bump the count, reconcile with the server.
      btn.classList.add('is-active');
      btn.setAttribute('aria-pressed', 'true');
      bump(engagement, 'reactions', 1);

      api('POST', '/api/engagement/react', {
        reaction_type: REACTION_BY_TYPE[t.type] || 'like',
        target_type: t.type,
        target_id: t.id
      }).then(function (res) {
        if (!res.ok) throw res;
        return res.json();
      }).then(function (data) {
        btn.dataset.reactionId = String(data.id);
      }).catch(function (err) {
        btn.classList.remove('is-active');
        btn.setAttribute('aria-pressed', 'false');
        bump(engagement, 'reactions', -1);
        failed(err instanceof Response ? err : null);
      }).finally(function () {
        delete btn.dataset.busy;
      });
      return;
    }

    // Toggle off — only possible for a reaction created this page-session.
    var reactionId = btn.dataset.reactionId;
    if (!reactionId) {
      delete btn.dataset.busy;
      return;
    }

    btn.classList.remove('is-active');
    btn.setAttribute('aria-pressed', 'false');
    bump(engagement, 'reactions', -1);

    api('DELETE', '/api/engagement/react/' + reactionId).then(function (res) {
      if (!res.ok) throw res;
      delete btn.dataset.reactionId;
    }).catch(function (err) {
      btn.classList.add('is-active');
      btn.setAttribute('aria-pressed', 'true');
      bump(engagement, 'reactions', 1);
      failed(err instanceof Response ? err : null);
    }).finally(function () {
      delete btn.dataset.busy;
    });
  }

  // ── Comments (view + add) ────────────────────────────────────────────────

  /** created_at arrives as a Unix timestamp (entity _data); render Y-m-d. */
  function commentTime(value) {
    if (value == null || value === '') return '';
    var d = /^\d+$/.test(String(value))
      ? new Date(Number(value) * 1000)
      : new Date(String(value).replace(' ', 'T'));
    return isNaN(d.getTime()) ? String(value).slice(0, 10) : d.toISOString().slice(0, 10);
  }

  function commentHtml(comment) {
    var when = commentTime(comment.created_at);
    return '<div class="feed-comment" data-comment-id="' + escapeHtml(comment.id) + '">' +
      '<div class="feed-comment__content">' +
      '<div class="feed-comment__header"><span class="feed-comment__time">' + escapeHtml(when) + '</span></div>' +
      '<div class="feed-comment__body">' + escapeHtml(comment.body) + '</div>' +
      '</div></div>';
  }

  function renderComments(container, items, t) {
    var list = items.map(commentHtml).join('');
    container.innerHTML =
      '<div class="feed-comments__list">' + list + '</div>' +
      '<form class="feed-comments__form" data-type="' + escapeHtml(t.type) + '" data-id="' + escapeHtml(String(t.id)) + '">' +
      '<input class="feed-comments__input" name="body" maxlength="2000" required placeholder="Write a comment...">' +
      '<button type="submit" class="feed-comments__submit">Comment</button>' +
      '</form>';
  }

  function toggleComments(btn) {
    var card = btn.closest('.feed-card');
    var container = card && card.querySelector('.feed-card__comments');
    if (!container) return;

    if (!container.hidden) {
      container.hidden = true;
      return;
    }
    container.hidden = false;

    if (container.dataset.loaded === '1') return;

    var t = target(btn);
    api('GET', '/api/engagement/comments/' + encodeURIComponent(t.type) + '/' + t.id)
      .then(function (res) {
        if (!res.ok) throw res;
        return res.json();
      }).then(function (data) {
        renderComments(container, data.comments || [], t);
        container.dataset.loaded = '1';
        var input = container.querySelector('.feed-comments__input');
        if (input) input.focus();
      }).catch(function (err) {
        container.hidden = true;
        failed(err instanceof Response ? err : null);
      });
  }

  function submitComment(form) {
    var input = form.querySelector('.feed-comments__input');
    var submit = form.querySelector('.feed-comments__submit');
    var body = input ? input.value.trim() : '';
    if (body === '' || form.dataset.busy === '1') return;

    var t = target(form);
    var engagement = form.closest('.feed-card__engagement');
    form.dataset.busy = '1';
    if (submit) submit.disabled = true;

    api('POST', '/api/engagement/comment', {
      body: body,
      target_type: t.type,
      target_id: t.id
    }).then(function (res) {
      if (!res.ok) throw res;
      return res.json();
    }).then(function (data) {
      var list = form.parentElement.querySelector('.feed-comments__list');
      if (list) list.insertAdjacentHTML('afterbegin', commentHtml(data));
      if (input) input.value = '';
      if (engagement) bump(engagement, 'comments', 1);
    }).catch(function (err) {
      failed(err instanceof Response ? err : null);
    }).finally(function () {
      delete form.dataset.busy;
      if (submit) submit.disabled = false;
    });
  }

  // ── Post menu + delete own post ──────────────────────────────────────────

  function closeMenus(except) {
    document.querySelectorAll('.feed-card__menu-dropdown:not([hidden])').forEach(function (dd) {
      if (dd === except) return;
      dd.hidden = true;
      var trigger = dd.parentElement.querySelector('.feed-card__menu-trigger');
      if (trigger) trigger.setAttribute('aria-expanded', 'false');
    });
  }

  function toggleMenu(btn) {
    var dropdown = btn.parentElement.querySelector('.feed-card__menu-dropdown');
    if (!dropdown) return;
    closeMenus(dropdown);
    dropdown.hidden = !dropdown.hidden;
    btn.setAttribute('aria-expanded', dropdown.hidden ? 'false' : 'true');
  }

  function deletePost(btn) {
    if (!window.confirm('Delete this post?')) {
      closeMenus();
      return;
    }
    var card = btn.closest('.feed-card');
    var id = parseInt(String(btn.dataset.id || ''), 10) || 0;

    api('DELETE', '/api/engagement/post/' + id).then(function (res) {
      if (!res.ok) throw res;
      if (card) card.remove();
    }).catch(function (err) {
      failed(err instanceof Response ? err : null);
    });
  }

  // ── Share ────────────────────────────────────────────────────────────────

  function share(btn) {
    var url = new URL(btn.dataset.url || '/', window.location.origin).href;
    var title = btn.dataset.title || document.title;

    if (navigator.share) {
      navigator.share({ title: title, url: url }).catch(function () { /* dismissed */ });
      return;
    }
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(url).then(function () {
        toast('Link copied');
      }, function () {
        toast('Could not copy link');
      });
    }
  }

  // ── Delegated wiring ─────────────────────────────────────────────────────

  document.addEventListener('click', function (e) {
    var el = e.target.closest('[data-action]');

    if (!el || el.dataset.action !== 'toggle-menu') closeMenus();
    if (!el) return;

    switch (el.dataset.action) {
      case 'react': react(el); break;
      case 'comment': toggleComments(el); break;
      case 'share': share(el); break;
      case 'toggle-menu': toggleMenu(el); break;
      case 'delete-post': deletePost(el); break;
      // 'edit-post' is not wired yet — editing needs an update endpoint.
    }
  });

  document.addEventListener('submit', function (e) {
    var form = e.target.closest('.feed-comments__form');
    if (!form) return;
    e.preventDefault();
    submitComment(form);
  });
})();
