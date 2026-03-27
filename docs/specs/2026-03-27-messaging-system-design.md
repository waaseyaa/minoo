# Messaging System Design

Facebook Messenger-style real-time messaging for Minoo. Group-capable from day one, with read receipts, typing indicators, message editing/deletion, email digests, and user blocking.

## Architecture

Thin JS client + Mercure SSE. PHP stays request-response — controllers publish events to Mercure hub via HTTP POST. Browser subscribes via native EventSource API. No JavaScript framework, no build step.

```
Browser (EventSource)  ←——SSE——→  Mercure Hub  ←——POST——→  PHP Controllers
       ↑                              ↑
       |                              |
   JWT cookie                    Caddy proxy
   (subscribe auth)              (/hub → mercure:3000)
```

## Data Model

### Entity Changes

**MessageThread** — add fields:
- `thread_type` — `direct` or `group` (2-person thread = `direct`, 3+ = `group`). This is a display hint only — all threads use the same entity and logic. A `direct` thread becomes `group` when a third participant is added.
- `last_message_at` — timestamp, denormalized, updated on each message for inbox sorting

**ThreadMessage** — add fields:
- `edited_at` — nullable timestamp, set when body is updated
- `deleted_at` — nullable timestamp, soft-delete (body replaced with "This message was deleted")

**ThreadParticipant** — no changes (already has `last_read_at`, `role`)

### New Entity: UserBlock

`ContentEntityBase`, primary key: `ubid`

| Field | Type | Notes |
|---|---|---|
| `blocker_id` | int | User who blocked |
| `blocked_id` | int | User who is blocked |
| `created_at` | timestamp | When block was created |

Registered in `BlockServiceProvider`. Access via `BlockAccessPolicy` — only the blocker can view/delete their blocks.

### Blocking Behavior

- Blocked users cannot start new threads with the blocker
- Blocked users cannot be added to threads the blocker is in
- Existing threads remain visible but blocked user's new messages are hidden from the blocker
- Block is one-directional (A blocks B, B can still see A unless B also blocks A)

## API Endpoints

### Existing (enhanced)

| Method | Path | Changes |
|---|---|---|
| `GET` | `/api/messaging/threads` | Sort by `last_message_at`, include unread count per thread |
| `POST` | `/api/messaging/threads` | Validate blocked users before creation |
| `GET` | `/api/messaging/threads/{id}` | No changes |
| `GET` | `/api/messaging/threads/{id}/messages` | Cursor pagination (`?before={mid}&limit=50`), exclude soft-deleted body |
| `POST` | `/api/messaging/threads/{id}/messages` | Publish to Mercure after persist |
| `POST` | `/api/messaging/threads/{id}/participants` | Block check before adding |
| `DELETE` | `/api/messaging/threads/{id}/participants/{uid}` | No changes |
| `GET` | `/api/messaging/users` | No changes |

### New Endpoints

| Method | Path | Purpose |
|---|---|---|
| `PATCH` | `/api/messaging/threads/{id}/messages/{mid}` | Edit message body (sender only, sets `edited_at`) |
| `DELETE` | `/api/messaging/threads/{id}/messages/{mid}` | Soft-delete message (sender only, sets `deleted_at`) |
| `POST` | `/api/messaging/threads/{id}/read` | Mark thread as read (sets `last_read_at` to now) |
| `POST` | `/api/messaging/threads/{id}/typing` | Broadcast typing indicator via Mercure (no persistence) |
| `GET` | `/api/messaging/unread-count` | Global unread count for header badge |
| `POST` | `/api/blocks` | Block a user |
| `DELETE` | `/api/blocks/{user_id}` | Unblock a user |
| `GET` | `/api/blocks` | List blocked users |

### Mercure Topics

| Event | Topic | Payload |
|---|---|---|
| New message | `/threads/{id}` | `{type: "message", message: {...}}` |
| Message edited | `/threads/{id}` | `{type: "message_edited", id, body, edited_at}` |
| Message deleted | `/threads/{id}` | `{type: "message_deleted", id}` |
| Typing indicator | `/threads/{id}` | `{type: "typing", user_id, display_name}` |
| Read receipt | `/threads/{id}` | `{type: "read", user_id, last_read_at}` |
| Participant change | `/threads/{id}` | `{type: "participant_added|removed", ...}` |
| Unread count | `/users/{id}/unread` | `{type: "unread", count}` |

