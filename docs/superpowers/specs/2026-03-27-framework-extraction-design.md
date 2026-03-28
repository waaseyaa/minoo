# Framework Extraction Plan — Minoo → Waaseyaa

**Date:** 2026-03-27
**Issues:** waaseyaa/framework #692–#702
**Milestone:** Feature Parity Roadmap (P2/P3)

## Goal

Extract 10 generic patterns from Minoo's `src/Support/`, `src/Entity/`, and `src/Controller/` into the Waaseyaa framework so any Waaseyaa app can reuse them. Executed in 3 batches, each producing one framework PR + tag and one Minoo PR.

## Decisions

| Issue | Component | Target package | Notes |
|---|---|---|---|
| #692 | SlugGenerator | `waaseyaa/foundation` | Static utility, add to existing |
| #693 | GeoDistance | new `waaseyaa/geo` | New package (room for future geo utilities) |
| #694 | MercurePublisher | new `waaseyaa/mercure` | Thin wrapper only, no subscriber infrastructure |
| #695 | PasswordResetService | `waaseyaa/user` | Add to existing, convert `ensureTable()` to migration |
| #697 | Flash | `waaseyaa/ssr` | Add to existing, alongside Twig layer |
| #698 | UploadService | `waaseyaa/media` | Add to existing, parameterize MIME types + max size |
| #699 | MailService | new `waaseyaa/mail` | `MailDriverInterface` + `SendGridDriver` |
| #700 | JSON helpers | `waaseyaa/api` | `JsonResponseTrait`, add to existing |
| #701 | Engagement entities | new `waaseyaa/engagement` | Configurable reaction types via config |
| #702 | Messaging + UserBlock | new `waaseyaa/messaging` + UserBlock in `waaseyaa/user` | UserBlock in user package (upstream of both engagement and messaging) |

## Batch 1 — Zero-Coupling Extractions

