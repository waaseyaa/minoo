/**
 * Mercure SSE connection manager.
 * Handles subscribe, reconnect, and polling fallback.
 */
export class MercureConnection {
  /** @param {string} hubUrl */
  /** @param {function} onEvent */
  /** @param {number} pollingInterval — fallback polling interval in ms */
  constructor(hubUrl, onEvent, pollingInterval = 10000) {
    this.hubUrl = hubUrl;
    this.onEvent = onEvent;
    this.pollingInterval = pollingInterval;
    this.topics = [];
    this.eventSource = null;
    this.pollTimer = null;
    this.pollFn = null;
  }

  /**
   * Subscribe to Mercure topics via EventSource.
   * @param {string[]} topics
   * @param {function} [pollFallback] — called during polling fallback
   */
  subscribe(topics, pollFallback = null) {
    this.topics = topics;
    this.pollFn = pollFallback;
    this.connect();
  }

  connect() {
    this.disconnect();

    const url = new URL(this.hubUrl);
    for (const topic of this.topics) {
      url.searchParams.append('topic', topic);
    }

    this.eventSource = new EventSource(url, { withCredentials: true });

    this.eventSource.onmessage = (event) => {
      try {
        const data = JSON.parse(event.data);
        this.onEvent(data);
      } catch {
        // Ignore malformed events.
      }
    };

    this.eventSource.onerror = () => {
      this.startPolling();
    };

    this.eventSource.onopen = () => {
      this.stopPolling();
    };
  }

  startPolling() {
    if (this.pollTimer || !this.pollFn) return;
    this.pollTimer = setInterval(() => this.pollFn(), this.pollingInterval);
  }

  stopPolling() {
    if (this.pollTimer) {
      clearInterval(this.pollTimer);
      this.pollTimer = null;
    }
  }

  disconnect() {
    if (this.eventSource) {
      this.eventSource.close();
      this.eventSource = null;
    }
    this.stopPolling();
  }

  /** Reconnect with new topic list (e.g., after joining/leaving a thread). */
  updateTopics(topics) {
    this.topics = topics;
    this.connect();
  }
}