## Mercure Infrastructure

### Deployment

- Mercure runs as standalone binary on production (`/home/deployer/minoo/mercure`)
- Caddy proxies `/hub` to Mercure (`:3000`)
- Managed via systemd user service

### Authentication

- **Subscribe:** JWT in `mercureAuthorization` cookie (`HttpOnly`, `SameSite=Strict`). Claims list subscribed topics per user.
- **Publish:** Server-side publisher JWT (secret in `.env`). PHP POSTs to Mercure internal URL.
- **Topic refresh:** When thread membership changes, PHP sets new JWT cookie on next API response. JS reconnects EventSource.

### Local Development

- Mercure binary at `bin/mercure` (gitignored)
- Runs on `localhost:3000`, dev Caddy proxies `/hub`
- Static JWT secret from `.env`

### Failure Graceful Degradation

If Mercure is down, messaging degrades to request-response. JS detects EventSource `error` and falls back to polling every 10s. Messages are persisted by PHP regardless — no data loss.

## UI/UX

### Full Page (`/messages`)

Two-panel desktop layout:

- **Left sidebar (280px):** Thread list sorted by recency. Each thread shows avatar (round for DM, square for group), display name, message preview, timestamp, unread dot. Compose button in header. Search bar at top.
- **Right main area:** Chat header (avatar, name, active status, info button). Message list with chat bubbles — outgoing right-aligned blue, incoming left-aligned gray. Rounded corners with directional tails. Timestamps, "Seen" read receipts on outgoing. Typing indicator as animated dots. Compose bar at bottom — pill-shaped, auto-growing textarea, send button.

### Header Popover

- Message icon with unread count badge in site nav (every page via `base.html.twig`)
- Click opens dropdown: compact thread list with avatar, name, preview, timestamp, unread dot
- "Open Messages" link at bottom navigates to `/messages`
- Subscribes to `/users/{id}/unread` Mercure topic for live badge updates

### Mobile Responsive

Below 768px: single-panel view. Thread list OR conversation, not both. Back arrow in conversation header returns to thread list. Larger touch targets (48px avatars).

### Key UX Details

- **Chat bubbles:** Outgoing right-aligned (blue `#5b8def`), incoming left-aligned (gray `#2a2a4a`). Rounded 18px corners with 4px directional tail.
- **Group threads:** Square avatar with member count instead of round initials.
- **Unread indicators:** Blue dot on thread item, bold preview text, subtle background highlight `rgba(91,141,239,0.08)`.
- **Typing indicator:** Three animated dots in a chat bubble below last message. Expires after 5s without renewal.
- **Edited messages:** "(edited)" label next to timestamp. Original body not preserved.
- **Deleted messages:** Body replaced with italic "This message was deleted."
- **Compose bar:** Pill-shaped, auto-grows up to 5 lines. Enter sends, Shift+Enter for newline. 2000 char limit.

## Email Digest

### Mechanism

- Scheduled CLI command: `bin/waaseyaa messaging:digest`
- Cron: every 4 hours (`0 */4 * * *`), configurable in `config/messaging.php`
- Queries users with unread messages older than 15 minutes (debounce)
- Skips users active on site in last 30 minutes
- Groups unreads by thread, sends one email per user via SendGrid

### Email Content

```
Subject: You have 3 unread messages on Minoo

Hey {name},

You have unread messages in 2 conversations:

- Elder Sarah (1 new message)
  "Can you help with the language class?"

- Volunteer Coordinators (2 new messages)
  Tom: "Schedule updated for next week"

-> Open Messages: https://minoo.sagamok.ca/messages

--
Minoo - Sagamok Anishnawbek Community Platform
Unsubscribe: https://minoo.sagamok.ca/account/notifications
```

### User Preferences