**Scope:** 6 issues (#692, #693, #694, #697, #698, #700). One framework PR, one Minoo PR.

### 1. SlugGenerator → `waaseyaa/foundation` (#692)

- Add `Waaseyaa\Foundation\SlugGenerator` — static `generate(string): string`
- Same logic: lowercase, strip non-alphanumeric, trim hyphens
- No service provider change — pure static utility
- Unit test: `packages/foundation/tests/Unit/SlugGeneratorTest.php`

### 2. GeoDistance → new `waaseyaa/geo` (#693)

- New package: `packages/geo/`
- `Waaseyaa\Geo\GeoDistance` — static `haversine(lat1, lon1, lat2, lon2, unit): float`
- `GeoServiceProvider` — empty for now, establishes the package
- `composer.json` requires `waaseyaa/foundation`
- Unit test covering km and miles

### 3. MercurePublisher → new `waaseyaa/mercure` (#694)

- New package: `packages/mercure/`
- `Waaseyaa\Mercure\MercurePublisher` — `publish(topic, data): bool`, `isConfigured(): bool`
- `MercureServiceProvider` registers singleton from config (`mercure.hub_url`, `mercure.jwt_secret`)
- Hand-rolled HS256 JWT stays (no new dependency)
- Refactor: accept `HttpClientInterface` parameter instead of raw `curl_exec` (testability)
- Unit test with mocked HTTP client

### 4. UploadService → `waaseyaa/media` (#698)

- Add `Waaseyaa\Media\UploadHandler`
- Constructor params: `array $allowedMimeTypes`, `int $maxSizeBytes` (defaults: images, 5MB)
- Register in `MediaServiceProvider` as singleton
- Unit test with temp files

### 5. Flash → `waaseyaa/ssr` (#697)

- Add `Waaseyaa\Ssr\Flash\FlashMessageService` — session-backed add/get/clear
- Add `Waaseyaa\Ssr\Flash\Flash` — static facade
- Add Twig extension `flash_messages()` in `SsrServiceProvider`
- `Flash::setService()` called in provider boot; test helper to reset between tests
- Unit test for add/get/clear lifecycle

### 6. JSON helpers → `waaseyaa/api` (#700)

- Add `Waaseyaa\Api\JsonResponseTrait`
- `jsonBody(Request): array` — safe JSON decode of request body
- `json(data, status): SsrResponse` — build JSON response
- No service provider change — trait that controllers opt into
- Unit test

### Minoo PR (after framework tag)

- Replace 6 `use Minoo\...` imports with `use Waaseyaa\...` equivalents
- Delete: `SlugGenerator.php`, `GeoDistance.php`, `MercurePublisher.php`, `UploadService.php`, `Flash.php`, `FlashMessageService.php`, `FlashServiceProvider.php`
- Delete `GameControllerTrait::jsonBody()` and `json()` (use framework trait instead)
- `composer update waaseyaa/*`
- Run full test suite

## Batch 2 — Mail, Auth, and User Blocking

**Scope:** 3 issues (#699, #695, #702 partial). One framework PR, one Minoo PR. Depends on Batch 1 (Mercure not directly needed, but establishes the new-package pattern).

### 7. Mail package → new `waaseyaa/mail` (#699)

- New package: `packages/mail/`
- `Waaseyaa\Mail\MailDriverInterface` — `send(MailMessage): bool`, `isConfigured(): bool`
- `Waaseyaa\Mail\MailMessage` — value object: `from`, `to`, `subject`, `body`, `htmlBody` (optional)
- `Waaseyaa\Mail\Driver\SendGridDriver` — wraps `sendgrid/sendgrid`
- `Waaseyaa\Mail\MailServiceProvider` — reads config (`mail.driver`, `mail.from_address`, `mail.sendgrid_api_key`), registers driver singleton bound to interface
- `sendgrid/sendgrid` in `waaseyaa/mail` composer.json `require`
- Unit tests: `MailMessage` construction, `SendGridDriver` with mocked HTTP

### 8. PasswordResetService + AuthMailer → `waaseyaa/user` (#695)

- Add `Waaseyaa\User\PasswordResetTokenRepository` — create/validate/consume tokens
- Drop `ensureTable()` — add framework migration for `password_reset_tokens` table
- Add `Waaseyaa\User\AuthMailer` — password-reset, email-verification, welcome emails
- Constructor takes `string $appName` from config (`app.name`) — no hardcoded "Minoo"
- Email templates: `packages/user/templates/email/password-reset.html.twig`, `verification.html.twig`, `welcome.html.twig`
- `UserServiceProvider` registers both: `PasswordResetTokenRepository` (needs `\PDO`), `AuthMailer` (needs `MailDriverInterface`, Twig, config)
- `waaseyaa/user` gains `require: waaseyaa/mail`
- Unit tests for token lifecycle and `AuthMailer` (mock mail driver)

### 9. UserBlock → `waaseyaa/user` (#702 partial)

- Add `Waaseyaa\User\UserBlock` entity — `blocker_id`, `blocked_id`, `created_at`
- Add `Waaseyaa\User\UserBlockAccessPolicy` — users can block/unblock, view own blocks
- Add `Waaseyaa\User\UserBlockService` — `isBlocked(blockerId, blockedId): bool`
- Register entity type + policy + service in `UserServiceProvider`
- Unit tests for entity, policy, service

### Minoo PR (after framework tag)

- Delete: `MailService.php`, `AuthMailer.php`, `PasswordResetService.php`, `UserBlock.php` entity
- Delete: `MailServiceProvider.php`, `BlockServiceProvider.php`, `BlockAccessPolicy.php`
- Add Minoo config: `app.name => 'Minoo'`, `mail.driver => 'sendgrid'`, `mail.from_address => 'hello@minoo.live'`, `mail.sendgrid_api_key => env('SENDGRID_API_KEY')`
- Update `MessagingController` to use `Waaseyaa\User\UserBlockService::isBlocked()`
- Move email templates from Minoo to framework (or keep Minoo overrides if customized beyond app name)
- `composer update waaseyaa/*`
- Run full test suite

## Batch 3 — Engagement and Messaging

**Scope:** 2 issues (#701, #702 remainder). One framework PR, one Minoo PR. Depends on Batch 2 (mail for digest, user block service for access checks).

### 10. Engagement entities → new `waaseyaa/engagement` (#701)

- New package: `packages/engagement/`
- `Waaseyaa\Engagement\Reaction` entity — polymorphic `target_type`/`target_id`, `user_id`, `created_at`, `reaction_type`
- `Waaseyaa\Engagement\Comment` entity — same polymorphic pattern, `body`, `user_id`, `created_at`
- `Waaseyaa\Engagement\Follow` entity — `target_type`/`target_id`, `user_id`, `created_at`
- `Waaseyaa\Engagement\EngagementAccessPolicy` — authenticated create, authors delete own, coordinators moderate
- `Waaseyaa\Engagement\EngagementServiceProvider` — registers 3 entity types + policy
- **Configurable reaction types:** provider reads `engagement.reaction_types` from config (default: `['like', 'love', 'celebrate']`). Minoo overrides: `['like', 'interested', 'recommend', 'miigwech', 'connect']`
- `Reaction::ALLOWED_REACTION_TYPES` set dynamically by provider, validated in entity constructor
- Dependencies: `waaseyaa/entity`, `waaseyaa/access`, `waaseyaa/user`
- Unit tests for all 3 entities, policy, configurable reaction validation

### 11. Messaging → new `waaseyaa/messaging` (#702)

- New package: `packages/messaging/`
- `Waaseyaa\Messaging\MessageThread` entity — `subject`, `created_by`, `created_at`, `updated_at`
- `Waaseyaa\Messaging\ThreadMessage` entity — `thread_id`, `sender_id`, `body`, `created_at`, `read_at`
- `Waaseyaa\Messaging\ThreadParticipant` entity — `thread_id`, `user_id`, `joined_at`, `last_read_at`
- `Waaseyaa\Messaging\MessagingAccessPolicy` — participants read/reply, creator adds participants, blocked users denied
- `Waaseyaa\Messaging\MessagingServiceProvider` — registers entity types + policy, wires `MercurePublisher` for real-time (optional, guarded by `isConfigured()`)
- `Waaseyaa\Messaging\MessageDigestCommand` — URL from config (`app.url`), digest emails via `MailDriverInterface`
- Dependencies: `waaseyaa/entity`, `waaseyaa/access`, `waaseyaa/user` (block checks), `waaseyaa/mercure` (optional), `waaseyaa/mail` (digest)
- Unit tests for entities, policy, digest command (mock mail + mercure)

### Minoo PR (after framework tag)

- Delete: `MessageThread.php`, `ThreadMessage.php`, `ThreadParticipant.php`, `Reaction.php`, `Comment.php`, `Follow.php` entities
- Delete: `MessagingServiceProvider.php`, `EngagementServiceProvider.php`, `EngagementAccessPolicy.php`
- Delete: `MessageDigestCommand.php`
- Add Minoo config: `engagement.reaction_types => ['like', 'interested', 'recommend', 'miigwech', 'connect']`, `app.url => 'https://minoo.live'`
- Update `MessagingController` and `EngagementController` imports to framework namespaces
- `composer update waaseyaa/*`
- Run full test suite

## Dependency Graph

```
Batch 1 (independent, parallelizable within batch):
  foundation ← SlugGenerator
  geo (new)
  mercure (new)
  media ← UploadHandler
  ssr ← Flash
  api ← JsonResponseTrait

Batch 2 (depends on Batch 1 being tagged):
  mail (new)
  user ← PasswordResetTokenRepository, AuthMailer (requires mail)
  user ← UserBlock, UserBlockService

Batch 3 (depends on Batch 2 being tagged):
  engagement (new, requires user for block checks)
  messaging (new, requires user + mercure + mail)
```

## Testing Strategy

Each batch follows the same pattern:

1. **Framework side:** Unit tests for every new class. Run `packages/*/tests/` for affected packages.
2. **Minoo side:** After import swap, run full Minoo test suite (`./vendor/bin/phpunit`). All 759+ tests must pass.
3. **Integration:** Minoo integration test boots `HttpKernel` — confirms providers discover correctly, entity types register, policies wire up.

## Risk Mitigation

- **Stale manifest cache:** Delete `storage/framework/packages.php` after each Minoo `composer update` to force provider re-discovery.
- **Autoloader corruption:** Run `composer dump-autoload` in Minoo after each framework version bump.
- **Engagement reaction types:** If config is missing, fall back to framework defaults — never crash on missing config.
- **MercurePublisher `isConfigured()` guard:** Messaging works without Mercure (no real-time, but messages still persist). Already the pattern in Minoo.
- **Email template overrides:** If Minoo has customized email templates beyond app name substitution, keep them as Minoo overrides (Twig template inheritance).

## Out of Scope

- **Post entity:** Has `community_id` — Minoo domain, stays in Minoo
- **ElderIdentity:** Minoo domain concept
- **NorthCloudClient / NorthCloudCache:** Minoo-specific NC API integration
- **Game engines:** Minoo-specific
- **EmailVerificationService:** Review separately after AuthMailer extraction
- **Additional mail drivers:** Only SendGrid for now; SMTP/Postmark added when needed
- **Mercure subscriber infrastructure:** Only publisher; client-side EventSource handles subscription
