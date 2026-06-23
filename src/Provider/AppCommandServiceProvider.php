<?php

declare(strict_types=1);

namespace App\Provider;

use App\Console\MailTestHandler;
use Waaseyaa\CLI\Command\HandlerArgument;
use Waaseyaa\CLI\Command\HandlerArgumentMode;
use Waaseyaa\CLI\Command\HandlerCommand;
use Waaseyaa\Foundation\ServiceProvider\Capability\ProvidesConsoleCommandsInterface;
use Waaseyaa\Mail\MailerInterface;

/**
 * Language-platform slimming (2026-06): messaging digest, genealogy demo
 * seed, and crisis OG asset commands removed with their surfaces.
 */
class AppCommandServiceProvider extends AppCoreServiceProvider implements ProvidesConsoleCommandsInterface
{
    public function register(): void
    {
        parent::register();

        $this->singleton(MailTestHandler::class, function (): MailTestHandler {
            [$configured, $fromAddress] = $this->mailConfigSnapshot();
            return new MailTestHandler(
                $this->resolve(MailerInterface::class),
                $configured,
                $fromAddress,
            );
        });
    }

    public function consoleCommands(): iterable
    {
        yield new HandlerCommand(
            name: 'mail:test',
            description: 'Send a test email to verify SendGrid configuration',
            handler: [MailTestHandler::class, 'execute'],
            arguments: [
                new HandlerArgument(
                    name: 'email',
                    mode: HandlerArgumentMode::Required,
                    description: 'The address to send the test email to',
                ),
            ],
        );
    }

    /**
     * @return array{0: bool, 1: string} [configured, fromAddress]
     */
    private function mailConfigSnapshot(): array
    {
        $mailConfig = is_array($this->config['mail'] ?? null) ? $this->config['mail'] : [];
        $fromAddress = trim((string) ($mailConfig['from_address'] ?? ''));
        $configured = trim((string) ($mailConfig['sendgrid_api_key'] ?? '')) !== ''
            && $fromAddress !== '';

        return [$configured, $fromAddress];
    }
}
