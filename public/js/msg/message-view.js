/**
 * Chat area — renders message bubbles, handles scroll, edit/delete/react UI.
 */
import { escapeHtml } from './utils.js';

const REACTION_TYPES = ['like', 'interested', 'recommend', 'miigwech', 'connect'];
const REACTION_EMOJI = { like: '👍', interested: '👀', recommend: '⭐', miigwech: '🙏', connect: '🤝' };

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
      const reactBtn = event.target.closest('[data-action="react"]');
      const pickerBtn = event.target.closest('[data-action="react-pick"]');

      if (editBtn) this.handleEdit(Number(editBtn.dataset.messageId));
      if (deleteBtn) this.handleDelete(Number(deleteBtn.dataset.messageId));
      if (reactBtn) this.toggleReactionPicker(Number(reactBtn.dataset.messageId));
      if (pickerBtn) this.handleReact(Number(pickerBtn.dataset.messageId), pickerBtn.dataset.reactionType);
    });

    // Close any open picker on outside click.
    document.addEventListener('click', (event) => {
      if (!event.target.closest('.messages-reaction-picker') && !event.target.closest('[data-action="react"]')) {
        this.containerEl.querySelectorAll('.messages-reaction-picker').forEach((el) => el.remove());
      }
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

    const ownerActions = isOwn && !isDeleted
      ? `<button data-action="edit" data-message-id="${msg.id}" title="Edit">&#x270E;</button>
         <button data-action="delete" data-message-id="${msg.id}" title="Delete">&#x2715;</button>`
      : '';

    const reactAction = !isDeleted
      ? `<button data-action="react" data-message-id="${msg.id}" class="messages-react-btn" title="React">&#x1F600;</button>`
      : '';

    const actions = ownerActions || reactAction
      ? `<span class="messages-bubble__actions">${ownerActions}${reactAction}</span>`
      : '';

    const reactions = this.renderReactions(msg);

    return `<article class="messages-bubble messages-bubble--${direction} ${isDeleted ? 'messages-bubble--deleted' : ''}" data-message-id="${msg.id}">
      <div class="messages-bubble__content">${body}</div>
      ${reactions}
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
      headers: { 'Content-Type': 'application/json' },
      body: '{}',
    });

    if (response.ok) {
      this.markDeleted(messageId);
    }
  }

  renderReactions(msg) {
    const reactions = msg.reactions || [];
    if (reactions.length === 0) return '';

    // Group by reaction_type: { like: { count, userReactionId } }
    const grouped = {};
    for (const r of reactions) {
      if (!grouped[r.reaction_type]) {
        grouped[r.reaction_type] = { count: 0, userReactionId: null };
      }
      grouped[r.reaction_type].count++;
      if (r.user_id === this.currentUserId) {
        grouped[r.reaction_type].userReactionId = r.id;
      }
    }

    const pills = Object.entries(grouped).map(([type, data]) => {
      const emoji = REACTION_EMOJI[type] || type;
      const active = data.userReactionId !== null ? ' is-active' : '';
      const reactionIdAttr = data.userReactionId !== null ? ` data-reaction-id="${data.userReactionId}"` : '';
      return `<button class="messages-reaction-pill${active}" data-action="react-pick" data-message-id="${msg.id}" data-reaction-type="${type}"${reactionIdAttr}>${emoji} ${data.count}</button>`;
    }).join('');

    return `<div class="messages-reactions">${pills}</div>`;
  }

  toggleReactionPicker(messageId) {
    // Remove any existing picker.
    this.containerEl.querySelectorAll('.messages-reaction-picker').forEach((el) => el.remove());

    const bubble = this.containerEl.querySelector(`[data-message-id="${messageId}"]`);
    if (!bubble) return;

    const picker = document.createElement('div');
    picker.className = 'messages-reaction-picker';
    picker.innerHTML = REACTION_TYPES.map(
      (type) => `<button data-action="react-pick" data-message-id="${messageId}" data-reaction-type="${type}" title="${type}">${REACTION_EMOJI[type]}</button>`
    ).join('');

    bubble.appendChild(picker);
  }

  async handleReact(messageId, reactionType) {
    // Close picker.
    this.containerEl.querySelectorAll('.messages-reaction-picker').forEach((el) => el.remove());

    const msg = this.messages.find((m) => m.id === messageId);
    if (!msg) return;
    if (!msg.reactions) msg.reactions = [];

    // Check if user already has this reaction type on this message.
    const existing = msg.reactions.find(
      (r) => r.user_id === this.currentUserId && r.reaction_type === reactionType
    );

    if (existing) {
      // Remove reaction.
      const response = await fetch(`/api/engagement/react/${existing.id}`, {
        method: 'DELETE',
        headers: { 'Content-Type': 'application/json' },
        body: '{}',
      });
      if (response.ok) {
        msg.reactions = msg.reactions.filter((r) => r.id !== existing.id);
        this.refreshBubble(messageId);
      }
    } else {
      // Add reaction.
      const response = await fetch('/api/engagement/react', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ target_type: 'thread_message', target_id: messageId, reaction_type: reactionType }),
      });
      if (response.ok) {
        const data = await response.json();
        msg.reactions.push({ id: data.id, user_id: this.currentUserId, reaction_type: reactionType });
        this.refreshBubble(messageId);
      }
    }
  }

  refreshBubble(messageId) {
    const msg = this.messages.find((m) => m.id === messageId);
    const el = this.containerEl.querySelector(`[data-message-id="${messageId}"]`);
    if (el && msg) {
      el.outerHTML = this.renderBubble(msg);
    }
  }

  scrollToBottom() {
    this.containerEl.scrollTop = this.containerEl.scrollHeight;
  }
}
