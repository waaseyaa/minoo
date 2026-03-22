# Auth Email System Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Wire SendGrid email delivery into Minoo's auth flows — password reset, email verification on signup, and welcome email.

**Architecture:** New `AuthMailer` service wraps `MailService` + Twig `Environment` to render and send email templates. New `EmailVerificationService` manages verification tokens (mirrors `PasswordResetService`). Registration flow changes to require email verification before account activation.

**Tech Stack:** PHP 8.4, SendGrid API, Twig 3, PHPUnit 10.5, in-memory SQLite for tests.

**Spec:** `docs/superpowers/specs/2026-03-22-auth-email-system-design.md`

**Issues:** #455, #456, #457, #458

---

## File Map

| File | Action | Responsibility |
|------|--------|---------------|
| `src/Support/EmailVerificationService.php` | Create | Token create/validate/consume for email verification |
| `tests/Minoo/Unit/Support/EmailVerificationServiceTest.php` | Create | Unit tests for EmailVerificationService |
| `src/Support/MailService.php` | Modify | Remove `final` keyword (PHPUnit cannot mock final classes) |
| `src/Support/AuthMailer.php` | Create | Renders Twig email templates, sends via MailService |
| `tests/Minoo/Unit/Support/AuthMailerTest.php` | Create | Unit tests for AuthMailer (mocked MailService) |
| `templates/email/password-reset.html.twig` | Create | Password reset HTML email |
| `templates/email/password-reset.txt.twig` | Create | Password reset plain-text email |
| `templates/email/email-verification.html.twig` | Create | Email verification HTML email |
| `templates/email/email-verification.txt.twig` | Create | Email verification plain-text email |
| `templates/email/welcome.html.twig` | Create | Welcome HTML email |
| `templates/email/welcome.txt.twig` | Create | Welcome plain-text email |
| `templates/auth/check-email.html.twig` | Create | "Check your inbox" confirmation page |
| `templates/auth/verify-email.html.twig` | Create | Verification success/error page |
| `templates/auth/forgot-password.html.twig` | Modify | Remove reset_url display block |
| `src/Controller/AuthController.php` | Modify | Wire AuthMailer into flows, add verifyEmail() |
| `tests/Minoo/Unit/Controller/AuthControllerTest.php` | Modify | Update tests for revised flows |
| `src/Provider/MailServiceProvider.php` | Modify | Register AuthMailer singleton |
| `src/Provider/AuthServiceProvider.php` | Modify | Add /verify-email route |
| `config/waaseyaa.php` | Modify | Add `base_url` to mail config |
| `resources/lang/en.php` | Modify | Add new auth translation strings |
| `resources/lang/oj.php` | Modify | Add new auth translation strings (Ojibwe) |

---

### Task 1: EmailVerificationService (#456)

**Files:**
- Create: `src/Support/EmailVerificationService.php`
- Create: `tests/Minoo/Unit/Support/EmailVerificationServiceTest.php`

- [ ] **Step 1: Write the failing tests**

Create `tests/Minoo/Unit/Support/EmailVerificationServiceTest.php`:

```php
<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Support;

use Minoo\Support\EmailVerificationService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(EmailVerificationService::class)]
final class EmailVerificationServiceTest extends TestCase
{
    private \PDO $pdo;
    private EmailVerificationService $service;

    protected function setUp(): void
    {
        $this->pdo = new \PDO('sqlite::memory:');
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->service = new EmailVerificationService($this->pdo);
    }

    #[Test]
    public function create_token_returns_64_char_hex(): void
    {
        $token = $this->service->createToken('user-1');
        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $token);
    }

    #[Test]
    public function validate_token_returns_user_id(): void
    {
        $token = $this->service->createToken('user-42');
        $result = $this->service->validateToken($token);
        self::assertSame('user-42', $result);
    }

    #[Test]
    public function validate_token_returns_null_for_invalid_token(): void
    {
        $result = $this->service->validateToken('nonexistent');
        self::assertNull($result);
    }

    #[Test]
    public function create_token_invalidates_previous_token_for_same_user(): void
    {
        $token1 = $this->service->createToken('user-1');
        $token2 = $this->service->createToken('user-1');

        self::assertNull($this->service->validateToken($token1));
        self::assertSame('user-1', $this->service->validateToken($token2));
    }

    #[Test]
    public function consume_token_marks_it_as_used(): void
    {
        $token = $this->service->createToken('user-1');
        $this->service->consumeToken($token);
        self::assertNull($this->service->validateToken($token));
    }

    #[Test]
    public function expired_token_returns_null(): void
    {
        // Insert an already-expired token directly
        $this->service->createToken('user-1'); // ensures table exists
        $this->pdo->exec("DELETE FROM email_verification_tokens");
        $this->pdo->prepare(
            'INSERT INTO email_verification_tokens (token, user_id, expires_at, used_at) VALUES (:token, :uid, :expires, NULL)'
        )->execute([
            'token' => 'expired-token-hex',
            'uid' => 'user-1',
            'expires' => time() - 1,
        ]);

        self::assertNull($this->service->validateToken('expired-token-hex'));
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Support/EmailVerificationServiceTest.php`
Expected: FAIL — class `EmailVerificationService` not found.

