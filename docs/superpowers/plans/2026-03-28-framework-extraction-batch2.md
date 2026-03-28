# Framework Extraction — Batch 2 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Extract mail infrastructure, password reset tokens, auth mailer, and user blocking from Minoo into the Waaseyaa framework.

**Architecture:** Three components in dependency order: (1) new `waaseyaa/mail` package with driver interface + SendGrid driver, (2) PasswordResetTokenRepository + AuthMailer added to `waaseyaa/user`, (3) UserBlock entity + access policy + service added to `waaseyaa/user`. Then one Minoo PR swaps all imports.

**Tech Stack:** PHP 8.4, PHPUnit 10.5, SendGrid SDK, Twig, Waaseyaa entity system

**Repos:**
- Framework: `/home/jones/dev/waaseyaa` (repo: `waaseyaa/framework`)
- Application: `/home/jones/dev/minoo` (repo: `waaseyaa/minoo`)

**Lesson from Batch 1:** After framework PRs merge, split repos must be updated before Minoo can `composer update`. Run `git subtree split` + push + tag for every affected package.

---

## File Map

### Framework side (waaseyaa/framework)

**New package — `packages/mail/`:**
- `composer.json`
- `src/MailDriverInterface.php`
- `src/MailMessage.php`
- `src/Driver/SendGridDriver.php`
- `src/MailServiceProvider.php`
- `tests/Unit/MailMessageTest.php`
- `tests/Unit/Driver/SendGridDriverTest.php`

**Added to `packages/user/`:**
- `src/PasswordResetTokenRepository.php`
- `src/AuthMailer.php`
- `src/UserBlock.php`
- `src/UserBlockAccessPolicy.php`
- `src/UserBlockService.php`
- `tests/Unit/PasswordResetTokenRepositoryTest.php`
- `tests/Unit/AuthMailerTest.php`
- `tests/Unit/UserBlockTest.php`
- `tests/Unit/UserBlockAccessPolicyTest.php`
- `tests/Unit/UserBlockServiceTest.php`

**Modified:**
- `packages/user/src/UserServiceProvider.php` — register new services + entity type
- `packages/user/composer.json` — add `waaseyaa/mail` dependency

### Minoo side (waaseyaa/minoo)

**Modified (import swaps):**
- `src/Controller/AuthController.php` — AuthMailer, PasswordResetService imports
- `src/Provider/AuthServiceProvider.php` — PasswordResetService import
- `src/Provider/BlockServiceProvider.php` — UserBlock import → framework, or delete if framework handles it
- `composer.json` — add `waaseyaa/mail`, bump `waaseyaa/user`

**Deleted:**
- `src/Support/MailService.php`
- `src/Support/AuthMailer.php`
- `src/Support/PasswordResetService.php`
- `src/Entity/UserBlock.php`
- `src/Access/BlockAccessPolicy.php`
- `src/Provider/MailServiceProvider.php`
- `tests/Minoo/Unit/Entity/UserBlockTest.php`
- `tests/Minoo/Unit/Access/BlockAccessPolicyTest.php`

---

## Task 1: MailMessage value object

**Files:**
- Create: `packages/mail/src/MailMessage.php`
- Create: `packages/mail/tests/Unit/MailMessageTest.php`

- [ ] **Step 1: Create package directory and composer.json**

Create `packages/mail/composer.json`:

```json
{
    "name": "waaseyaa/mail",
    "description": "Mail abstraction layer with driver interface for Waaseyaa",
    "type": "library",
    "license": "GPL-2.0-or-later",
    "repositories": [
        {
            "type": "path",
            "url": "../foundation"
        }
    ],
    "require": {
        "php": ">=8.4",
        "sendgrid/sendgrid": "^8.1"
    },
    "require-dev": {
        "phpunit/phpunit": "^10.5"
    },
    "autoload": {
        "psr-4": {
            "Waaseyaa\\Mail\\": "src/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "Waaseyaa\\Mail\\Tests\\": "tests/"
        }
    },
    "extra": {
        "waaseyaa": {
            "providers": [
                "Waaseyaa\\Mail\\MailServiceProvider"
            ]
        },
        "branch-alias": {
            "dev-main": "0.1.x-dev"
        }
    },
    "minimum-stability": "dev",
    "prefer-stable": true
}
```

- [ ] **Step 2: Write MailMessage test**

Create `packages/mail/tests/Unit/MailMessageTest.php`:

```php
<?php

declare(strict_types=1);

namespace Waaseyaa\Mail\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Mail\MailMessage;

#[CoversClass(MailMessage::class)]
final class MailMessageTest extends TestCase
{
    #[Test]
    public function creates_plain_text_message(): void
    {
        $msg = new MailMessage(
            from: 'sender@example.com',
            to: 'recipient@example.com',
            subject: 'Test Subject',
            body: 'Hello world',
        );

        $this->assertSame('sender@example.com', $msg->from);
        $this->assertSame('recipient@example.com', $msg->to);
        $this->assertSame('Test Subject', $msg->subject);
        $this->assertSame('Hello world', $msg->body);
        $this->assertSame('', $msg->htmlBody);
        $this->assertSame('', $msg->fromName);
    }

    #[Test]
    public function creates_html_message_with_from_name(): void
    {
        $msg = new MailMessage(
            from: 'sender@example.com',
            to: 'recipient@example.com',
            subject: 'HTML Test',
            body: 'Plain fallback',
            htmlBody: '<h1>Hello</h1>',
            fromName: 'Test App',
        );

        $this->assertSame('<h1>Hello</h1>', $msg->htmlBody);
        $this->assertSame('Test App', $msg->fromName);
    }
}
```

