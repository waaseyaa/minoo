/**
 * Chat area — renders message bubbles, handles scroll, edit/delete UI.
 */
import { escapeHtml } from './utils.js';

export class MessageView {
  /**
   * @param {HTMLElement} containerEl
   * @param {number} currentUserId
   */
  constructor(containerEl, currentUserId) {
    this.containerEl = containerEl;
    this.currentUserId = currentUserId;
    this.messages = [];
    this.threadId = null;

    this.containerEl.addEventListener('click', (event) => {
      const editBtn = event.target.closest('[data-action="edit"]');
      const deleteBtn = event.target.closest('[data-action="delete"]');

      if (editBtn) this.handleEdit(Number(editBtn.dataset.messageId));
      if (deleteBtn) this.handleDelete(Number(deleteBtn.dataset.messageId));
    });
  }

  /** @param {number} threadId */
  /** @param {Array} messages */
  render(threadId, messages) {
    this.threadId = threadId;
    this.messages = messages;

    if (messages.length === 0) {
      this.containerEl.innerHTML = '<p class="messages-empty-note">No messages yet. Say hello!</p>';
      return;
    }

    const html = messages.map((msg) => this.renderBubble(msg)).join('');
    this.containerEl.innerHTML = `<div class="messages-message-list">${html}</div>`;
    this.scrollToBottom();
  }

  renderBubble(msg) {
    const isOwn = msg.sender_id === this.currentUserId;
    const isDeleted = msg.deleted_at !== null && msg.deleted_at !== undefined;
    const isEdited = msg.edited_at !== null && msg.edited_at !== undefined;
    const direction = isOwn ? 'outgoing' : 'incoming';
    const body = isDeleted ? '<em>This message was deleted</em>' : escapeHtml(msg.body);
    const time = new Date(msg.created_at * 1000).toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
    const editedLabel = isEdited && !isDeleted ? ' · (edited)' : '';

    const actions = isOwn && !isDeleted
      ? `<span class="messages-bubble__actions">
          <button data-action="edit" data-message-id="${msg.id}" title="Edit">&#x270E;</button>
          <button data-action="delete" data-message-id="${msg.id}" title="Delete">&#x2715;</button>
        </span>`
      : '';

    return `<article class="messages-bubble messages-bubble--${direction} ${isDeleted ? 'messages-bubble--deleted' : ''}" data-message-id="${msg.id}">
      <div class="messages-bubble__content">${body}</div>
      <div class="messages-bubble__meta">${time}${editedLabel}${actions}</div>
    </article>`;
  }

  /** Append a new message (optimistic or from Mercure). */
  appendMessage(msg) {
    this.messages.push(msg);
    const list = this.containerEl.querySelector('.messages-message-list');
    if (list) {
      list.insertAdjacentHTML('beforeend', this.renderBubble(msg));
      this.scrollToBottom();
    }
  }

  /** Update a message in place (edit event). */
  updateMessage(messageId, body, editedAt) {
    const el = this.containerEl.querySelector(`[data-message-id="${messageId}"]`);
    if (!el) return;

    const msg = this.messages.find((m) => m.id === messageId);
    if (msg) {
      msg.body = body;
      msg.edited_at = editedAt;
    }

    el.outerHTML = this.renderBubble(msg || { id: messageId, body, edited_at: editedAt, sender_id: 0, created_at: 0 });
  }

  /** Mark a message as deleted in place. */
  markDeleted(messageId) {
    const msg = this.messages.find((m) => m.id === messageId);
    if (msg) {
      msg.deleted_at = Math.floor(Date.now() / 1000);
      msg.body = '';
    }

    const el = this.containerEl.querySelector(`[data-message-id="${messageId}"]`);
    if (el && msg) {
      el.outerHTML = this.renderBubble(msg);
    }
  }

  async handleEdit(messageId) {
    const msg = this.messages.find((m) => m.id === messageId);
    if (!msg) return;

    const newBody = prompt('Edit message:', msg.body);
    if (newBody === null || newBody.trim() === '' || newBody === msg.body) return;

    const response = await fetch(`/api/messaging/threads/${this.threadId}/messages/${messageId}`, {
      method: 'PATCH',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ body: newBody.trim() }),
    });

    if (response.ok) {
      const data = await response.json();
      this.updateMessage(messageId, data.body, data.edited_at);
    }
  }

  async handleDelete(messageId) {
    if (!confirm('Delete this message?')) return;

    const response = await fetch(`/api/messaging/threads/${this.threadId}/messages/${messageId}`, {
      method: 'DELETE',
    });

    if (response.ok) {
      this.markDeleted(messageId);
    }
  }

  scrollToBottom() {
    this.containerEl.scrollTop = this.containerEl.scrollHeight;
  }
}