- [ ] **Step 3: Implement EmailVerificationService**

Create `src/Support/EmailVerificationService.php`:

```php
<?php

declare(strict_types=1);

namespace Minoo\Support;

final class EmailVerificationService
{
    private bool $tableEnsured = false;

    public function __construct(private readonly \PDO $pdo) {}

    /**
     * Create a verification token for a user. Invalidates any existing token.
     * Returns 64-char hex token.
     */
    public function createToken(int|string $userId): string
    {
        $this->ensureTable();
        $stmt = $this->pdo->prepare('DELETE FROM email_verification_tokens WHERE user_id = :uid');
        $stmt->execute(['uid' => $userId]);

        $token = bin2hex(random_bytes(32));
        $stmt = $this->pdo->prepare(
            'INSERT INTO email_verification_tokens (token, user_id, expires_at, used_at) VALUES (:token, :uid, :expires, NULL)'
        );
        $stmt->execute([
            'token' => $token,
            'uid' => $userId,
            'expires' => time() + 86400, // 24 hours
        ]);

        return $token;
    }

    /**
     * Validate a token. Returns user_id if valid, null otherwise.
     */
    public function validateToken(string $token): int|string|null
    {
        $this->ensureTable();
        $stmt = $this->pdo->prepare(
            'SELECT user_id FROM email_verification_tokens WHERE token = :token AND expires_at > :now AND used_at IS NULL'
        );
        $stmt->execute(['token' => $token, 'now' => time()]);
        $result = $stmt->fetchColumn();

        return $result !== false ? $result : null;
    }

    /**
     * Mark a token as consumed.
     */
    public function consumeToken(string $token): void
    {
        $this->ensureTable();
        $stmt = $this->pdo->prepare('UPDATE email_verification_tokens SET used_at = :now WHERE token = :token');
        $stmt->execute(['token' => $token, 'now' => time()]);
    }

    private function ensureTable(): void
    {
        if ($this->tableEnsured) {
            return;
        }
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS email_verification_tokens ('
            . 'token TEXT PRIMARY KEY, '
            . 'user_id TEXT NOT NULL, '
            . 'expires_at INTEGER NOT NULL, '
            . 'used_at INTEGER'
            . ')'
        );
        $this->tableEnsured = true;
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Support/EmailVerificationServiceTest.php`
Expected: 6 tests, 7 assertions, all PASS.

- [ ] **Step 5: Commit**

```bash
git add src/Support/EmailVerificationService.php tests/Minoo/Unit/Support/EmailVerificationServiceTest.php
git commit -m "feat(#456): EmailVerificationService with token create/validate/consume"
```

---

### Task 2: Email Twig Templates (#455)

**Files:**
- Create: `templates/email/password-reset.html.twig`
- Create: `templates/email/password-reset.txt.twig`
- Create: `templates/email/email-verification.html.twig`
- Create: `templates/email/email-verification.txt.twig`
- Create: `templates/email/welcome.html.twig`
- Create: `templates/email/welcome.txt.twig`

Content tone: community voice, "we"/"you", warm and direct, per `docs/content-tone-guide.md`.

- [ ] **Step 1: Create password reset email templates**

Create `templates/email/password-reset.html.twig`:

```html
<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><title>Reset your Minoo password</title></head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; max-width: 600px; margin: 0 auto; padding: 24px; color: #1a1a1a;">
  <h1 style="font-size: 24px; margin-bottom: 16px;">Reset your password</h1>
  <p>Hi {{ user_name }},</p>
  <p>We received a request to reset your Minoo password. Click the link below to choose a new one:</p>
  <p style="margin: 24px 0;">
    <a href="{{ reset_url }}" style="background: #2d5016; color: #fff; padding: 12px 24px; text-decoration: none; border-radius: 6px; display: inline-block;">Reset Password</a>
  </p>
  <p>Or copy this link: {{ reset_url }}</p>
  <p>This link expires in 1 hour.</p>
  <p>If you didn't request this, you can safely ignore this email — your password won't change.</p>
  <p style="margin-top: 32px; color: #666; font-size: 14px;">— Minoo</p>
</body>
</html>
```

Create `templates/email/password-reset.txt.twig`:

