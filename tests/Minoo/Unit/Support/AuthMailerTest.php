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
        $this->mailService->method('isConfigured')->willReturn(true);
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

    private function createUser(string $id, string $name, string $email): User
    {
        return new User([
            'uid' => $id,
            'name' => $name,
            'mail' => $email,
        ]);
    }

    #[Test]
    public function send_password_reset_calls_mail_service(): void
    {
        $user = $this->createUser('1', 'Alice', 'alice@example.com');

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
        $user = $this->createUser('2', 'Bob', 'bob@example.com');

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
        $user = $this->createUser('3', 'Carol', 'carol@example.com');

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
