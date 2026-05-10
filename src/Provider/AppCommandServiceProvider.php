<?php

declare(strict_types=1);

namespace App\Provider;

use App\Console\CrisisOgAssetsHandler;
use App\Console\GenealogyDemoSeedHandler;
use App\Console\MailTestHandler;
use App\Console\MessageDigestCommand;
use App\Console\MessagingDigestHandler;
use Waaseyaa\CLI\ArgumentDefinition;
use Waaseyaa\CLI\ArgumentMode;
use Waaseyaa\CLI\CommandDefinition;
use Waaseyaa\CLI\OptionDefinition;
use Waaseyaa\CLI\OptionMode;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Foundation\ServiceProvider\Capability\HasNativeCommandsInterface;
use Waaseyaa\Mail\MailerInterface;

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

        $this->singleton(MessageDigestCommand::class, function (): MessageDigestCommand {
            [$configured, $fromAddress] = $this->mailConfigSnapshot();
            $messagingConfig = is_array($this->config['messaging'] ?? null)
                ? $this->config['messaging']
                : [];
            return new MessageDigestCommand(
                $this->resolve(EntityTypeManager::class),
                $this->resolve(MailerInterface::class),
                $configured,
                $messagingConfig,
                $fromAddress,
            );
        });

        $this->singleton(MessagingDigestHandler::class, function (): MessagingDigestHandler {
            return new MessagingDigestHandler($this->resolve(MessageDigestCommand::class));
        });

        $this->singleton(GenealogyDemoSeedHandler::class, function (): GenealogyDemoSeedHandler {
            return new GenealogyDemoSeedHandler($this->resolve(EntityTypeManager::class));
        });

        $this->singleton(CrisisOgAssetsHandler::class, function (): CrisisOgAssetsHandler {
            return new CrisisOgAssetsHandler(
                $this->resolve(\App\Support\CrisisIncidentResolver::class),
                $this->resolve(\App\Support\CrisisOgImageService::class),
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

        yield new CommandDefinition(
            name: 'messaging:digest',
            description: 'Send email digests for unread thread messages (cron)',
            handler: [MessagingDigestHandler::class, 'execute'],
        );

        yield new CommandDefinition(
            name: 'genealogy:demo-seed',
            description: 'Create demo genealogy tree, persons, family, and edges for local SSR testing',
            handler: [GenealogyDemoSeedHandler::class, 'execute'],
        );

        yield new CommandDefinition(
            name: 'crisis:og-assets',
            description: 'Build or regenerate crisis Open Graph PNGs under public/ (managed /og/crisis/*.png only).',
            arguments: [
                new ArgumentDefinition(
                    name: 'operation',
                    mode: ArgumentMode::Optional,
                    description: 'build|regenerate',
                    default: 'build',
                ),
                new ArgumentDefinition(
                    name: 'community_slug',
                    mode: ArgumentMode::Optional,
                    description: 'With regenerate: community slug',
                ),
                new ArgumentDefinition(
                    name: 'incident_slug',
                    mode: ArgumentMode::Optional,
                    description: 'With regenerate: incident slug',
                ),
            ],
            options: [
                new OptionDefinition(
                    name: 'dry-run',
                    mode: OptionMode::None,
                    description: 'Print actions without writing files',
                ),
                new OptionDefinition(
                    name: 'only',
                    mode: OptionMode::Required,
                    description: 'Limit build to community_slug/incident_slug',
                    default: '',
                ),
                new OptionDefinition(
                    name: 'force',
                    mode: OptionMode::None,
                    description: 'Overwrite existing managed PNG',
                ),
                new OptionDefinition(
                    name: 'draft',
                    mode: OptionMode::None,
                    description: 'Include draft incidents in resolver',
                ),
            ],
            handler: [CrisisOgAssetsHandler::class, 'execute'],
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
