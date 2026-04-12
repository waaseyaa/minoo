<?php

declare(strict_types=1);

namespace App\Support\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Waaseyaa\Mail\Envelope;
use Waaseyaa\Mail\MailerInterface;

#[AsCommand(name: 'mail:test', description: 'Send a test email to verify SendGrid configuration')]
final class MailTestCommand extends Command
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly bool $mailConfigured,
        private readonly string $mailFromAddress,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('email', InputArgument::REQUIRED, 'The address to send the test email to');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $email = (string) $input->getArgument('email');

        if (!$this->mailConfigured) {
            $output->writeln('<error>Mail is not configured. Set SENDGRID_API_KEY in your environment.</error>');
            return self::FAILURE;
        }

        try {
            $this->mailer->send(new Envelope(
                to: [$email],
                from: $this->mailFromAddress,
                subject: '[Minoo] Mail test',
                textBody: 'This is a test email from Minoo. If you received this, SendGrid is working.',
            ));

            $output->writeln("<info>Test email sent to {$email}. Check your inbox (and spam).</info>");
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $output->writeln('<error>Failed to send test email: ' . $e->getMessage() . '</error>');
            return self::FAILURE;
        }
    }
}
