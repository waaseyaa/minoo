/**
 * Compose bar — auto-grow textarea, send on Enter, typing broadcast.
 */
export class ComposeBar {
  /**
   * @param {HTMLElement} containerEl — element to render the compose bar into
   * @param {function} onSend — called with message body string
   * @param {function} onTyping — called when user is typing (debounced externally)
   */
  constructor(containerEl, onSend, onTyping) {
    this.containerEl = containerEl;
    this.onSend = onSend;
    this.onTyping = onTyping;
    this.maxLength = 2000;
  }

  render() {
    this.containerEl.innerHTML = `
      <form class="messages-compose" id="messages-compose-form">
        <label for="messages-compose-input" class="visually-hidden">Type a message</label>
        <textarea id="messages-compose-input" class="messages-compose__input" rows="1" maxlength="${this.maxLength}" placeholder="Type a message..." required></textarea>
        <button type="submit" class="messages-compose__send" aria-label="Send">
          <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor"><path d="M3.5 2.5l14 7.5-14 7.5v-6l8-1.5-8-1.5z"/></svg>
        </button>
      </form>`;

    const form = this.containerEl.querySelector('#messages-compose-form');
    const input = this.containerEl.querySelector('#messages-compose-input');

    // Auto-grow.
    input.addEventListener('input', () => {
      input.style.height = 'auto';
      input.style.height = Math.min(input.scrollHeight, 120) + 'px';
      this.onTyping();
    });

    // Send on Enter (Shift+Enter for newline).
    input.addEventListener('keydown', (event) => {
      if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault();
        form.requestSubmit();
      }
    });

    form.addEventListener('submit', (event) => {
      event.preventDefault();
      const body = input.value.trim();
      if (!body) return;
      this.onSend(body);
      input.value = '';
      input.style.height = 'auto';
    });
  }

  focus() {
    const input = this.containerEl.querySelector('#messages-compose-input');
    if (input) input.focus();
  }
}
