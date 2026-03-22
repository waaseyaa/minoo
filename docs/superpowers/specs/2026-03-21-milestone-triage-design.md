# Milestone Triage: Light Mode, Messaging v1, Notifications v1

**Date:** 2026-03-21
**Status:** Approved
**Scope:** Issue breakdown for three empty milestones

---

## Context

Three milestones existed with descriptions but zero issues: Light Mode (#41), Messaging (#42), Notifications (#43). This design defines the v1 issue set for each, scoped to be functional and shippable without overreaching.

### Design Decisions

| Decision | Choice | Rationale |
|----------|--------|-----------|
| Messaging transport | Short polling (5s conversation, 15s inbox) | Zero new infrastructure, PHP-compatible, upgrade path to SSE later |
| Conversation model | 1:1 thread-based | Group chats excluded from v1; two participant fields on conversation entity |
| Notification creation | Inline dispatch from controllers | Only 3 trigger types (reaction, comment, follow); event/listener pattern deferred until triggers multiply |
| Notification delivery | Polling (15s badge) | Same rationale as messaging transport |
| Light mode strategy | CSS custom property overrides via `[data-theme="light"]` | Works with existing `@layer tokens`, no stylesheet duplication, localStorage persistence |

---

## Light Mode (#41) — 5 Issues

### 1. feat: light mode color tokens in @layer tokens
**Labels:** enhancement, frontend

Define `[data-theme="light"]` overrides for all `--color-*` custom properties. Surface palette (backgrounds, text, borders, accents) mapped to light equivalents using oklch.

### 2. feat: theme toggle component
**Labels:** enhancement, frontend

Twig partial `components/theme-toggle.html.twig` + inline JS. Button in header, persists choice to `localStorage`, applies `data-theme` attribute to `<html>`. Respects `prefers-color-scheme` as default when no stored preference.

### 3. feat: light mode component variants — feed cards, sidebars, widgets
**Labels:** enhancement, frontend

Audit all `@layer components` rules. Ensure cards, sidebar, engagement UI, action buttons, detail boxes render correctly under light tokens. Fix any hardcoded colors that bypass tokens.

### 4. feat: light mode variants — forms, auth, dashboard
**Labels:** enhancement, frontend

Light mode for form inputs, auth pages, dashboard/coordinator views, flash messages, modals. Interior pages that may be missed in the component pass.

### 5. test: visual regression check for light/dark mode
**Labels:** enhancement, testing

Playwright tests that toggle theme and screenshot key pages (homepage, feed, event detail, language, elder support, dashboard) in both modes. Ensures no regressions when switching.

---

## Messaging v1 (#42) — 8 Issues

### 1. feat: conversation + message entity types
**Labels:** enhancement, backend

- `conversation` entity: uuid, participant_1 (framework user ID), participant_2 (framework user ID), last_message_at, status (active/archived), subject (optional, defaults to empty — maps to `label` key)
- Entity keys: `'id' => 'cvid'`, `'uuid' => 'uuid'`, `'label' => 'subject'`
- `message` entity: uuid, conversation_id, sender_id (framework user ID), body (TEXT, max 2000 chars enforced at controller), read_at, created_at
- Entity keys: `'id' => 'mid'`, `'uuid' => 'uuid'`, `'label' => 'body'`
- Participants reference framework-level user IDs (same as `created_by`/`updated_by` on existing entities)
- Entity classes, field definitions, service provider registration

### 2. feat: conversation + message access policies
**Labels:** enhancement, backend

- `ConversationAccessPolicy` — only participants can view/update
- `MessageAccessPolicy` — sender can create, participants can view, no edit/delete in v1
- Unit tests for both policies

### 3. feat: conversation + message migrations
**Labels:** enhancement, backend

SQLite migrations for both tables. Schema matches field definitions. Indexes on participant columns and `conversation_id + created_at` for efficient inbox/thread queries.

### 4. feat: messaging controller — CRUD endpoints
**Labels:** enhancement, backend

- `GET /messages` — inbox (conversation list with last message preview, ordered by last_message_at)
- `GET /messages/{id}` — conversation thread (paginated messages)
- `POST /messages/new` — start conversation with a user
- `POST /messages/{id}/reply` — send message in existing conversation

### 5. feat: read/unread state + mark-as-read endpoint
**Labels:** enhancement, backend

- `POST /messages/{id}/read` — marks all messages in conversation as read for current user (sets read_at)
- Inbox endpoint returns unread count per conversation
- `GET /api/messages/unread-count` — total unread for polling badge

### 6. feat: messaging inbox template
**Labels:** enhancement, frontend

`templates/messages.html.twig` — conversation list with avatar, participant name, last message preview, timestamp, unread indicator. Empty state: "No conversations yet." Link from user header/nav.

### 7. feat: conversation thread template + reply form
**Labels:** enhancement, frontend

`templates/messages/conversation.html.twig` — message bubbles (sender vs. receiver alignment), timestamps, reply textarea + send button. Polling JS fetches new messages every 5s on open conversation. Inbox polls unread count every 15s.

### 8. test: messaging integration + Playwright tests
**Labels:** enhancement, testing

- Integration tests: create conversation, send message, read/unread state, access policy enforcement
- Playwright: navigate to inbox, start conversation, send/receive message flow

**Sequencing:** Issues 1–3 (data model) → 4–5 (API) → 6–7 (UI) → 8 (tests). Each layer builds on the previous.

---

## Notifications v1 (#43) — 7 Issues

### 1. feat: notification entity type
**Labels:** enhancement, backend

- `notification` entity: uuid, recipient_id (framework user ID), actor_id (framework user ID), type, entity_type, entity_id, message (TEXT, max 500 chars), read_at, created_at
- Type field: plain TEXT column with application-level validation — values: `reaction`, `comment`, `follow`
- Entity keys: `'id' => 'nid'`, `'uuid' => 'uuid'`, `'label' => 'message'`
- Service provider + access policy (recipients view/mark their own only)

### 2. feat: notification migration
**Labels:** enhancement, backend

SQLite migration for notifications table. Indexes on `recipient_id + created_at` (inbox query) and `recipient_id + read_at` (unread count).

### 3. feat: inline notification creation for reactions, comments, follows
**Labels:** enhancement, backend

Add notification creation calls to existing engagement endpoints:
- Reaction → notify content owner
- Comment → notify content owner
- Follow → notify followed user
- No self-notifications

**Dependency:** Requires Social Feed engagement endpoints to be merged (#413 and related).

### 4. feat: notification controller — list, unread count, mark read
**Labels:** enhancement, backend

- `GET /notifications` — paginated list, newest first
- `GET /api/notifications/unread-count` — integer for badge polling
- `POST /notifications/{id}/read` — mark single as read
- `POST /notifications/read-all` — mark all as read

### 5. feat: notification inbox template
**Labels:** enhancement, frontend

`templates/notifications.html.twig` — notification list with actor name, action description ("reacted to your post"), linked entity, timestamp, read/unread styling. Empty state: "No notifications yet." Mark all as read button.

### 6. feat: unread notification badge + polling
**Labels:** enhancement, frontend

Badge component in site header (bell icon + count). Inline JS polls `GET /api/notifications/unread-count` every 15s. Badge hidden when count is 0. Clicking navigates to notifications inbox.

### 7. test: notification integration + Playwright tests
**Labels:** enhancement, testing

- Integration tests: notification created on reaction/comment/follow, no self-notification, access policy enforcement, mark-read
- Playwright: trigger reaction, verify badge appears, open inbox, verify notification displays, mark as read

**Sequencing:** Issues 1–2 (entity + migration) → 3 (inline creation, blocked on engagement endpoints) → 4 (controller) → 5–6 (UI, parallelizable) → 7 (tests).

---

## Cross-Milestone Notes

- **No dependency between the three milestones.** Light Mode, Messaging, and Notifications can be worked independently and in any order.
- **Notifications issue 3 depends on Social Feed v1** engagement endpoints being merged.
- **Transport upgrade path:** Both Messaging and Notifications use short polling in v1. SSE or WebSocket upgrade would be a future milestone that replaces the polling JS in issues Messaging#7 and Notifications#6 without changing the backend API contract.
- **Polling endpoints are lightweight:** `/api/messages/unread-count` and `/api/notifications/unread-count` return a single integer. They require authentication (framework session) but no CSRF token (GET-only, no state mutation). Rate limiting is not needed at v1 scale but should be revisited if user count grows.
- **User identity:** All participant/recipient/sender/actor fields reference framework-level user IDs — the same identity system used by `created_by`/`updated_by` on existing entities.
- **Existing milestone actions (from triage):**
  - Assign #417 → V1 Release milestone
  - Assign #390 → Social Feed v1 milestone
  - Add labels to #379 (Content Enrichment Pipeline)