- [ ] **Step 3: Run test to verify it fails**

Run: `cd /home/jones/dev/waaseyaa && ./vendor/bin/phpunit packages/mail/tests/Unit/MailMessageTest.php`
Expected: FAIL — class not found

- [ ] **Step 4: Write MailMessage**

Create `packages/mail/src/MailMessage.php`:

```php
<?php

declare(strict_types=1);

namespace Waaseyaa\Mail;

final class MailMessage
{
    public function __construct(
        public readonly string $from,
        public readonly string $to,
        public readonly string $subject,
        public readonly string $body,
        public readonly string $htmlBody = '',
        public readonly string $fromName = '',
    ) {}
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `cd /home/jones/dev/waaseyaa && ./vendor/bin/phpunit packages/mail/tests/Unit/MailMessageTest.php`
Expected: OK (2 tests)

- [ ] **Step 6: Commit**

```bash
cd /home/jones/dev/waaseyaa
git add packages/mail/
git commit -m "feat(mail): new package with MailMessage value object

Refs: #699"
```

---

## Task 2: MailDriverInterface + SendGridDriver

**Files:**
- Create: `packages/mail/src/MailDriverInterface.php`
- Create: `packages/mail/src/Driver/SendGridDriver.php`
- Create: `packages/mail/tests/Unit/Driver/SendGridDriverTest.php`

- [ ] **Step 1: Write MailDriverInterface**

Create `packages/mail/src/MailDriverInterface.php`:

```php
<?php

declare(strict_types=1);

namespace Waaseyaa\Mail;

interface MailDriverInterface
{
    /**
     * Send an email message.
     *
     * @return int HTTP status code (202 = accepted for SendGrid)
     * @throws \RuntimeException on failure
     */
    public function send(MailMessage $message): int;

    public function isConfigured(): bool;
}
```

- [ ] **Step 2: Write SendGridDriver test**

Create `packages/mail/tests/Unit/Driver/SendGridDriverTest.php`:

```php
<?php

declare(strict_types=1);

namespace Waaseyaa\Mail\Tests\Unit\Driver;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Mail\Driver\SendGridDriver;
use Waaseyaa\Mail\MailMessage;

#[CoversClass(SendGridDriver::class)]
final class SendGridDriverTest extends TestCase
{
    #[Test]
    public function is_not_configured_with_empty_api_key(): void
    {
        $driver = new SendGridDriver('', 'from@example.com', 'App');
        $this->assertFalse($driver->isConfigured());
    }

    #[Test]
    public function is_not_configured_with_empty_from_address(): void
    {
        $driver = new SendGridDriver('key', '', 'App');
        $this->assertFalse($driver->isConfigured());
    }

    #[Test]
    public function is_configured_with_all_values(): void
    {
        $driver = new SendGridDriver('key', 'from@example.com', 'App');
        $this->assertTrue($driver->isConfigured());
    }

    #[Test]
    public function send_throws_when_not_configured(): void
    {
        $driver = new SendGridDriver('', '', '');
        $msg = new MailMessage(
            from: 'a@b.com',
            to: 'c@d.com',
            subject: 'Test',
            body: 'Hello',
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Mail driver is not configured');
        $driver->send($msg);
    }
}
```

- [ ] **Step 3: Run test to verify it fails**

Run: `cd /home/jones/dev/waaseyaa && ./vendor/bin/phpunit packages/mail/tests/Unit/Driver/SendGridDriverTest.php`
Expected: FAIL — class not found

- [ ] **Step 4: Write SendGridDriver**

Create `packages/mail/src/Driver/SendGridDriver.php`:

```php
<?php

declare(strict_types=1);

namespace Waaseyaa\Mail\Driver;

use SendGrid;
use SendGrid\Mail\Mail;
use Waaseyaa\Mail\MailDriverInterface;
use Waaseyaa\Mail\MailMessage;

final class SendGridDriver implements MailDriverInterface
{
    private SendGrid $client;

    public function __construct(
        private readonly string $apiKey,
        private readonly string $fromAddress,
        private readonly string $fromName,
    ) {
        $this->client = new SendGrid($this->apiKey);
    }