```
Reset your password
===================

Hi {{ user_name }},

We received a request to reset your Minoo password. Visit this link to choose a new one:

{{ reset_url }}

This link expires in 1 hour.

If you didn't request this, you can safely ignore this email — your password won't change.

— Minoo
```

- [ ] **Step 2: Create email verification templates**

Create `templates/email/email-verification.html.twig`:

```html
<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><title>Verify your email for Minoo</title></head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; max-width: 600px; margin: 0 auto; padding: 24px; color: #1a1a1a;">
  <h1 style="font-size: 24px; margin-bottom: 16px;">Verify your email</h1>
  <p>Hi {{ user_name }},</p>
  <p>Thanks for signing up for Minoo. Please verify your email address so we can activate your account:</p>
  <p style="margin: 24px 0;">
    <a href="{{ verify_url }}" style="background: #2d5016; color: #fff; padding: 12px 24px; text-decoration: none; border-radius: 6px; display: inline-block;">Verify Email</a>
  </p>
  <p>Or copy this link: {{ verify_url }}</p>
  <p>This link expires in 24 hours.</p>
  <p style="margin-top: 32px; color: #666; font-size: 14px;">— Minoo</p>
</body>
</html>
```

Create `templates/email/email-verification.txt.twig`:

```
Verify your email
=================

Hi {{ user_name }},

Thanks for signing up for Minoo. Please verify your email address so we can activate your account:

{{ verify_url }}

This link expires in 24 hours.

— Minoo
```

- [ ] **Step 3: Create welcome email templates**

Create `templates/email/welcome.html.twig`:

```html
<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><title>Welcome to Minoo</title></head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; max-width: 600px; margin: 0 auto; padding: 24px; color: #1a1a1a;">
  <h1 style="font-size: 24px; margin-bottom: 16px;">Welcome to Minoo</h1>
  <p>Hi {{ user_name }},</p>
  <p>Your email is verified and your account is ready. You're part of the community now.</p>
  <p>Here's what you can do:</p>
  <ul>
    <li>Explore Teachings, Events, and Groups shared by the community</li>
    <li>Connect with community members</li>
    <li>Share your own stories and updates</li>
  </ul>
  <p style="margin: 24px 0;">
    <a href="{{ home_url }}" style="background: #2d5016; color: #fff; padding: 12px 24px; text-decoration: none; border-radius: 6px; display: inline-block;">Visit Minoo</a>
  </p>
  <p style="margin-top: 32px; color: #666; font-size: 14px;">— Minoo</p>
</body>
</html>
```

Create `templates/email/welcome.txt.twig`:

```
Welcome to Minoo
================

Hi {{ user_name }},

Your email is verified and your account is ready. You're part of the community now.

Here's what you can do:

- Explore Teachings, Events, and Groups shared by the community
- Connect with community members
- Share your own stories and updates

Visit Minoo: {{ home_url }}

— Minoo
```

- [ ] **Step 4: Commit**

```bash
git add templates/email/
git commit -m "feat(#455): Twig email templates for password reset, verification, welcome"
```

---

### Task 3: AuthMailer Service (#455)

**Files:**
- Modify: `src/Support/MailService.php` (remove `final` — PHPUnit cannot mock final classes)
- Create: `src/Support/AuthMailer.php`
- Create: `tests/Minoo/Unit/Support/AuthMailerTest.php`
- Modify: `src/Provider/MailServiceProvider.php`
- Modify: `config/waaseyaa.php`

- [ ] **Step 1: Remove `final` from MailService**

In `src/Support/MailService.php`, change line 10 from:

```php
final class MailService
```

to:

```php
class MailService
```

This is required because `AuthMailerTest` needs to mock `MailService`. See CLAUDE.md gotcha: "Don't use `final` on services that need mocking."

- [ ] **Step 2: Add base_url to mail config**

In `config/waaseyaa.php`, add `base_url` to the `mail` array:

```php
'mail' => [
    'sendgrid_api_key' => getenv('SENDGRID_API_KEY') ?: '',
    'from_address' => getenv('MAIL_FROM_ADDRESS') ?: 'hello@minoo.live',
    'from_name' => getenv('MAIL_FROM_NAME') ?: 'Minoo',
    'base_url' => getenv('MINOO_BASE_URL') ?: 'https://minoo.live',
],
```

- [ ] **Step 3: Write the failing tests**

Create `tests/Minoo/Unit/Support/AuthMailerTest.php`:

