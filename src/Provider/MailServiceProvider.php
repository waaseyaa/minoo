<?php

declare(strict_types=1);

namespace Minoo\Provider;

use Minoo\Support\Command\MailTestCommand;
use Minoo\Support\Command\MessagingDigestCommand;
use Minoo\Support\MessageDigestCommand;
use Symfony\Component\Console\Command\Command;
use Waaseyaa\Foundation\Kernel\ConsoleKernel;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;
use Waaseyaa\Mail\MailerInterface;

/**
 * Contributes `mail:test` and `messaging:digest`. {@see ServiceProvider::commands()} is not on
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
        $fromAddress = trim((string) ($config['from_address'] ?? ''));
        $configured = trim((string) ($config['sendgrid_api_key'] ?? '')) !== ''
            && $fromAddress !== '';

        $messagingConfig = [];
        if (isset($this->config['messaging']) && is_array($this->config['messaging'])) {
            $messagingConfig = $this->config['messaging'];
        }

        $digest = new MessageDigestCommand(
            $entityTypeManager,
            $this->resolve(MailerInterface::class),
            $configured,
            $messagingConfig,
            $fromAddress,
        );

        return [
            new MailTestCommand(
                $this->resolve(MailerInterface::class),
                $configured,
                $fromAddress,
            ),
            new MessagingDigestCommand($digest),
        ];
    }
}
