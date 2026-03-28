/**
 * Messaging entry point — wires all modules together.
 * Loaded on /messages page via <script type="module">.
 */
import { MercureConnection } from './mercure.js';
import { ThreadList } from './thread-list.js';
import { MessageView } from './message-view.js';
import { ComposeBar } from './compose.js';
import { TypingIndicator } from './typing.js';

const app = document.getElementById('messages-app');
if (app) {
  const userId = Number(app.dataset.userId);
  const hubUrl = app.dataset.hubUrl || '/hub';

  const threadListEl = document.getElementById('messages-thread-list');
  const threadViewEl = document.getElementById('messages-thread-view');
  const composeEl = document.getElementById('messages-compose-area');
  const typingEl = document.getElementById('messages-typing-area');

  if (!threadListEl || !threadViewEl || !composeEl || !typingEl) {
    throw new Error('Missing required DOM elements');
  }

  const threadList = new ThreadList(threadListEl, openThread);
  const messageView = new MessageView(threadViewEl, userId);
  const composeBar = new ComposeBar(composeEl, sendMessage, () => typing.localTyping());
  const typing = new TypingIndicator(typingEl, userId);

  let currentThreadId = null;
  let mercure = null;

  // Mount search input and load threads.
  threadList.mountSearch();
  loadThreads();

  async function loadThreads() {
    try {
      const response = await fetch('/api/messaging/threads');
      if (!response.ok) throw new Error('Failed');
      const json = await response.json();
      const threads = Array.isArray(json.threads) ? json.threads : [];
      threadList.render(threads);

      // Subscribe to all thread topics + user unread.
      const topics = threads.map((t) => `/threads/${t.id}`);
      topics.push(`/users/${userId}/unread`);

      if (mercure) mercure.disconnect();
      mercure = new MercureConnection(hubUrl, handleMercureEvent);
      mercure.subscribe(topics, loadThreads);
    } catch {
      threadListEl.innerHTML = '<p class="messages-empty-note">Failed to load conversations</p>';
    }
  }

  async function openThread(threadId) {
    currentThreadId = threadId;
    typing.setThread(threadId);

    try {
      const response = await fetch(`/api/messaging/threads/${threadId}/messages?limit=100`);
      if (!response.ok) throw new Error('Failed');
      const json = await response.json();
      const messages = Array.isArray(json.messages) ? json.messages : [];
      messageView.render(threadId, messages);
      composeBar.render();
      composeBar.focus();

      // Mark as read.
      fetch(`/api/messaging/threads/${threadId}/read`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: '{}' }).catch(() => {});
    } catch {
      threadViewEl.innerHTML = '<p class="messages-empty-note">Failed to load messages</p>';
    }
  }

  async function sendMessage(body) {
    if (!currentThreadId) return;

    // Optimistic append.
    const tempMsg = {
      id: Date.now(),
      thread_id: currentThreadId,
      sender_id: userId,
      body,
      created_at: Math.floor(Date.now() / 1000),
      edited_at: null,
      deleted_at: null,
    };
    messageView.appendMessage(tempMsg);

    try {
      await fetch(`/api/messaging/threads/${currentThreadId}/messages`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ body }),
      });
    } catch {
      // Message will appear via Mercure or next load.
    }

    threadList.updateThread(currentThreadId, { lastMessage: { body }, unreadCount: 0 });
  }

  function handleMercureEvent(data) {
    switch (data.type) {
      case 'message':
        if (data.message.thread_id === currentThreadId && data.message.sender_id !== userId) {
          messageView.appendMessage(data.message);
          fetch(`/api/messaging/threads/${currentThreadId}/read`, { method: 'POST' }).catch(() => {});
        }
        threadList.updateThread(data.message.thread_id, { lastMessage: data.message });
        break;

      case 'message_edited':
        messageView.updateMessage(data.id, data.body, data.edited_at);
        break;

      case 'message_deleted':
        messageView.markDeleted(data.id);
        break;

      case 'typing':
        typing.remoteTyping(data.user_id, data.display_name);
        break;

      case 'read':
        // Could update read receipt indicators here in future.
        break;

      case 'unread':
        // Handled by popover module.
        break;
    }
  }
}