```php
<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Support;

use Minoo\Support\AuthMailer;
use Minoo\Support\MailService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Waaseyaa\User\User;

#[CoversClass(AuthMailer::class)]
final class AuthMailerTest extends TestCase
{
    private MailService $mailService;
    private Environment $twig;
    private AuthMailer $mailer;

    protected function setUp(): void
    {
        $this->mailService = $this->createMock(MailService::class);
        $this->twig = new Environment(new ArrayLoader([
            'email/password-reset.html.twig' => '<p>Reset: {{ reset_url }}</p>',
            'email/password-reset.txt.twig' => 'Reset: {{ reset_url }}',
            'email/email-verification.html.twig' => '<p>Verify: {{ verify_url }}</p>',
            'email/email-verification.txt.twig' => 'Verify: {{ verify_url }}',
            'email/welcome.html.twig' => '<p>Welcome {{ user_name }}</p>',
            'email/welcome.txt.twig' => 'Welcome {{ user_name }}',
        ]));
        $this->mailer = new AuthMailer($this->mailService, $this->twig, 'https://minoo.test');
    }

    private function createUserMock(string $id, string $name, string $email): User
    {
        $user = $this->createMock(User::class);
        $user->method('id')->willReturn($id);
        $user->method('get')->willReturnMap([
            ['name', $name],
            ['mail', $email],
        ]);
        return $user;
    }

    #[Test]
    public function send_password_reset_calls_mail_service(): void
    {
        $user = $this->createUserMock('1', 'Alice', 'alice@example.com');

        $this->mailService->expects(self::once())
            ->method('sendHtml')
            ->with(
                'alice@example.com',
                'Reset your Minoo password',
                self::stringContains('https://minoo.test/reset-password?token=abc123'),
                self::stringContains('https://minoo.test/reset-password?token=abc123'),
            )
            ->willReturn(202);

        $this->mailer->sendPasswordReset($user, 'abc123');
    }

    #[Test]
    public function send_email_verification_calls_mail_service(): void
    {
        $user = $this->createUserMock('2', 'Bob', 'bob@example.com');

        $this->mailService->expects(self::once())
            ->method('sendHtml')
            ->with(
                'bob@example.com',
                'Verify your email for Minoo',
                self::stringContains('https://minoo.test/verify-email?token=def456'),
                self::stringContains('https://minoo.test/verify-email?token=def456'),
            )
            ->willReturn(202);

        $this->mailer->sendEmailVerification($user, 'def456');
    }

    #[Test]
    public function send_welcome_calls_mail_service(): void
    {
        $user = $this->createUserMock('3', 'Carol', 'carol@example.com');

        $this->mailService->expects(self::once())
            ->method('sendHtml')
            ->with(
                'carol@example.com',
                'Welcome to Minoo',
                self::stringContains('Welcome Carol'),
                self::stringContains('Welcome Carol'),
            )
            ->willReturn(202);

        $this->mailer->sendWelcome($user);
    }
}
```

- [ ] **Step 4: Run tests to verify they fail**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Support/AuthMailerTest.php`
Expected: FAIL — class `AuthMailer` not found.

- [ ] **Step 5: Implement AuthMailer**

Create `src/Support/AuthMailer.php`:

```php
<?php

declare(strict_types=1);

namespace Minoo\Support;

use Twig\Environment;
use Waaseyaa\User\User;

final class AuthMailer
{
    public function __construct(
        private readonly MailService $mail,
        private readonly Environment $twig,
        private readonly string $baseUrl,
    ) {}

    public function sendPasswordReset(User $user, string $token): void
    {
        $vars = [
            'user_name' => $user->get('name'),
            'reset_url' => $this->baseUrl . '/reset-password?token=' . $token,
        ];

        $html = $this->twig->render('email/password-reset.html.twig', $vars);
        $text = $this->twig->render('email/password-reset.txt.twig', $vars);

        $this->mail->sendHtml(
            $user->get('mail'),
            'Reset your Minoo password',
            $html,
            $text,
        );
    }

    public function sendEmailVerification(User $user, string $token): void
    {
        $vars = [
            'user_name' => $user->get('name'),
            'verify_url' => $this->baseUrl . '/verify-email?token=' . $token,
        ];

        $html = $this->twig->render('email/email-verification.html.twig', $vars);
        $text = $this->twig->render('email/email-verification.txt.twig', $vars);

        $this->mail->sendHtml(
            $user->get('mail'),
            'Verify your email for Minoo',
            $html,
            $text,
        );
    }

    public function sendWelcome(User $user): void
    {
        $vars = [
            'user_name' => $user->get('name'),
            'home_url' => $this->baseUrl,
        ];

        $html = $this->twig->render('email/welcome.html.twig', $vars);
        $text = $this->twig->render('email/welcome.txt.twig', $vars);

        $this->mail->sendHtml(
            $user->get('mail'),
            'Welcome to Minoo',
            $html,
            $text,
        );
    }
}
```

- [ ] **Step 6: Run tests to verify they pass**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Support/AuthMailerTest.php`
Expected: 3 tests, 3 assertions, all PASS.