- `notification_preferences` field on User entity (`_data` JSON): `{ "email_digest": true, "digest_interval": "4h" }`
- Account settings page gets notification preferences section
- Default: opted-in
- Unsubscribe link in every email

### Dependency

Requires fix for #493 (ConsoleKernel broken on production). Until resolved, uses HttpKernel-via-reflection workaround.

## JavaScript Module Structure

ES modules in `public/js/messaging/` — no build step, loaded via `<script type="module">`.

| File | Purpose |
|---|---|
| `index.js` | Entry point — initializes modules, wires EventSource |
| `mercure.js` | Connection manager — subscribe, reconnect, fallback polling |
| `thread-list.js` | Sidebar — render threads, sort by recency, unread state |
| `message-view.js` | Chat area — render bubbles, scroll, optimistic append, edit/delete UI |
| `compose.js` | Input bar — auto-grow, send on Enter, typing broadcast |
| `typing.js` | Typing indicators — debounced POST, render/expire indicators |
| `popover.js` | Header popover — loaded on every page, subscribes to unread topic |

### Mercure Connection Lifecycle

1. Page loads -> `mercure.js` opens EventSource to `/hub?topic=...` with JWT cookie
2. On `message` event -> dispatches to appropriate module by event type
3. On EventSource `error` -> starts polling fallback (GET threads every 10s)
4. On EventSource `open` after error -> cancels polling, resumes SSE
5. Tab hidden -> EventSource stays open (browser manages)

## Testing Strategy

### Unit Tests (PHPUnit)

| Area | Coverage |
|---|---|
| `MessageThread` | `thread_type`, `last_message_at` validation |
| `ThreadMessage` | `edited_at`, `deleted_at` fields, soft-delete behavior |
| `UserBlock` entity | Required fields, blocker/blocked validation |
| `BlockAccessPolicy` | Only blocker can view/delete |
| `MessagingAccessPolicy` | Edit/delete sender-only, block checks on creation |
| `MessagingController` | All new endpoints: edit, delete, mark-read, typing, unread-count |
| `BlockController` | Block/unblock/list |
| `MessageDigestCommand` | Debounce logic, skip active users, email content |

### Integration Tests

- Thread lifecycle: create -> add participants -> send messages -> mark read -> leave
- Block flow: block -> can't create thread -> can't add to group -> unblock
- Digest command: seed unreads -> run -> verify SendGrid called correctly

### Playwright Tests (E2E)

- Send and receive messages (two browser contexts)
- Typing indicator appears/disappears
- Edit and delete messages
- Unread badge in header updates live
- Popover shows recent threads
- Mobile: thread list -> conversation -> back navigation
- Block/unblock from conversation settings

### Not Tested

- Mercure hub (external binary)
- EventSource browser API
- SendGrid delivery

## Deferred Features

These are tracked as GitHub issues for future milestones:

- **Message reactions** — emoji reactions on individual messages (reuse post reaction system)
- **File/image sharing** — attach photos/documents (needs storage infrastructure)
- **Message search** — full-text search across conversations (SQLite FTS)
- **Role-based messaging restrictions** — e.g., only Coordinators/Elders can initiate with anyone

## Access Rules

- **Open messaging:** Any authenticated user can start a conversation with any other user
- **Blocking:** Users can block individuals to prevent incoming messages
- **Thread ownership:** Thread creator can add/remove participants
- **Message ownership:** Only the sender can edit or delete their own messages
- **Admin bypass:** Users with `administer content` permission bypass all checks

## Configuration

`config/messaging.php`:

```php
return [
    'max_message_length' => 2000,
    'typing_indicator_ttl' => 5, // seconds
    'digest_interval' => '4h',
    'digest_debounce' => 15, // minutes — don't email for recent messages
    'digest_active_skip' => 30, // minutes — skip users active within this window
    'mercure_hub_url' => env('MERCURE_HUB_URL', 'http://localhost:3000/.well-known/mercure'),
    'mercure_publisher_jwt' => env('MERCURE_PUBLISHER_JWT'),
    'mercure_subscriber_jwt_secret' => env('MERCURE_SUBSCRIBER_JWT_SECRET'),
    'polling_fallback_interval' => 10, // seconds
];
```