    public function send(MailMessage $message): int
    {
        if (!$this->isConfigured()) {
            throw new \RuntimeException('Mail driver is not configured.');
        }

        $email = new Mail();
        $email->setFrom($message->from ?: $this->fromAddress, $message->fromName ?: $this->fromName);
        $email->setSubject($message->subject);
        $email->addTo($message->to);

        if ($message->body !== '') {
            $email->addContent('text/plain', $message->body);
        }

        if ($message->htmlBody !== '') {
            $email->addContent('text/html', $message->htmlBody);
        }

        $response = $this->client->send($email);
        $statusCode = $response->statusCode();

        if ($statusCode >= 400) {
            throw new \RuntimeException(sprintf(
                'SendGrid returned HTTP %d: %s',
                $statusCode,
                $response->body(),
            ));
        }

        return $statusCode;
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== '' && $this->fromAddress !== '';
    }
}
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `cd /home/jones/dev/waaseyaa && ./vendor/bin/phpunit packages/mail/tests/Unit/Driver/SendGridDriverTest.php`
Expected: OK (4 tests)

- [ ] **Step 6: Commit**

```bash
cd /home/jones/dev/waaseyaa
git add packages/mail/
git commit -m "feat(mail): add MailDriverInterface and SendGridDriver

Refs: #699"
```

---

## Task 3: MailServiceProvider

**Files:**
- Create: `packages/mail/src/MailServiceProvider.php`

- [ ] **Step 1: Write MailServiceProvider**

Create `packages/mail/src/MailServiceProvider.php`:

```php
<?php

declare(strict_types=1);

namespace Waaseyaa\Mail;

use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;
use Waaseyaa\Mail\Driver\SendGridDriver;

final class MailServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $config = $this->config['mail'] ?? [];
        $driver = $config['driver'] ?? 'sendgrid';

        $this->singleton(MailDriverInterface::class, fn () => match ($driver) {
            'sendgrid' => new SendGridDriver(
                apiKey: $config['sendgrid_api_key'] ?? '',
                fromAddress: $config['from_address'] ?? '',
                fromName: $config['from_name'] ?? '',
            ),
            default => throw new \RuntimeException("Unsupported mail driver: {$driver}"),
        });
    }
}
```

- [ ] **Step 2: Commit**

```bash
cd /home/jones/dev/waaseyaa
git add packages/mail/src/MailServiceProvider.php
git commit -m "feat(mail): add MailServiceProvider with driver config

Refs: #699"
```

---

## Task 4: PasswordResetTokenRepository → waaseyaa/user

**Files:**
- Create: `packages/user/src/PasswordResetTokenRepository.php`
- Create: `packages/user/tests/Unit/PasswordResetTokenRepositoryTest.php`

- [ ] **Step 1: Write test**

Create `packages/user/tests/Unit/PasswordResetTokenRepositoryTest.php`:

```php
<?php

declare(strict_types=1);

namespace Waaseyaa\User\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\User\PasswordResetTokenRepository;

#[CoversClass(PasswordResetTokenRepository::class)]
final class PasswordResetTokenRepositoryTest extends TestCase
{
    private \PDO $pdo;
    private PasswordResetTokenRepository $repo;

    protected function setUp(): void
    {
        $this->pdo = new \PDO('sqlite::memory:');
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->repo = new PasswordResetTokenRepository($this->pdo);
    }

    #[Test]
    public function creates_token_and_validates_it(): void
    {
        $token = $this->repo->createToken(42);
        $this->assertSame(64, strlen($token));

        $userId = $this->repo->validateToken($token);
        $this->assertSame('42', $userId);
    }

    #[Test]
    public function invalidates_previous_tokens_for_same_user(): void
    {
        $first = $this->repo->createToken(42);
        $second = $this->repo->createToken(42);

        $this->assertNull($this->repo->validateToken($first));
        $this->assertSame('42', $this->repo->validateToken($second));
    }

    #[Test]
    public function consume_marks_token_as_used(): void
    {
        $token = $this->repo->createToken(42);
        $this->repo->consumeToken($token);

        $this->assertNull($this->repo->validateToken($token));
    }

    #[Test]
    public function expired_token_returns_null(): void
    {
        // Insert a token that expired 1 second ago
        $this->repo->createToken(42);
        $this->pdo->exec("UPDATE password_reset_tokens SET expires_at = " . (time() - 1));

        $token = $this->pdo->query("SELECT token FROM password_reset_tokens")->fetchColumn();
        $this->assertNull($this->repo->validateToken($token));
    }

    #[Test]
    public function unknown_token_returns_null(): void
    {
        $this->assertNull($this->repo->validateToken('nonexistent'));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd /home/jones/dev/waaseyaa && ./vendor/bin/phpunit packages/user/tests/Unit/PasswordResetTokenRepositoryTest.php`
Expected: FAIL — class not found

- [ ] **Step 3: Write PasswordResetTokenRepository**

Create `packages/user/src/PasswordResetTokenRepository.php`:

```php
<?php

declare(strict_types=1);

namespace Waaseyaa\User;

final class PasswordResetTokenRepository
{
    private bool $tableEnsured = false;

    public function __construct(private readonly \PDO $pdo) {}

    /**
     * Create a reset token for a user. Invalidates any existing tokens.
     * Returns the 64-char hex token string.
     */
    public function createToken(int|string $userId): string
    {
        $this->ensureTable();

        $stmt = $this->pdo->prepare('DELETE FROM password_reset_tokens WHERE user_id = :uid');
        $stmt->execute(['uid' => $userId]);

        $token = bin2hex(random_bytes(32));
        $stmt = $this->pdo->prepare(
            'INSERT INTO password_reset_tokens (token, user_id, expires_at, used_at) VALUES (:token, :uid, :expires, NULL)'
        );
        $stmt->execute([
            'token' => $token,
            'uid' => $userId,
            'expires' => time() + 3600,
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
            'SELECT user_id FROM password_reset_tokens WHERE token = :token AND expires_at > :now AND used_at IS NULL'
        );
        $stmt->execute(['token' => $token, 'now' => time()]);
        $result = $stmt->fetchColumn();

        return $result !== false ? $result : null;
    }

    /**
     * Mark a token as used (consumed).
     */
    public function consumeToken(string $token): void
    {
        $this->ensureTable();

        $stmt = $this->pdo->prepare('UPDATE password_reset_tokens SET used_at = :now WHERE token = :token');
        $stmt->execute(['token' => $token, 'now' => time()]);
    }

    private function ensureTable(): void
    {
        if ($this->tableEnsured) {
            return;
        }

        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS password_reset_tokens ('
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

Note: We keep `ensureTable()` for now — the framework doesn't have a migration runner wired into individual packages yet. The table is simple and `CREATE TABLE IF NOT EXISTS` is idempotent.

- [ ] **Step 4: Run tests**

Run: `cd /home/jones/dev/waaseyaa && ./vendor/bin/phpunit packages/user/tests/Unit/PasswordResetTokenRepositoryTest.php`
Expected: OK (5 tests)

- [ ] **Step 5: Commit**

```bash
cd /home/jones/dev/waaseyaa
git add packages/user/src/PasswordResetTokenRepository.php packages/user/tests/Unit/PasswordResetTokenRepositoryTest.php
git commit -m "feat(user): add PasswordResetTokenRepository

Refs: #695"
```

---

## Task 5: AuthMailer → waaseyaa/user

**Files:**
- Create: `packages/user/src/AuthMailer.php`
- Create: `packages/user/tests/Unit/AuthMailerTest.php`

- [ ] **Step 1: Write test**

Create `packages/user/tests/Unit/AuthMailerTest.php`:

```php
<?php

declare(strict_types=1);

namespace Waaseyaa\User\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Waaseyaa\Mail\MailDriverInterface;
use Waaseyaa\Mail\MailMessage;
use Waaseyaa\User\AuthMailer;
use Waaseyaa\User\User;

#[CoversClass(AuthMailer::class)]
final class AuthMailerTest extends TestCase
{
    private MailDriverInterface $driver;
    private Environment $twig;
    private AuthMailer $mailer;