- [ ] **Step 7: Register AuthMailer in MailServiceProvider**

In `src/Provider/MailServiceProvider.php`, add to `register()`:

```php
$this->singleton(AuthMailer::class, fn () => new AuthMailer(
    $this->container->get(MailService::class),
    $this->container->get(Environment::class),
    $config['base_url'] ?? 'https://minoo.live',
));
```

Add the import: `use Minoo\Support\AuthMailer;`

- [ ] **Step 8: Commit**

```bash
git add src/Support/MailService.php src/Support/AuthMailer.php tests/Minoo/Unit/Support/AuthMailerTest.php src/Provider/MailServiceProvider.php config/waaseyaa.php
git commit -m "feat(#455): AuthMailer service with Twig rendering and DI registration"
```

---

### Task 4: Wire Password Reset Email (#458)

**Files:**
- Modify: `src/Controller/AuthController.php` (lines ~209-252, `submitForgotPassword`)
- Modify: `templates/auth/forgot-password.html.twig` (lines 10-14)
- Modify: `tests/Minoo/Unit/Controller/AuthControllerTest.php`
- Modify: `resources/lang/en.php`
- Modify: `resources/lang/oj.php`

- [ ] **Step 1: Update language strings**

In `resources/lang/en.php`, update the existing reset strings:

```php
'auth.reset_link_generated' => 'If an account exists with that email, we've sent a password reset link.',
```

Remove or repurpose `auth.reset_submitted` since both cases now show the same message.

In `resources/lang/oj.php`, add the corresponding translation.

- [ ] **Step 2: Update AuthController::submitForgotPassword**

Inject `AuthMailer` into `AuthController` constructor:

```php
public function __construct(
    private readonly EntityTypeManager $entityTypeManager,
    private readonly Environment $twig,
    private readonly AuthMailer $authMailer,
) {}
```

Modify `submitForgotPassword()` — after generating the token (line ~239-240), email it instead of passing to template:

```php
if ($user !== null) {
    $resetService = $this->createPasswordResetService();
    $token = $resetService->createToken($user->id());
    $this->authMailer->sendPasswordReset($user, $token);
}

// Always show the same message (prevents user enumeration)
$html = $this->twig->render('auth/forgot-password.html.twig', [
    'submitted' => true,
    'values' => compact('email'),
]);

return new SsrResponse(content: $html);
```

- [ ] **Step 3: Update forgot-password template**

In `templates/auth/forgot-password.html.twig`, remove the `reset_url` block (lines 10-14). Replace with:

```twig
{% if submitted is defined and submitted %}
  <div class="form__success" role="status">
    <p>{{ trans('auth.reset_link_generated') }}</p>
  </div>
{% endif %}
```

- [ ] **Step 4: Update AuthControllerTest**

The constructor now takes 3 args. Update `setUp()` in the test to create and inject an `AuthMailer` mock:

```php
use Minoo\Support\AuthMailer;

// Add to class properties:
private AuthMailer $authMailer;

// In setUp(), add:
$this->authMailer = $this->createMock(AuthMailer::class);

// Update ALL controller instantiations from:
$controller = new AuthController($this->entityTypeManager, $this->twig);
// to:
$controller = new AuthController($this->entityTypeManager, $this->twig, $this->authMailer);
```

Add test for forgot-password emailing:

```php
#[Test]
public function submit_forgot_password_sends_reset_email_for_valid_user(): void
{
    $user = $this->createMock(User::class);
    $user->method('id')->willReturn('1');

    $this->query->method('condition')->willReturnSelf();
    $this->query->method('execute')->willReturn(['1']);
    $this->storage->method('load')->willReturn($user);

    $this->authMailer->expects(self::once())
        ->method('sendPasswordReset')
        ->with($user, self::matchesRegularExpression('/^[0-9a-f]{64}$/'));

    $request = Request::create('/forgot-password', 'POST', ['email' => 'alice@example.com']);
    $controller = new AuthController($this->entityTypeManager, $this->twig, $this->authMailer);
    $response = $controller->submitForgotPassword([], [], $this->account, $request);

    self::assertSame(200, $response->statusCode);
}

#[Test]
public function submit_forgot_password_does_not_send_email_for_unknown_user(): void
{
    $this->query->method('condition')->willReturnSelf();
    $this->query->method('execute')->willReturn([]);

    $this->authMailer->expects(self::never())
        ->method('sendPasswordReset');

    $request = Request::create('/forgot-password', 'POST', ['email' => 'nobody@example.com']);
    $controller = new AuthController($this->entityTypeManager, $this->twig, $this->authMailer);
    $response = $controller->submitForgotPassword([], [], $this->account, $request);

    self::assertSame(200, $response->statusCode);
}
```

