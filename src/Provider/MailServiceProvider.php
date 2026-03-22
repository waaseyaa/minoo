<?php

declare(strict_types=1);

namespace Minoo\Provider;

use Minoo\Support\AuthMailer;
use Minoo\Support\MailService;
use Minoo\Support\Command\MailTestCommand;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;
use Waaseyaa\SSR\SsrServiceProvider;

final class MailServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $config = $this->config['mail'] ?? [];

        $this->singleton(MailService::class, fn () => new MailService(
            apiKey: $config['sendgrid_api_key'] ?? '',
            fromAddress: $config['from_address'] ?? 'hello@minoo.live',
            fromName: $config['from_name'] ?? 'Minoo',
        ));

        $this->singleton(AuthMailer::class, fn () => new AuthMailer(
            $this->resolve(MailService::class),
            SsrServiceProvider::getTwigEnvironment(),
            $config['base_url'] ?? 'https://minoo.live',
        ));
    }

    public function commands(
        \Waaseyaa\Entity\EntityTypeManager $entityTypeManager,
        \Waaseyaa\Database\DatabaseInterface $database,
        \Symfony\Contracts\EventDispatcher\EventDispatcherInterface $dispatcher,
    ): array {
        $config = $this->config['mail'] ?? [];

        return [
            new MailTestCommand(new MailService(
                apiKey: $config['sendgrid_api_key'] ?? '',
                fromAddress: $config['from_address'] ?? 'hello@minoo.live',
                fromName: $config['from_name'] ?? 'Minoo',
            )),
        ];
    }
}
