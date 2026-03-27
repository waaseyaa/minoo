/**
 * Thread list sidebar — renders threads, handles selection, unread state.
 */
import { escapeHtml } from './utils.js';

export class ThreadList {
  /**
   * @param {HTMLElement} containerEl
   * @param {function} onSelectThread — called with threadId when a thread is clicked
   */
  constructor(containerEl, onSelectThread) {
    this.containerEl = containerEl;
    this.onSelectThread = onSelectThread;
    this.threads = [];
    this.activeThreadId = null;

    this.containerEl.addEventListener('click', (event) => {
      const button = event.target.closest('[data-thread-id]');
      if (!button) return;
      const threadId = Number(button.dataset.threadId);
      this.setActive(threadId);
      this.onSelectThread(threadId);
    });
  }

  /** @param {Array} threads */
  render(threads) {
    this.threads = threads;

    if (threads.length === 0) {
      this.containerEl.innerHTML = '<p class="messages-empty-note">No conversations yet</p>';
      return;
    }

    this.containerEl.innerHTML = threads.map((thread) => {
      const isActive = this.activeThreadId === thread.id;
      const isUnread = thread.unread_count > 0;
      const title = thread.title?.trim() || `Thread #${thread.id}`;
      const preview = thread.last_message?.body || 'No messages yet';
      const time = thread.last_message_at ? this.formatTime(thread.last_message_at) : '';
      const avatarClass = thread.thread_type === 'group' ? 'messages-avatar--group' : '';

      return `<button class="messages-thread-list__item ${isActive ? 'messages-thread-list__item--active' : ''} ${isUnread ? 'messages-thread-list__item--unread' : ''}" data-thread-id="${thread.id}">
        <span class="messages-avatar ${avatarClass}">${escapeHtml(this.initials(title))}</span>
        <span class="messages-thread-list__body">
          <span class="messages-thread-list__header">
            <span class="messages-thread-list__title">${escapeHtml(title)}</span>
            <span class="messages-thread-list__time">${escapeHtml(time)}</span>
          </span>
          <span class="messages-thread-list__preview">${escapeHtml(preview)}</span>
        </span>
        ${isUnread ? '<span class="messages-unread-dot"></span>' : ''}
      </button>`;
    }).join('');
  }

  setActive(threadId) {
    this.activeThreadId = threadId;
    this.containerEl.querySelectorAll('[data-thread-id]').forEach((el) => {
      el.classList.toggle('messages-thread-list__item--active', Number(el.dataset.threadId) === threadId);
    });
  }

  /** Update a single thread's preview and unread state without full re-render. */
  updateThread(threadId, { lastMessage, unreadCount }) {
    const thread = this.threads.find((t) => t.id === threadId);
    if (!thread) return;

    if (lastMessage) thread.last_message = lastMessage;
    if (unreadCount !== undefined) thread.unread_count = unreadCount;
    thread.last_message_at = Math.floor(Date.now() / 1000);

    // Re-sort by recency and re-render.
    this.threads.sort((a, b) => (b.last_message_at || 0) - (a.last_message_at || 0));
    this.render(this.threads);
  }

  /** @param {string} name */
  initials(name) {
    return name.split(/\s+/).slice(0, 2).map((w) => w[0] || '').join('').toUpperCase() || '?';
  }

  /** @param {number} timestamp */
  formatTime(timestamp) {
    const diff = Math.floor(Date.now() / 1000) - timestamp;
    if (diff < 60) return 'now';
    if (diff < 3600) return `${Math.floor(diff / 60)}m`;
    if (diff < 86400) return `${Math.floor(diff / 3600)}h`;
    return `${Math.floor(diff / 86400)}d`;
  }
}