- [ ] **Step 5: Run all tests**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Controller/AuthControllerTest.php`
Expected: All existing + new tests PASS.

- [ ] **Step 6: Commit**

```bash
git add src/Controller/AuthController.php templates/auth/forgot-password.html.twig tests/Minoo/Unit/Controller/AuthControllerTest.php resources/lang/en.php resources/lang/oj.php
git commit -m "feat(#458): email password reset links instead of displaying in browser"
```

---

### Task 5: Email Verification on Signup (#457)

**Files:**
- Modify: `src/Controller/AuthController.php` (`submitRegister`, new `verifyEmail`)
- Modify: `src/Provider/AuthServiceProvider.php` (add route)
- Create: `templates/auth/check-email.html.twig`
- Create: `templates/auth/verify-email.html.twig`
- Modify: `tests/Minoo/Unit/Controller/AuthControllerTest.php`
- Modify: `resources/lang/en.php`
- Modify: `resources/lang/oj.php`

- [ ] **Step 1: Add language strings**

In `resources/lang/en.php`, add:

```php
'auth.check_email_title' => 'Check Your Email',
'auth.check_email_message' => 'We sent a verification link to your email. Click it to activate your account.',
'auth.check_email_note' => 'The link expires in 24 hours. Check your spam folder if you don't see it.',
'auth.verify_title' => 'Email Verified',
'auth.verify_success' => 'Your email is verified and your account is active. Welcome to Minoo.',
'auth.verify_error_title' => 'Verification Failed',
'auth.verify_error_invalid' => 'This verification link is invalid or has expired.',
'auth.verify_error_user' => 'User account not found.',
```

Add corresponding Ojibwe translations in `resources/lang/oj.php`.

- [ ] **Step 2: Create check-email template**

Create `templates/auth/check-email.html.twig`:

```twig
{% extends "base.html.twig" %}

{% block title %}{{ trans('auth.check_email_title') }} — Minoo{% endblock %}

{% block content %}
<section class="content-section flow-lg">
  <h1>{{ trans('auth.check_email_title') }}</h1>
  <div class="form__success" role="status">
    <p>{{ trans('auth.check_email_message') }}</p>
    <p>{{ trans('auth.check_email_note') }}</p>
  </div>
  <p class="form__link"><a href="{{ lang_url('/login') }}">{{ trans('auth.back_to_login') }}</a></p>
</section>
{% endblock %}
```

- [ ] **Step 3: Create verify-email template**

Create `templates/auth/verify-email.html.twig`:

```twig
{% extends "base.html.twig" %}

{% block title %}{{ verified ? trans('auth.verify_title') : trans('auth.verify_error_title') }} — Minoo{% endblock %}

{% block content %}
<section class="content-section flow-lg">
  {% if verified %}
    <h1>{{ trans('auth.verify_title') }}</h1>
    <div class="form__success" role="status">
      <p>{{ trans('auth.verify_success') }}</p>
    </div>
  {% else %}
    <h1>{{ trans('auth.verify_error_title') }}</h1>
    <div class="form__error" role="alert">
      <p>{{ error }}</p>
    </div>
    <p class="form__link"><a href="{{ lang_url('/register') }}">{{ trans('auth.register_title') }}</a></p>
  {% endif %}
</section>
{% endblock %}
```

- [ ] **Step 4: Revise submitRegister in AuthController**

Replace the current registration logic (after validation passes) with:

```php
/** @var User $user */
$user = $storage->create([
    'name' => $name,
    'mail' => $email,
    'status' => false,  // Inactive until email verified
    'created' => time(),
    'roles' => [],
    'permissions' => [],
]);
$user->setRawPassword($password);

if ($phone !== '') {
    $user->set('phone', $phone);
}

$storage->save($user);

// Send verification email
$verifyService = $this->createEmailVerificationService();
$token = $verifyService->createToken($user->id());
$this->authMailer->sendEmailVerification($user, $token);

// Do NOT log in — redirect to check-email page
$html = $this->twig->render('auth/check-email.html.twig', []);

