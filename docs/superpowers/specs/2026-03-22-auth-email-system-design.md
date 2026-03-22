# Auth Email System Design

**Date:** 2026-03-22
**Milestone:** Social Feed v1 (#40)
**Status:** Approved

## Summary

Wire SendGrid email delivery into Minoo's authentication flows: password reset, email verification on signup, and welcome email after verification. Introduces an `AuthMailer` service and Twig-based email templates.

## Current State

- `MailService` exists (SendGrid wrapper) but is not called from auth flows
- `PasswordResetService` exists (token create/validate/consume) but reset URL is displayed in the browser instead of emailed
- Registration creates an active account immediately with no email verification
- Registration currently auto-creates a volunteer record — this will be removed as part of a separate role model refactor

## Architecture

### New Files

```
src/Support/AuthMailer.php                    # Renders + sends auth emails
src/Support/EmailVerificationService.php      # Token create/validate/consume (24hr expiry)
templates/email/password-reset.html.twig      # Reset link email (HTML)
templates/email/password-reset.txt.twig       # Reset link email (plain text)
templates/email/email-verification.html.twig  # Verify your email (HTML)
templates/email/email-verification.txt.twig   # Verify your email (plain text)
templates/email/welcome.html.twig             # Welcome after verification (HTML)
templates/email/welcome.txt.twig              # Welcome after verification (plain text)
templates/auth/check-email.html.twig          # "Check your inbox" confirmation page
templates/auth/verify-email.html.twig         # Verification success/error states
```

### AuthMailer Service

```php
final class AuthMailer
{
    public function __construct(
        private readonly MailService $mail,
        private readonly Environment $twig,
        private readonly string $baseUrl,
    ) {}

    public function sendPasswordReset(User $user, string $token): void;
    public function sendEmailVerification(User $user, string $token): void;
    public function sendWelcome(User $user): void;
}
```

Each method renders the HTML + plain-text Twig pair and calls `MailService::sendHtml()`. Registered via `MailServiceProvider` (extends existing provider, no new provider needed).

### EmailVerificationService

Structurally identical to `PasswordResetService`:
- Table: `email_verification_tokens` (token, user_id, expires_at, used_at)
- Token: 64-char hex (32 random bytes)
- Expiry: 24 hours (vs 1 hour for password reset)
- Methods: `createToken()`, `validateToken()`, `consumeToken()`

## Flow Changes

### Registration (revised)

1. Submit register form
2. Validate input (name, email, password)
3. Create User with `status: false` (inactive) — **no volunteer record**
4. Generate verification token via `EmailVerificationService::createToken()`
5. `AuthMailer::sendEmailVerification()` — link to `/verify-email?token=xxx`
6. Redirect to `check-email.html.twig` ("We sent you an email")
7. User clicks link → `AuthController::verifyEmail()` validates token
8. Set `status: true`, consume token
9. `AuthMailer::sendWelcome()` sent on successful verification
10. Log user in, redirect to `/`

### Password Reset (revised)

1. Submit forgot-password form (unchanged)
2. Generate token (unchanged)
3. `AuthMailer::sendPasswordReset()` — email the link
4. Template shows generic "If that email exists, we sent a link" (no URL displayed, prevents user enumeration)
5. Rest of reset flow unchanged

### New Routes

| Route | Method | Controller | Purpose |
|-------|--------|------------|---------|
| `/verify-email` | GET | `AuthController::verifyEmail()` | Handle verification link click |

## Email Templates

All emails follow `docs/content-tone-guide.md`:
- Community voice ("we", "you"), warm and direct
- Simple, clean HTML — no heavy framework
- Plain-text fallback for every HTML email
- From: `hello@minoo.live` / "Minoo" (configured in MailServiceProvider)

### Password Reset Email
- Subject: "Reset your Minoo password"
- Body: greeting, reset link, 1-hour expiry note, "didn't request this?" disclaimer

### Email Verification Email
- Subject: "Verify your email for Minoo"
- Body: greeting, verification link, 24-hour expiry note

### Welcome Email
- Subject: "Welcome to Minoo"
- Body: community welcome, what they can do now, link to homepage

## Out of Scope (separate issues)

These are tracked as separate GitHub issues, not part of this email work:

- **Role model refactor:** Remove volunteer auto-creation from registration
- **Volunteer application flow:** User applies, coordinator/admin approves
- **Elder self-identification:** Any user can self-identify as Elder
- **Coordinator/Admin account management:** Create accounts with/without email, promote existing users
- **Role hierarchy access policies:** Enforce new role model in access layer

## Testing

- Unit tests for `AuthMailer` (mock `MailService`, verify correct template + args)
- Unit tests for `EmailVerificationService` (mirrors existing `PasswordResetServiceTest`)
- Integration test for registration → verification flow
- Update existing `AuthControllerTest` for revised flows

## Configuration

Existing config in `config/waaseyaa.php`:
```php
'mail' => [
    'sendgrid_api_key' => getenv('SENDGRID_API_KEY') ?: '',
]
```

New config key needed:
```php
'mail' => [
    'sendgrid_api_key' => getenv('SENDGRID_API_KEY') ?: '',
    'base_url' => getenv('MINOO_BASE_URL') ?: 'https://minoo.live',
]
```

`base_url` is used by `AuthMailer` to construct absolute links in emails.
