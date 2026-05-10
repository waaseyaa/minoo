<?php

declare(strict_types=1);

namespace App\Console;

use Waaseyaa\CLI\CliIO;
use Waaseyaa\Mail\Envelope;
use Waaseyaa\Mail\MailerInterface;

final class MailTestHandler
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly bool $mailConfigured,
        private readonly string $mailFromAddress,
    ) {
    }

    public function execute(CliIO $io): int
    {
        $email = (string) $io->argument('email');

        if (!$this->mailConfigured) {
            $io->error('Mail is not configured. Set SENDGRID_API_KEY in your environment.');
            return 1;
        }

        try {
            $this->mailer->send(new Envelope(
                to: [$email],
                from: $this->mailFromAddress,
                subject: '[Minoo] Mail test',
                textBody: 'This is a test email from Minoo. If you received this, SendGrid is working.',
            ));

            $io->writeln("Test email sent to {$email}. Check your inbox (and spam).");
            return 0;
        } catch (\Throwable $e) {
            $io->error('Failed to send test email: ' . $e->getMessage());
            return 1;
        }
    }
}