return new SsrResponse(content: $html);
```

Key changes from current:
- `status: false` (was `true`)
- No volunteer creation
- No `roles`/`permissions` assignment
- No `$_SESSION['waaseyaa_uid']` — no login until verified
- Redirect to check-email page, not dashboard

- [ ] **Step 5: Add verifyEmail method to AuthController**

```php
public function verifyEmail(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
{
    $token = (string) $request->query->get('token', '');

    if ($token === '') {
        $html = $this->twig->render('auth/verify-email.html.twig', [
            'verified' => false,
            'error' => trans('auth.verify_error_invalid'),
        ]);
        return new SsrResponse(content: $html);
    }

    $verifyService = $this->createEmailVerificationService();
    $userId = $verifyService->validateToken($token);

    if ($userId === null) {
        $html = $this->twig->render('auth/verify-email.html.twig', [
            'verified' => false,
            'error' => trans('auth.verify_error_invalid'),
        ]);
        return new SsrResponse(content: $html);
    }

    $storage = $this->entityTypeManager->getStorage('user');
    /** @var User|null $user */
    $user = $storage->load($userId);

    if ($user === null) {
        $html = $this->twig->render('auth/verify-email.html.twig', [
            'verified' => false,
            'error' => trans('auth.verify_error_user'),
        ]);
        return new SsrResponse(content: $html);
    }

    $user->set('status', true);
    $storage->save($user);
    $verifyService->consumeToken($token);

    $this->authMailer->sendWelcome($user);

    $_SESSION['waaseyaa_uid'] = $user->id();

    Flash::success(trans('auth.verify_success'));

    $html = $this->twig->render('auth/verify-email.html.twig', [
        'verified' => true,
    ]);
    return new SsrResponse(content: $html);
}
```

- [ ] **Step 6: Add helper method for EmailVerificationService**

Add to `AuthController`:

```php
private function createEmailVerificationService(): EmailVerificationService
{
    $projectRoot = dirname(__DIR__, 2);
    $dbPath = getenv('WAASEYAA_DB') ?: $projectRoot . '/storage/waaseyaa.sqlite';
    $pdo = new \PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

    return new EmailVerificationService($pdo);
}
```

- [ ] **Step 7: Register the route in AuthServiceProvider**

Add to `routes()` method in `src/Provider/AuthServiceProvider.php`:

```php
$router->addRoute(
    'auth.verify_email',
    RouteBuilder::create('/verify-email')
        ->controller('Minoo\Controller\AuthController::verifyEmail')
        ->allowAll()
        ->render()
        ->methods('GET')
        ->build(),
);
```

- [ ] **Step 8: Update AuthControllerTest**

Update the existing `submit_register_creates_volunteer_entity_for_new_account` test — rename and rewrite it since volunteer creation is removed:

```php
#[Test]
public function submit_register_creates_inactive_user_and_sends_verification_email(): void
{
    $user = $this->createMock(User::class);
    $user->method('id')->willReturn('new-1');

    $this->query->method('condition')->willReturnSelf();
    $this->query->method('execute')->willReturn([]); // no existing user
    $this->storage->method('create')->willReturn($user);

    $this->authMailer->expects(self::once())
        ->method('sendEmailVerification')
        ->with($user, self::matchesRegularExpression('/^[0-9a-f]{64}$/'));

    $request = Request::create('/register', 'POST', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'securepass123',
    ]);

    $controller = new AuthController($this->entityTypeManager, $this->twig, $this->authMailer);
    $response = $controller->submitRegister([], [], $this->account, $request);

    // Should NOT redirect to dashboard (no login)
    self::assertSame(200, $response->statusCode);
    // Session should NOT be set
    self::assertArrayNotHasKey('waaseyaa_uid', $_SESSION ?? []);
}
```

Add tests for `verifyEmail`:

```php
#[Test]
public function verify_email_with_missing_token_shows_error(): void
{
    $request = Request::create('/verify-email', 'GET');
    $controller = new AuthController($this->entityTypeManager, $this->twig, $this->authMailer);
    $response = $controller->verifyEmail([], [], $this->account, $request);

    self::assertSame(200, $response->statusCode);
    self::assertStringContainsString('invalid or has expired', $response->content);
}

