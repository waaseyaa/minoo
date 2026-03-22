<?php

declare(strict_types=1);

namespace Minoo\Support;

use Twig\Environment;
use Waaseyaa\User\User;

class AuthMailer
{
    public function __construct(
        private readonly MailService $mail,
        private readonly Environment $twig,
        private readonly string $baseUrl,
    ) {}

    public function isConfigured(): bool
    {
        return $this->mail->isConfigured();
    }

    public function sendPasswordReset(User $user, string $token): void
    {
        if (!$this->mail->isConfigured()) {
            return;
        }

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
        if (!$this->mail->isConfigured()) {
            return;
        }

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
        if (!$this->mail->isConfigured()) {
            return;
        }

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
