import { test, expect, Page } from '@playwright/test';
import { execSync } from 'child_process';

test.beforeAll(() => {
  execSync('php bin/seed-test-user', { cwd: process.cwd() });
});

async function clearRateLimits() {
  execSync(
    "php -r \"(new PDO('sqlite:storage/waaseyaa.sqlite'))->exec('DELETE FROM rate_limits');\"",
    { cwd: process.cwd() }
  );
}

async function login(page: Page, email: string, password: string) {
  clearRateLimits();
  await page.goto('/login');
  await page.fill('input[name="email"]', email);
  await page.fill('input[name="password"]', password);
  await page.click('.form button[type="submit"]');
  await page.waitForURL('/');
}

test.describe('Messaging — unauthenticated', () => {
  test('shows auth required message', async ({ page }) => {
    await page.goto('/messages');
    await expect(page.locator('.messages-empty')).toBeVisible();
    await expect(page.locator('.messages-empty h2')).toBeVisible();
  });

  test('does not render messaging layout', async ({ page }) => {
    await page.goto('/messages');
    await expect(page.locator('#messages-app')).not.toBeAttached();
  });
});

test.describe('Messaging — authenticated', () => {
  test.beforeEach(async ({ page }) => {
    await login(page, 'test@minoo.test', 'TestPass123!');
  });

  test('inbox layout renders with sidebar and main pane', async ({ page }) => {
    await page.goto('/messages');
    await expect(page.locator('.messages-layout')).toBeVisible();
    await expect(page.locator('.messages-sidebar')).toBeVisible();
    await expect(page.locator('.messages-main')).toBeVisible();
  });

  test('sidebar header shows Chats title and compose button', async ({ page }) => {
    await page.goto('/messages');
    await expect(page.locator('.messages-sidebar__header h2')).toContainText('Chats');
    await expect(page.locator('#messages-new-thread')).toBeVisible();
  });

  test('main pane shows placeholder when no thread selected', async ({ page }) => {
    await page.goto('/messages');
    await expect(page.locator('.messages-thread-view__placeholder')).toBeVisible();
  });

  test('compose area is not visible before selecting a thread', async ({ page }) => {
    await page.goto('/messages');
    // Compose form should not exist until a thread is selected
    await expect(page.locator('#messages-compose-form')).not.toBeAttached();
  });
});

