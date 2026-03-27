/**
 * Header message popover — badge + dropdown.
 * Loaded on every page via base.html.twig.
 */
import { escapeHtml } from './utils.js';

const trigger = document.getElementById('messages-popover-trigger');
const badge = document.getElementById('messages-badge');
const dropdown = document.getElementById('messages-popover-dropdown');

if (trigger && badge && dropdown) {
  const userId = Number(trigger.dataset.userId);
  const hubUrl = trigger.dataset.hubUrl || '/hub';

  // Load initial unread count.
  updateBadge();

  // Subscribe to unread topic via EventSource.
  try {
    const url = new URL(hubUrl, window.location.origin);
    url.searchParams.append('topic', `/users/${userId}/unread`);
    const es = new EventSource(url, { withCredentials: true });
    es.onmessage = (event) => {
      try {
        const data = JSON.parse(event.data);
        if (data.type === 'unread') {
          setBadge(data.count);
        }
      } catch {}
    };
  } catch {}

  // Toggle dropdown.
  trigger.addEventListener('click', async (event) => {
    event.stopPropagation();
    const isOpen = !dropdown.hidden;
    dropdown.hidden = isOpen;
    trigger.setAttribute('aria-expanded', String(!isOpen));

    if (!isOpen) {
      await loadRecentThreads();
    }
  });

  // Close on outside click.
  document.addEventListener('click', () => {
    dropdown.hidden = true;
    trigger.setAttribute('aria-expanded', 'false');
  });
  dropdown.addEventListener('click', (event) => event.stopPropagation());

  async function updateBadge() {
    try {
      const response = await fetch('/api/messaging/unread-count');
      if (!response.ok) return;
      const data = await response.json();
      setBadge(data.count || 0);
    } catch {}
  }

  function setBadge(count) {
    badge.textContent = count > 99 ? '99+' : String(count);
    badge.hidden = count === 0;
  }

  async function loadRecentThreads() {
    try {
      const response = await fetch('/api/messaging/threads?limit=5');
      if (!response.ok) return;
      const data = await response.json();
      const threads = Array.isArray(data.threads) ? data.threads : [];

      if (threads.length === 0) {
        dropdown.innerHTML = '<div class="messages-popover__empty">No conversations yet</div><a href="/messages" class="messages-popover__link">Open Messages</a>';
        return;
      }

      dropdown.innerHTML = threads.map((thread) => {
        const title = thread.title?.trim() || `Thread #${thread.id}`;
        const preview = thread.last_message?.body || 'No messages';
        const isUnread = thread.unread_count > 0;
        return `<a href="/messages#thread-${thread.id}" class="messages-popover__item ${isUnread ? 'messages-popover__item--unread' : ''}">
          <span class="messages-popover__title">${escapeHtml(title)}</span>
          <span class="messages-popover__preview">${escapeHtml(preview)}</span>
        </a>`;
      }).join('') + '<a href="/messages" class="messages-popover__link">Open Messages</a>';
    } catch {}
  }
}
