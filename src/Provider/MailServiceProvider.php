<?php

declare(strict_types=1);

namespace Minoo\Provider;

use Minoo\Support\Command\MailTestCommand;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;
use Waaseyaa\Mail\MailDriverInterface;

final class MailServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Mail driver and AuthMailer are now registered by framework providers.
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