test.describe('Messaging — thread creation and sending', () => {
  test('can create thread and send message via API, then see it in UI', async ({ page }) => {
    await login(page, 'test@minoo.test', 'TestPass123!');

    // Find the member user ID via API — list users
    const usersRes = await page.request.get('/api/messaging/users?q=Member');
    expect(usersRes.ok()).toBeTruthy();
    const users = await usersRes.json();
    const memberUser = (users.users || users).find(
      (u: { name?: string }) => u.name?.includes('Member')
    );

    if (!memberUser) {
      test.skip(true, 'Member test user not found — skipping thread test');
      return;
    }

    // Create a thread with the member user
    const threadRes = await page.request.post('/api/messaging/threads', {
      data: { participant_ids: [memberUser.id] },
    });
    expect(threadRes.ok()).toBeTruthy();
    const thread = await threadRes.json();
    const threadId = thread.thread?.id || thread.id;
    expect(threadId).toBeTruthy();

    // Send a message
    const msgRes = await page.request.post(
      `/api/messaging/threads/${threadId}/messages`,
      { data: { body: 'Hello from Playwright' } }
    );
    expect(msgRes.ok()).toBeTruthy();

    // Navigate to messages and verify thread appears in list
    await page.goto('/messages');
    await expect(page.locator(`[data-thread-id="${threadId}"]`)).toBeVisible();
  });

  test('selecting a thread shows messages and compose form', async ({ page }) => {
    await login(page, 'test@minoo.test', 'TestPass123!');
    await page.goto('/messages');

    // Click the first thread in the list (if any exist from prior test)
    const firstThread = page.locator('[data-thread-id]').first();
    if (await firstThread.count() === 0) {
      test.skip(true, 'No threads available — skipping');
      return;
    }

    await firstThread.click();

    // Wait for messages to load via API
    await expect(page.locator('.messages-bubble, .messages-empty-note')).toBeVisible({ timeout: 10000 });

    // Compose form should now be visible
    await expect(page.locator('#messages-compose-form')).toBeVisible();
    await expect(page.locator('#messages-compose-input')).toBeVisible();
    await expect(page.locator('.messages-compose__send')).toBeVisible();
  });

  test('can send a message via compose form', async ({ page }) => {
    await login(page, 'test@minoo.test', 'TestPass123!');

    // Ensure a thread exists via API
    const usersRes = await page.request.get('/api/messaging/users?q=Member');
    const users = await usersRes.json();
    const member = (users.users || users).find((u: { name?: string }) => u.name?.includes('Member'));
    if (!member) { test.skip(true, 'Member user not found'); return; }
    await page.request.post('/api/messaging/threads', { data: { participant_ids: [member.id] } });

    await page.goto('/messages');
    const firstThread = page.locator('[data-thread-id]').first();
    await expect(firstThread).toBeVisible();
    await firstThread.click();
    await expect(page.locator('#messages-compose-form')).toBeVisible();

    // Type and send a message
    const messageText = `Test message ${Date.now()}`;
    await page.fill('#messages-compose-input', messageText);

    // Wait for the API response after clicking send
    const [sendResponse] = await Promise.all([
      page.waitForResponse((r) => r.url().includes('/messages') && r.request().method() === 'POST'),
      page.click('.messages-compose__send'),
    ]);
    expect(sendResponse.ok()).toBeTruthy();

    // Message should appear in the thread view
    await expect(page.getByText(messageText)).toBeVisible({ timeout: 10000 });
  });

  test('sent messages have outgoing style', async ({ page }) => {
    await login(page, 'test@minoo.test', 'TestPass123!');

    // Ensure a thread with a message exists
    const usersRes = await page.request.get('/api/messaging/users?q=Member');
    const users = await usersRes.json();
    const member = (users.users || users).find((u: { name?: string }) => u.name?.includes('Member'));
    if (!member) { test.skip(true, 'Member user not found'); return; }
    const threadRes = await page.request.post('/api/messaging/threads', { data: { participant_ids: [member.id] } });
    const thread = await threadRes.json();
    const threadId = thread.thread?.id || thread.id;
    await page.request.post(`/api/messaging/threads/${threadId}/messages`, { data: { body: 'Style test' } });

    await page.goto('/messages');
    const firstThread = page.locator('[data-thread-id]').first();
    await expect(firstThread).toBeVisible();
    await firstThread.click();
    await expect(page.locator('.messages-bubble--outgoing')).toBeVisible({ timeout: 10000 });
  });
});

test.describe('Messaging — access control', () => {
  test('member user cannot see threads they are not part of', async ({ page }) => {
    await login(page, 'member@minoo.test', 'MemberPass123!');

    // Try to access a thread that belongs to the test user
    const threadsRes = await page.request.get('/api/messaging/threads');
    expect(threadsRes.ok()).toBeTruthy();
    const data = await threadsRes.json();
    const threads = data.threads || [];

    // Member should only see threads they participate in
    for (const thread of threads) {
      const participants = thread.participants || [];
      const memberInThread = participants.some(
        (p: { user_id?: number; name?: string }) =>
          p.name?.includes('Member') || p.user_id === thread._member_id
      );
      // If participant list is provided, member should be in it
      if (participants.length > 0) {
        expect(memberInThread).toBeTruthy();
      }
    }
  });
});

test.describe('Messaging — popover badge', () => {
  test('message badge appears in header for authenticated users', async ({ page }) => {
    await login(page, 'test@minoo.test', 'TestPass123!');
    await page.goto('/');

    // The popover trigger should exist in the header
    const trigger = page.locator('#messages-popover-trigger');
    await expect(trigger).toBeAttached();
  });

  test('clicking popover trigger opens dropdown', async ({ page }) => {
    await login(page, 'test@minoo.test', 'TestPass123!');
    await page.goto('/');

    const trigger = page.locator('#messages-popover-trigger');
    if (await trigger.count() === 0) {
      test.skip(true, 'Popover trigger not present');
      return;
    }

    const dropdown = page.locator('#messages-popover-dropdown');
    await expect(dropdown).toBeHidden();
    await trigger.click();
    await expect(dropdown).toBeVisible();
  });

  test('popover dropdown has link to messages page', async ({ page }) => {
    await login(page, 'test@minoo.test', 'TestPass123!');
    await page.goto('/');

    const trigger = page.locator('#messages-popover-trigger');
    if (await trigger.count() === 0) {
      test.skip(true, 'Popover trigger not present');
      return;
    }

    await trigger.click();
    await expect(page.locator('.messages-popover__link[href="/messages"]')).toBeVisible();
  });
});

