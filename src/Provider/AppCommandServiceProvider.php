<?php

declare(strict_types=1);

namespace App\Provider;

use App\Console\MailTestHandler;
use Waaseyaa\CLI\ArgumentDefinition;
use Waaseyaa\CLI\ArgumentMode;
use Waaseyaa\CLI\CommandDefinition;
use Waaseyaa\Foundation\ServiceProvider\Capability\HasNativeCommandsInterface;
use Waaseyaa\Mail\MailerInterface;

/**
 * Language-platform slimming (2026-06): messaging digest, genealogy demo
 * seed, and crisis OG asset commands removed with their surfaces.
 */
class AppCommandServiceProvider extends AppCoreServiceProvider implements HasNativeCommandsInterface
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

    public function nativeCommands(): iterable
    {
        yield new CommandDefinition(
            name: 'mail:test',
            description: 'Send a test email to verify SendGrid configuration',
            arguments: [
                new ArgumentDefinition(
                    name: 'email',
                    mode: ArgumentMode::Required,
                    description: 'The address to send the test email to',
                ),
            ],
            handler: [MailTestHandler::class, 'execute'],
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