    /** @var list<MailMessage> */
    private array $sentMessages = [];

    protected function setUp(): void
    {
        $this->sentMessages = [];
        $self = $this;

        $this->driver = new class($self) implements MailDriverInterface {
            /** @param AuthMailerTest $test */
            public function __construct(private readonly object $test) {}
            public function send(MailMessage $message): int
            {
                $this->test->sentMessages[] = $message;
                return 202;
            }
            public function isConfigured(): bool { return true; }
        };

        $this->twig = new Environment(new ArrayLoader([
            'email/password-reset.html.twig' => '<p>Reset: {{ reset_url }}</p>',
            'email/password-reset.txt.twig' => 'Reset: {{ reset_url }}',
            'email/email-verification.html.twig' => '<p>Verify: {{ verify_url }}</p>',
            'email/email-verification.txt.twig' => 'Verify: {{ verify_url }}',
            'email/welcome.html.twig' => '<p>Welcome {{ user_name }}</p>',
            'email/welcome.txt.twig' => 'Welcome {{ user_name }}',
        ]));

        $this->mailer = new AuthMailer(
            driver: $this->driver,
            twig: $this->twig,
            baseUrl: 'https://example.com',
            appName: 'TestApp',
        );
    }

    #[Test]
    public function sends_password_reset_email(): void
    {
        $user = $this->createMock(User::class);
        $user->method('get')->willReturnMap([
            ['name', 'Alice'],
            ['mail', 'alice@example.com'],
        ]);

        $this->mailer->sendPasswordReset($user, 'abc123');

        $this->assertCount(1, $this->sentMessages);
        $msg = $this->sentMessages[0];
        $this->assertSame('alice@example.com', $msg->to);
        $this->assertSame('Reset your TestApp password', $msg->subject);
        $this->assertStringContainsString('reset-password?token=abc123', $msg->htmlBody);
    }

    #[Test]
    public function sends_email_verification(): void
    {
        $user = $this->createMock(User::class);
        $user->method('get')->willReturnMap([
            ['name', 'Bob'],
            ['mail', 'bob@example.com'],
        ]);

        $this->mailer->sendEmailVerification($user, 'xyz789');

        $this->assertCount(1, $this->sentMessages);
        $msg = $this->sentMessages[0];
        $this->assertSame('Verify your email for TestApp', $msg->subject);
        $this->assertStringContainsString('verify-email?token=xyz789', $msg->htmlBody);
    }

    #[Test]
    public function sends_welcome_email(): void
    {
        $user = $this->createMock(User::class);
        $user->method('get')->willReturnMap([
            ['name', 'Carol'],
            ['mail', 'carol@example.com'],
        ]);

        $this->mailer->sendWelcome($user);

        $this->assertCount(1, $this->sentMessages);
        $msg = $this->sentMessages[0];
        $this->assertSame('Welcome to TestApp', $msg->subject);
        $this->assertStringContainsString('Welcome Carol', $msg->htmlBody);
    }

