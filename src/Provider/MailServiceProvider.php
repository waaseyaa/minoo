<?php

declare(strict_types=1);

namespace Minoo\Provider;

use Minoo\Support\Command\MailTestCommand;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;
use Waaseyaa\Mail\MailerInterface;

final class MailServiceProvider extends ServiceProvider
{
    public function register(): void {}

    /**
     * @return list<object>
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