test.describe('Messaging — reactions', () => {
  test('can react to a message via API', async ({ page }) => {
    await login(page, 'test@minoo.test', 'TestPass123!');

    // Ensure a thread with a message exists.
    const usersRes = await page.request.get('/api/messaging/users?q=Member');
    const users = await usersRes.json();
    const member = (users.users || users).find((u: { name?: string }) => u.name?.includes('Member'));
    if (!member) { test.skip(true, 'Member user not found'); return; }
    const threadRes = await page.request.post('/api/messaging/threads', { data: { participant_ids: [member.id] } });
    const thread = await threadRes.json();
    const threadId = thread.thread?.id || thread.id;
    const msgRes = await page.request.post(`/api/messaging/threads/${threadId}/messages`, { data: { body: 'React to this' } });
    const msg = await msgRes.json();
    const messageId = msg.id;

    // Add a reaction via engagement API.
    const reactRes = await page.request.post('/api/engagement/react', {
      data: { target_type: 'thread_message', target_id: messageId, reaction_type: 'miigwech' },
    });
    expect(reactRes.ok()).toBeTruthy();
    const reaction = await reactRes.json();
    expect(reaction.id).toBeTruthy();

    // Verify reactions appear in messages endpoint.
    const messagesRes = await page.request.get(`/api/messaging/threads/${threadId}/messages`);
    const messagesData = await messagesRes.json();
    const reactedMsg = messagesData.messages.find((m: { id: number }) => m.id === messageId);
    expect(reactedMsg.reactions.length).toBeGreaterThan(0);
    expect(reactedMsg.reactions[0].reaction_type).toBe('miigwech');

    // Remove the reaction (cleanup — may fail if session mismatch, not critical).
    await page.request.delete(`/api/engagement/react/${reaction.id}`);
  });

  test('react button appears on message bubbles', async ({ page }) => {
    await login(page, 'test@minoo.test', 'TestPass123!');

    // Ensure a thread with a message exists.
    const usersRes = await page.request.get('/api/messaging/users?q=Member');
    const users = await usersRes.json();
    const member = (users.users || users).find((u: { name?: string }) => u.name?.includes('Member'));
    if (!member) { test.skip(true, 'Member user not found'); return; }
    const threadRes = await page.request.post('/api/messaging/threads', { data: { participant_ids: [member.id] } });
    const thread = await threadRes.json();
    const threadId = thread.thread?.id || thread.id;
    await page.request.post(`/api/messaging/threads/${threadId}/messages`, { data: { body: 'Check react button' } });

    await page.goto('/messages');
    await page.locator(`[data-thread-id="${threadId}"]`).click();
    await expect(page.locator('.messages-bubble')).toBeVisible({ timeout: 10000 });

    // React button should be present on the bubble.
    await expect(page.locator('.messages-react-btn').first()).toBeVisible();
  });

  test('clicking react button opens picker', async ({ page }) => {
    await login(page, 'test@minoo.test', 'TestPass123!');

    // Ensure a thread with a message exists.
    const usersRes = await page.request.get('/api/messaging/users?q=Member');
    const users = await usersRes.json();
    const member = (users.users || users).find((u: { name?: string }) => u.name?.includes('Member'));
    if (!member) { test.skip(true, 'Member user not found'); return; }
    const threadRes = await page.request.post('/api/messaging/threads', { data: { participant_ids: [member.id] } });
    const thread = await threadRes.json();
    const threadId = thread.thread?.id || thread.id;
    await page.request.post(`/api/messaging/threads/${threadId}/messages`, { data: { body: 'Picker test' } });

    await page.goto('/messages');
    await page.locator(`[data-thread-id="${threadId}"]`).click();
    await expect(page.locator('.messages-bubble')).toBeVisible({ timeout: 10000 });

    // Click the react button on the first message.
    await page.locator('.messages-react-btn').first().click();
    await expect(page.locator('.messages-reaction-picker')).toBeVisible();
  });
});
