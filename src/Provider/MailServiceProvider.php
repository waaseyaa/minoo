<?php

declare(strict_types=1);

namespace Minoo\Provider;

use Minoo\Support\Command\MailTestCommand;
use Symfony\Component\Console\Command\Command;
use Waaseyaa\Foundation\Kernel\ConsoleKernel;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;
use Waaseyaa\Mail\MailerInterface;

/**
 * Contributes `mail:test`. {@see ServiceProvider::commands()} is not on
 * {@see \Waaseyaa\Foundation\ServiceProvider\ServiceProviderInterface} by design; the base
 * {@see ServiceProvider} declares it, and {@see ConsoleKernel} registers returned commands after boot.
 */
final class MailServiceProvider extends ServiceProvider
{
    public function register(): void {}

    /**
     * @return list<Command>
     */
    public function commands(
        \Waaseyaa\Entity\EntityTypeManager $entityTypeManager,
        \Waaseyaa\Database\DatabaseInterface $database,
        \Symfony\Contracts\EventDispatcher\EventDispatcherInterface $dispatcher,
    ): array {
        $config = $this->config['mail'] ?? [];
        $configured = trim((string) ($config['sendgrid_api_key'] ?? '')) !== ''
            && trim((string) ($config['from_address'] ?? '')) !== '';

        return [
            new MailTestCommand(
                $this->resolve(MailerInterface::class),
                $configured,
            ),
        ];
    }
}