    #[Test]
    public function skips_sending_when_driver_not_configured(): void
    {
        $unconfigured = new class implements MailDriverInterface {
            public function send(MailMessage $message): int { return 202; }
            public function isConfigured(): bool { return false; }
        };

        $mailer = new AuthMailer($unconfigured, $this->twig, 'https://example.com', 'App');
        $user = $this->createMock(User::class);

        $mailer->sendPasswordReset($user, 'token');
        $this->assertCount(0, $this->sentMessages);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd /home/jones/dev/waaseyaa && ./vendor/bin/phpunit packages/user/tests/Unit/AuthMailerTest.php`
Expected: FAIL — class not found

- [ ] **Step 3: Write AuthMailer**

Create `packages/user/src/AuthMailer.php`:

```php
<?php

declare(strict_types=1);

namespace Waaseyaa\User;

use Twig\Environment;
use Waaseyaa\Mail\MailDriverInterface;
use Waaseyaa\Mail\MailMessage;

class AuthMailer
{
    public function __construct(
        private readonly MailDriverInterface $driver,
        private readonly Environment $twig,
        private readonly string $baseUrl,
        private readonly string $appName,
    ) {}

    public function isConfigured(): bool
    {
        return $this->driver->isConfigured();
    }

    public function sendPasswordReset(User $user, string $token): void
    {
        if (!$this->driver->isConfigured()) {
            return;
        }

        $vars = [
            'user_name' => $user->get('name'),
            'reset_url' => $this->baseUrl . '/reset-password?token=' . $token,
        ];

        $html = $this->twig->render('email/password-reset.html.twig', $vars);
        $text = $this->twig->render('email/password-reset.txt.twig', $vars);

        $this->driver->send(new MailMessage(
            from: '',
            to: $user->get('mail'),
            subject: "Reset your {$this->appName} password",
            body: $text,
            htmlBody: $html,
        ));
    }

    public function sendEmailVerification(User $user, string $token): void
    {
        if (!$this->driver->isConfigured()) {
            return;
        }

        $vars = [
            'user_name' => $user->get('name'),
            'verify_url' => $this->baseUrl . '/verify-email?token=' . $token,
        ];

        $html = $this->twig->render('email/email-verification.html.twig', $vars);
        $text = $this->twig->render('email/email-verification.txt.twig', $vars);

        $this->driver->send(new MailMessage(
            from: '',
            to: $user->get('mail'),
            subject: "Verify your email for {$this->appName}",
            body: $text,
            htmlBody: $html,
        ));
    }

    public function sendWelcome(User $user): void
    {
        if (!$this->driver->isConfigured()) {
            return;
        }

        $vars = [
            'user_name' => $user->get('name'),
            'home_url' => $this->baseUrl,
        ];

        $html = $this->twig->render('email/welcome.html.twig', $vars);
        $text = $this->twig->render('email/welcome.txt.twig', $vars);

        $this->driver->send(new MailMessage(
            from: '',
            to: $user->get('mail'),
            subject: "Welcome to {$this->appName}",
            body: $text,
            htmlBody: $html,
        ));
    }
}
```

Note: `AuthMailer` is NOT `final` — the CLAUDE.md gotcha says "Don't use `final` on services that need mocking." Tests mock `User` (which is not final in the framework).

- [ ] **Step 4: Add waaseyaa/mail dependency to user package**

In `packages/user/composer.json`, add to `repositories`:
```json
{
    "type": "path",
    "url": "../mail"
}
```

Add to `require`:
```json
"waaseyaa/mail": "^0.1",
"twig/twig": "^3.0"
```

- [ ] **Step 5: Run tests**

Run: `cd /home/jones/dev/waaseyaa && ./vendor/bin/phpunit packages/user/tests/Unit/AuthMailerTest.php`
Expected: OK (4 tests)

- [ ] **Step 6: Commit**

```bash
cd /home/jones/dev/waaseyaa
git add packages/user/src/AuthMailer.php packages/user/tests/Unit/AuthMailerTest.php packages/user/composer.json
git commit -m "feat(user): add AuthMailer with configurable app name

Refs: #695"
```

---

## Task 6: UserBlock entity → waaseyaa/user

**Files:**
- Create: `packages/user/src/UserBlock.php`
- Create: `packages/user/tests/Unit/UserBlockTest.php`

- [ ] **Step 1: Write test**

Create `packages/user/tests/Unit/UserBlockTest.php`:

```php
<?php

declare(strict_types=1);

namespace Waaseyaa\User\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\User\UserBlock;

#[CoversClass(UserBlock::class)]
final class UserBlockTest extends TestCase
{
    #[Test]
    public function creates_with_required_fields(): void
    {
        $block = new UserBlock(['blocker_id' => 1, 'blocked_id' => 2]);
        $this->assertSame(1, (int) $block->get('blocker_id'));
        $this->assertSame(2, (int) $block->get('blocked_id'));
        $this->assertNotNull($block->get('created_at'));
    }

    #[Test]
    public function uses_provided_created_at(): void
    {
        $block = new UserBlock(['blocker_id' => 1, 'blocked_id' => 2, 'created_at' => 1000]);
        $this->assertSame(1000, (int) $block->get('created_at'));
    }

    #[Test]
    public function requires_blocker_id(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('blocker_id');
        new UserBlock(['blocked_id' => 2]);
    }

    #[Test]
    public function requires_blocked_id(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('blocked_id');
        new UserBlock(['blocker_id' => 1]);
    }

    #[Test]
    public function rejects_self_block(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot block yourself');
        new UserBlock(['blocker_id' => 1, 'blocked_id' => 1]);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd /home/jones/dev/waaseyaa && ./vendor/bin/phpunit packages/user/tests/Unit/UserBlockTest.php`
Expected: FAIL — class not found

- [ ] **Step 3: Write UserBlock**

Create `packages/user/src/UserBlock.php`:

```php
<?php

declare(strict_types=1);

namespace Waaseyaa\User;

use Waaseyaa\Entity\ContentEntityBase;

final class UserBlock extends ContentEntityBase
{
    protected string $entityTypeId = 'user_block';

    protected array $entityKeys = [
        'id' => 'ubid',
        'uuid' => 'uuid',
        'label' => 'blocker_id',
    ];

    /** @param array<string, mixed> $values */
    public function __construct(array $values = [])
    {
        foreach (['blocker_id', 'blocked_id'] as $field) {
            if (!isset($values[$field])) {
                throw new \InvalidArgumentException("Missing required field: {$field}");
            }
        }

        if ((int) $values['blocker_id'] === (int) $values['blocked_id']) {
            throw new \InvalidArgumentException('Cannot block yourself');
        }

        if (!array_key_exists('created_at', $values)) {
            $values['created_at'] = time();
        }

        parent::__construct($values, $this->entityTypeId, $this->entityKeys);
    }
}
```

- [ ] **Step 4: Run tests**

Run: `cd /home/jones/dev/waaseyaa && ./vendor/bin/phpunit packages/user/tests/Unit/UserBlockTest.php`
Expected: OK (5 tests)

- [ ] **Step 5: Commit**

```bash
cd /home/jones/dev/waaseyaa
git add packages/user/src/UserBlock.php packages/user/tests/Unit/UserBlockTest.php
git commit -m "feat(user): add UserBlock entity

Refs: #702"
```

---

## Task 7: UserBlockAccessPolicy + UserBlockService

**Files:**
- Create: `packages/user/src/UserBlockAccessPolicy.php`
- Create: `packages/user/src/UserBlockService.php`
- Create: `packages/user/tests/Unit/UserBlockAccessPolicyTest.php`
- Create: `packages/user/tests/Unit/UserBlockServiceTest.php`

- [ ] **Step 1: Write access policy test**

Create `packages/user/tests/Unit/UserBlockAccessPolicyTest.php`:

```php
<?php

declare(strict_types=1);

namespace Waaseyaa\User\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Access\AccessResult;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\EntityInterface;
use Waaseyaa\User\UserBlockAccessPolicy;

#[CoversClass(UserBlockAccessPolicy::class)]
final class UserBlockAccessPolicyTest extends TestCase
{
    private UserBlockAccessPolicy $policy;

    protected function setUp(): void
    {
        $this->policy = new UserBlockAccessPolicy();
    }

    #[Test]
    public function applies_to_user_block(): void
    {
        $this->assertTrue($this->policy->appliesTo('user_block'));
        $this->assertFalse($this->policy->appliesTo('post'));
    }

    #[Test]
    public function admin_is_always_allowed(): void
    {
        $account = $this->createMock(AccountInterface::class);
        $account->method('hasPermission')->with('administer content')->willReturn(true);

        $entity = $this->createMock(EntityInterface::class);

        $result = $this->policy->access($entity, 'view', $account);
        $this->assertSame(AccessResult::ALLOWED, $result->getDecision());
    }

    #[Test]
    public function blocker_can_manage_own_blocks(): void
    {
        $account = $this->createMock(AccountInterface::class);
        $account->method('hasPermission')->willReturn(false);
        $account->method('id')->willReturn(42);

        $entity = $this->createMock(EntityInterface::class);
        $entity->method('get')->with('blocker_id')->willReturn(42);

        $result = $this->policy->access($entity, 'delete', $account);
        $this->assertSame(AccessResult::ALLOWED, $result->getDecision());
    }

    #[Test]
    public function non_owner_is_neutral(): void
    {
        $account = $this->createMock(AccountInterface::class);
        $account->method('hasPermission')->willReturn(false);
        $account->method('id')->willReturn(99);

        $entity = $this->createMock(EntityInterface::class);
        $entity->method('get')->with('blocker_id')->willReturn(42);

        $result = $this->policy->access($entity, 'view', $account);
        $this->assertSame(AccessResult::NEUTRAL, $result->getDecision());
    }

    #[Test]
    public function authenticated_can_create(): void
    {
        $account = $this->createMock(AccountInterface::class);
        $account->method('hasPermission')->willReturn(false);
        $account->method('isAuthenticated')->willReturn(true);

        $result = $this->policy->createAccess('user_block', 'user_block', $account);
        $this->assertSame(AccessResult::ALLOWED, $result->getDecision());
    }

    #[Test]
    public function anonymous_cannot_create(): void
    {
        $account = $this->createMock(AccountInterface::class);
        $account->method('hasPermission')->willReturn(false);
        $account->method('isAuthenticated')->willReturn(false);

        $result = $this->policy->createAccess('user_block', 'user_block', $account);
        $this->assertSame(AccessResult::NEUTRAL, $result->getDecision());
    }
}
```

- [ ] **Step 2: Write UserBlockAccessPolicy**

Create `packages/user/src/UserBlockAccessPolicy.php`:

```php
<?php

declare(strict_types=1);

namespace Waaseyaa\User;

use Waaseyaa\Access\AccessPolicyInterface;
use Waaseyaa\Access\AccessResult;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Access\Gate\PolicyAttribute;
use Waaseyaa\Entity\EntityInterface;

#[PolicyAttribute(entityType: 'user_block')]
final class UserBlockAccessPolicy implements AccessPolicyInterface
{
    public function appliesTo(string $entityTypeId): bool
    {
        return $entityTypeId === 'user_block';
    }

    public function access(EntityInterface $entity, string $operation, AccountInterface $account): AccessResult
    {
        if ($account->hasPermission('administer content')) {
            return AccessResult::allowed('Admin permission.');
        }

        $blockerId = $entity->get('blocker_id');

        if ($blockerId !== null && (int) $blockerId === (int) $account->id()) {
            return AccessResult::allowed('Blocker may manage own blocks.');
        }

        return AccessResult::neutral('Only the blocker may access this block.');
    }

    public function createAccess(string $entityTypeId, string $bundle, AccountInterface $account): AccessResult
    {
        if ($account->hasPermission('administer content')) {
            return AccessResult::allowed('Admin permission.');
        }

        if ($account->isAuthenticated()) {
            return AccessResult::allowed('Authenticated users may create blocks.');
        }

        return AccessResult::neutral('Anonymous users cannot create blocks.');
    }
}
```

- [ ] **Step 3: Write UserBlockService test**

Create `packages/user/tests/Unit/UserBlockServiceTest.php`:

```php
<?php

declare(strict_types=1);

namespace Waaseyaa\User\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Entity\Storage\EntityQueryInterface;
use Waaseyaa\Entity\Storage\EntityStorageInterface;
use Waaseyaa\User\UserBlockService;

#[CoversClass(UserBlockService::class)]
final class UserBlockServiceTest extends TestCase
{
    #[Test]
    public function returns_true_when_block_exists(): void
    {
        $query = $this->createMock(EntityQueryInterface::class);
        $query->method('condition')->willReturnSelf();
        $query->method('range')->willReturnSelf();
        $query->method('execute')->willReturn([1]);

        $storage = $this->createMock(EntityStorageInterface::class);
        $storage->method('getQuery')->willReturn($query);

        $etm = $this->createMock(EntityTypeManager::class);
        $etm->method('getStorage')->with('user_block')->willReturn($storage);

        $service = new UserBlockService($etm);
        $this->assertTrue($service->isBlocked(42, 99));
    }

    #[Test]
    public function returns_false_when_no_block(): void
    {
        $query = $this->createMock(EntityQueryInterface::class);
        $query->method('condition')->willReturnSelf();
        $query->method('range')->willReturnSelf();
        $query->method('execute')->willReturn([]);

        $storage = $this->createMock(EntityStorageInterface::class);
        $storage->method('getQuery')->willReturn($query);

        $etm = $this->createMock(EntityTypeManager::class);
        $etm->method('getStorage')->with('user_block')->willReturn($storage);

        $service = new UserBlockService($etm);
        $this->assertFalse($service->isBlocked(42, 99));
    }
}
```

- [ ] **Step 4: Write UserBlockService**

Create `packages/user/src/UserBlockService.php`:

```php
<?php

declare(strict_types=1);

namespace Waaseyaa\User;

use Waaseyaa\Entity\EntityTypeManager;

final class UserBlockService
{
    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
    ) {}

    public function isBlocked(int $blockerId, int $blockedId): bool
    {
        $ids = $this->entityTypeManager->getStorage('user_block')
            ->getQuery()
            ->condition('blocker_id', $blockerId)
            ->condition('blocked_id', $blockedId)
            ->range(0, 1)
            ->execute();

        return $ids !== [];
    }
}
```

- [ ] **Step 5: Run all tests**

Run: `cd /home/jones/dev/waaseyaa && ./vendor/bin/phpunit packages/user/tests/Unit/UserBlockAccessPolicyTest.php packages/user/tests/Unit/UserBlockServiceTest.php`
Expected: OK (8 tests)

- [ ] **Step 6: Commit**

```bash
cd /home/jones/dev/waaseyaa
git add packages/user/src/UserBlockAccessPolicy.php packages/user/src/UserBlockService.php packages/user/tests/Unit/UserBlockAccessPolicyTest.php packages/user/tests/Unit/UserBlockServiceTest.php
git commit -m "feat(user): add UserBlockAccessPolicy and UserBlockService

Refs: #702"
```

---

## Task 8: Wire everything in UserServiceProvider

**Files:**
- Modify: `packages/user/src/UserServiceProvider.php`

- [ ] **Step 1: Update UserServiceProvider**

Add to `packages/user/src/UserServiceProvider.php`:

In the `register()` method, after the existing user entity type registration, add:

```php
$this->entityType(new EntityType(
    id: 'user_block',
    label: 'User Block',
    class: UserBlock::class,
    keys: ['id' => 'ubid', 'uuid' => 'uuid', 'label' => 'blocker_id'],
    group: 'user',
    fieldDefinitions: [
        'blocker_id' => ['type' => 'integer', 'label' => 'Blocker ID', 'weight' => 0],
        'blocked_id' => ['type' => 'integer', 'label' => 'Blocked ID', 'weight' => 1],
        'created_at' => ['type' => 'timestamp', 'label' => 'Created', 'weight' => 10],
    ],
));

$this->singleton(UserBlockService::class, fn () => new UserBlockService(
    $this->resolve(EntityTypeManager::class),
));

$this->singleton(PasswordResetTokenRepository::class, fn () => new PasswordResetTokenRepository(
    $this->resolve(\PDO::class),
));

$config = $this->config ?? [];
$this->singleton(AuthMailer::class, fn () => new AuthMailer(
    driver: $this->resolve(\Waaseyaa\Mail\MailDriverInterface::class),
    twig: \Waaseyaa\SSR\SsrServiceProvider::getTwigEnvironment(),
    baseUrl: $config['app']['url'] ?? '',
    appName: $config['app']['name'] ?? 'Waaseyaa',
));
```

Add imports at top:
```php
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Mail\MailDriverInterface;
use Waaseyaa\SSR\SsrServiceProvider;
```

- [ ] **Step 2: Run user package tests**

Run: `cd /home/jones/dev/waaseyaa && ./vendor/bin/phpunit packages/user/tests/`
Expected: All user tests pass

- [ ] **Step 3: Commit**

```bash
cd /home/jones/dev/waaseyaa
git add packages/user/src/UserServiceProvider.php
git commit -m "feat(user): wire PasswordResetTokenRepository, AuthMailer, UserBlock in provider

Refs: #695, #702"
```

---

## Task 9: Create framework PRs, merge, tag, split repos

- [ ] **Step 1: Push and create mail PR**

```bash
cd /home/jones/dev/waaseyaa
git push -u origin HEAD
gh pr create --title "feat(mail): new mail abstraction package — Refs #699" --body "..."
```

- [ ] **Step 2: Push and create user additions PR**

If on same branch, one PR covers both. Otherwise create separate.

- [ ] **Step 3: Merge PRs**

```bash
gh pr merge <number> --squash --delete-branch
```

- [ ] **Step 4: Tag new release**

```bash
git checkout main && git pull
git tag v0.1.0-alpha.64
git push origin v0.1.0-alpha.64
```

- [ ] **Step 5: Split and push affected repos**

```bash
for pkg in mail user; do
  git subtree split --prefix=packages/$pkg -b split/$pkg
  git push git@github.com:waaseyaa/$pkg.git split/$pkg:main --force
  git tag -d v0.1.0-alpha.64 2>/dev/null
  git tag v0.1.0-alpha.64 split/$pkg
  git push git@github.com:waaseyaa/$pkg.git v0.1.0-alpha.64 --force
done
```

Note: `waaseyaa/mail` needs a new split repo created first (like geo/mercure in Batch 1). Run `gh repo create waaseyaa/mail --public --description "Mail abstraction layer with driver interface for Waaseyaa"` and add to Packagist.

---

## Task 10: Minoo import swap

- [ ] **Step 1: Update composer.json**

Add `"waaseyaa/mail": "^0.1.0-alpha.64"`, bump `"waaseyaa/user": "^0.1.0-alpha.64"`.

- [ ] **Step 2: Run composer update**

```bash
composer update 'waaseyaa/*'
```

- [ ] **Step 3: Swap imports**

Replace in `src/Controller/AuthController.php`:
- `use Minoo\Support\AuthMailer;` → `use Waaseyaa\User\AuthMailer;`
- `use Minoo\Support\PasswordResetService;` → `use Waaseyaa\User\PasswordResetTokenRepository;`
- Update all `$this->passwordResetService->` → `$this->passwordResetTokenRepository->` (or keep property name, just change type)

Replace in `src/Provider/AuthServiceProvider.php`:
- `use Minoo\Support\PasswordResetService;` → `use Waaseyaa\User\PasswordResetTokenRepository;`

Replace in `src/Provider/BlockServiceProvider.php`:
- `use Minoo\Entity\UserBlock;` → `use Waaseyaa\User\UserBlock;`

Replace in `tests/Minoo/Unit/Entity/UserBlockTest.php`:
- `use Minoo\Entity\UserBlock;` → `use Waaseyaa\User\UserBlock;`

Replace in `tests/Minoo/Unit/Access/BlockAccessPolicyTest.php`:
- `use Minoo\Access\BlockAccessPolicy;` → `use Waaseyaa\User\UserBlockAccessPolicy;`
- Update class name references in test

- [ ] **Step 4: Update Minoo config**

Add to Minoo config:
```php
'app' => [
    'name' => 'Minoo',
    'url' => env('APP_URL', 'https://minoo.live'),
],
'mail' => [
    'driver' => 'sendgrid',
    'sendgrid_api_key' => env('SENDGRID_API_KEY', ''),
    'from_address' => env('MAIL_FROM_ADDRESS', 'hello@minoo.live'),
    'from_name' => env('MAIL_FROM_NAME', 'Minoo'),
],
```

- [ ] **Step 5: Delete old Minoo files**

```bash
rm src/Support/MailService.php
rm src/Support/AuthMailer.php
rm src/Support/PasswordResetService.php
rm src/Entity/UserBlock.php
rm src/Access/BlockAccessPolicy.php
rm src/Provider/MailServiceProvider.php
```

- [ ] **Step 6: Update MailServiceProvider registration**

Remove `Minoo\Provider\MailServiceProvider` from `composer.json` `extra.waaseyaa.providers` — the framework's `MailServiceProvider` handles it now.

- [ ] **Step 7: Delete or update Minoo tests**

Delete `tests/Minoo/Unit/Entity/UserBlockTest.php` and `tests/Minoo/Unit/Access/BlockAccessPolicyTest.php` (covered by framework tests). Or update imports if keeping for integration coverage.

- [ ] **Step 8: Clear cache, rebuild autoloader, run tests**

```bash
rm -f storage/framework/packages.php
composer dump-autoload
./vendor/bin/phpunit
```

Expected: All tests pass.

- [ ] **Step 9: Commit and create PR**

```bash
git add -A
git commit -m "refactor: swap Minoo mail/auth/block for framework packages (Batch 2)

Refs: waaseyaa/framework#695, #699, #702"
git push -u origin HEAD
gh pr create --title "refactor: swap Minoo mail/auth/block for framework packages (Batch 2)"
```
