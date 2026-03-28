<?php

declare(strict_types=1);

namespace Minoo\Provider;

use Minoo\Support\Command\MailTestCommand;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;
use Waaseyaa\Mail\Driver\SendGridDriver;
use Waaseyaa\Mail\MailDriverInterface;

final class MailServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $config = $this->config['mail'] ?? [];

        $this->singleton(MailDriverInterface::class, fn () => new SendGridDriver(
            apiKey: $config['sendgrid_api_key'] ?? '',
            fromAddress: $config['from_address'] ?? '',
            fromName: $config['from_name'] ?? '',
        ));
    }

    public function commands(
        \Waaseyaa\Entity\EntityTypeManager $entityTypeManager,
        \Waaseyaa\Database\DatabaseInterface $database,
        \Symfony\Contracts\EventDispatcher\EventDispatcherInterface $dispatcher,
    ): array {
        return [
            new MailTestCommand($this->resolve(MailDriverInterface::class)),
        ];
    }
}
