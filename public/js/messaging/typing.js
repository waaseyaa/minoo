/**
 * Typing indicators — debounced POST to server, render/expire display.
 */
export class TypingIndicator {
  /**
   * @param {HTMLElement} containerEl — element to render typing indicator into
   * @param {number} currentUserId
   */
  constructor(containerEl, currentUserId) {
    this.containerEl = containerEl;
    this.currentUserId = currentUserId;
    this.typingUsers = new Map(); // userId -> { displayName, timer }
    this.sendTimer = null;
    this.sendDebounceMs = 2000;
    this.expireMs = 5000;
    this.threadId = null;
  }

  setThread(threadId) {
    this.threadId = threadId;
    this.typingUsers.clear();
    this.render();
  }

  /** Call when the local user types — debounces the POST. */
  localTyping() {
    if (!this.threadId) return;

    if (this.sendTimer) return;

    this.sendTimer = setTimeout(() => {
      this.sendTimer = null;
    }, this.sendDebounceMs);

    fetch(`/api/messaging/threads/${this.threadId}/typing`, {
      method: 'POST',
    }).catch(() => {});
  }

  /** Called when a Mercure typing event arrives. */
  remoteTyping(userId, displayName) {
    if (userId === this.currentUserId) return;

    const existing = this.typingUsers.get(userId);
    if (existing?.timer) clearTimeout(existing.timer);

    const timer = setTimeout(() => {
      this.typingUsers.delete(userId);
      this.render();
    }, this.expireMs);

    this.typingUsers.set(userId, { displayName, timer });
    this.render();
  }

  render() {
    if (this.typingUsers.size === 0) {
      this.containerEl.innerHTML = '';
      return;
    }

    const names = [...this.typingUsers.values()].map((u) => u.displayName);
    const text = names.length === 1
      ? `${names[0]} is typing`
      : `${names.join(', ')} are typing`;

    this.containerEl.innerHTML = `<div class="messages-typing">${text}<span class="messages-typing__dots">...</span></div>`;
  }
}