#[Test]
public function verify_email_with_valid_token_activates_user_and_sends_welcome(): void
{
    $user = $this->createMock(User::class);
    $user->method('id')->willReturn('1');

    $this->storage->method('load')->willReturn($user);

    $this->authMailer->expects(self::once())
        ->method('sendWelcome')
        ->with($user);

    // This test will need a real or mocked EmailVerificationService.
    // The controller creates it internally, so use in-memory SQLite via WAASEYAA_DB env.
    putenv('WAASEYAA_DB=:memory:');

    $request = Request::create('/verify-email?token=test', 'GET', ['token' => 'test']);
    $controller = new AuthController($this->entityTypeManager, $this->twig, $this->authMailer);

    // Note: This test needs a pre-seeded token. Since the controller creates its own
    // EmailVerificationService internally, the token won't exist in the fresh :memory: DB.
    // The implementing agent should either:
    // (a) Inject EmailVerificationService via constructor (preferred refactor), or
    // (b) Pre-seed the token in the :memory: DB before calling verifyEmail.
    // Option (a) is cleaner — add EmailVerificationService as an optional 4th constructor param.

    putenv('WAASEYAA_DB'); // reset
}
```

**Important note for implementer:** The `verifyEmail` tests expose that `createEmailVerificationService()` creates its own PDO connection internally, making it hard to test. The recommended fix: inject `EmailVerificationService` as a constructor parameter (same pattern as `AuthMailer`), registered in `AuthServiceProvider`. This also applies to the existing `createPasswordResetService()` — but that refactor is out of scope for this PR.

- [ ] **Step 9: Run all tests**

Run: `./vendor/bin/phpunit`
Expected: All tests PASS. Delete `storage/framework/packages.php` first if needed.

- [ ] **Step 10: Commit**

```bash
git add src/Controller/AuthController.php src/Provider/AuthServiceProvider.php templates/auth/check-email.html.twig templates/auth/verify-email.html.twig tests/Minoo/Unit/Controller/AuthControllerTest.php resources/lang/en.php resources/lang/oj.php
git commit -m "feat(#457): email verification on signup with check-email and verify-email flows"
```

---

### Task 6: Full Test Suite + Cleanup

**Files:**
- All modified files from Tasks 1-5

- [ ] **Step 1: Delete stale manifest cache**

```bash
rm -f storage/framework/packages.php
```

- [ ] **Step 2: Run full test suite**

Run: `./vendor/bin/phpunit`
Expected: All tests PASS (existing + new).

- [ ] **Step 3: Fix any failures**

Address any test failures from the changes. Common issues:
- `AuthControllerTest` tests that expect old registration behavior (volunteer creation, immediate login)
- Tests that instantiate `AuthController` without the new `AuthMailer` parameter

- [ ] **Step 4: Final commit if any fixes needed**

```bash
git add -u
git commit -m "fix(#457): resolve test failures from auth email integration"
```

---

### Task 7: Integration Test — Registration to Verification (#457)

**Files:**
- Create: `tests/Minoo/Integration/AuthEmailFlowTest.php`

This test boots the full kernel and exercises the registration → verification flow end-to-end with in-memory SQLite.

- [ ] **Step 1: Write integration test**

Create `tests/Minoo/Integration/AuthEmailFlowTest.php`:

```php
<?php

declare(strict_types=1);

namespace Minoo\Tests\Integration;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\HttpKernel;

#[CoversNothing]
final class AuthEmailFlowTest extends TestCase
{
    private HttpKernel $kernel;

    protected function setUp(): void
    {
        putenv('WAASEYAA_DB=:memory:');
        $projectRoot = dirname(__DIR__, 3);
        $this->kernel = new HttpKernel($projectRoot);

        // Boot kernel (protected method — use reflection like other integration tests)
        $ref = new \ReflectionMethod($this->kernel, 'boot');
        $ref->setAccessible(true);
        $ref->invoke($this->kernel);
    }

    protected function tearDown(): void
    {
        putenv('WAASEYAA_DB');
    }

    #[Test]
    public function new_user_is_inactive_until_email_verified(): void
    {
        // 1. Create a user with status=false (simulating registration)
        $entityTypeManager = $this->kernel->getContainer()->get(\Waaseyaa\Entity\EntityTypeManager::class);
        $storage = $entityTypeManager->getStorage('user');

        $user = $storage->create([
            'name' => 'Integration Test User',
            'mail' => 'integration@example.com',
            'status' => false,
            'created' => time(),
            'roles' => [],
            'permissions' => [],
        ]);
        $user->setRawPassword('testpass123');
        $storage->save($user);

        // Verify user is inactive
        $loaded = $storage->load($user->id());
        self::assertFalse((bool) $loaded->get('status'));

        // 2. Create verification token
        $pdo = new \PDO('sqlite::memory:');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $verifyService = new \Minoo\Support\EmailVerificationService($pdo);
        $token = $verifyService->createToken($user->id());

        // 3. Validate token returns user ID
        $userId = $verifyService->validateToken($token);
        self::assertSame($user->id(), $userId);

        // 4. Activate user
        $loaded->set('status', true);
        $storage->save($loaded);
        $verifyService->consumeToken($token);

        // 5. Verify user is now active and token is consumed
        $reloaded = $storage->load($user->id());
        self::assertTrue((bool) $reloaded->get('status'));
        self::assertNull($verifyService->validateToken($token));
    }
}
```

- [ ] **Step 2: Run integration test**

Run: `./vendor/bin/phpunit tests/Minoo/Integration/AuthEmailFlowTest.php`
Expected: 1 test, 4 assertions, PASS.

- [ ] **Step 3: Run full suite one final time**

Run: `./vendor/bin/phpunit`
Expected: All tests PASS.

- [ ] **Step 4: Commit**

```bash
git add tests/Minoo/Integration/AuthEmailFlowTest.php
git commit -m "test(#457): integration test for registration-to-verification flow"
```
