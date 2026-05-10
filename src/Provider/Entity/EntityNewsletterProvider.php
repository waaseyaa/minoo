<?php

declare(strict_types=1);

namespace App\Provider\Entity;

use App\Domain\Newsletter\Service\EditionLifecycle;
use App\Domain\Newsletter\Service\NewsletterAssembler;
use App\Domain\Newsletter\Service\NewsletterDispatcher;
use App\Domain\Newsletter\Service\NewsletterRenderer;
use App\Domain\Newsletter\Service\RenderTokenStore;
use App\Domain\Newsletter\ValueObject\SectionQuota;
use App\Provider\AppCoreServiceProvider;
use App\Support\NewsletterMailer;
use Waaseyaa\Entity\EntityTypeManager;

final class EntityNewsletterProvider extends AppCoreServiceProvider
{
    public function register(): void
    {
        // Entity types: {@see NewsletterEntityDefinitionsProvider}

        $this->singleton(EditionLifecycle::class, function () {
            return new EditionLifecycle();
        });

        $this->singleton(NewsletterAssembler::class, function () {
            $config = require dirname(__DIR__, 3) . '/config/newsletter.php';
            return new NewsletterAssembler(
                entityTypeManager: $this->resolve(EntityTypeManager::class),
                lifecycle: $this->resolve(EditionLifecycle::class),
                quotas: SectionQuota::fromConfig($config['sections']),
            );
        });

        $this->singleton(RenderTokenStore::class, function () {
            $config = require dirname(__DIR__, 3) . '/config/newsletter.php';
            $dir = dirname(__DIR__, 3) . '/' . $config['storage_dir'] . '/render-tokens';
            return new RenderTokenStore(
                storageDir: $dir,
                ttlSeconds: 60,
            );
        });

        $this->singleton(NewsletterRenderer::class, function () {
            $config = require dirname(__DIR__, 3) . '/config/newsletter.php';
            $rootDir = dirname(__DIR__, 3);
            return new NewsletterRenderer(
                tokenStore: $this->resolve(RenderTokenStore::class),
                storageDir: $rootDir . '/' . $config['storage_dir'],
                baseUrl: $_ENV['APP_URL'] ?? 'http://localhost:8081',
                nodeBinary: 'node',
                scriptPath: $rootDir . '/bin/render-pdf.js',
                timeoutSeconds: $config['pdf']['timeout_seconds'] ?? 60,
            );
        });

        $this->singleton(NewsletterDispatcher::class, function () {
            $config = require dirname(__DIR__, 3) . '/config/newsletter.php';
            $mailConfig = $this->config['mail'] ?? [];

            $mailer = new NewsletterMailer(
                apiKey: (string) ($mailConfig['sendgrid_api_key'] ?? ''),
                fromAddress: (string) ($mailConfig['from_address'] ?? ''),
                fromName: (string) ($mailConfig['from_name'] ?? 'Minoo Newsroom'),
            );
            return new NewsletterDispatcher(
                mailService: $mailer,
                communityConfig: $config['communities'] ?? [],
                defaultCommunity: $config['default_community'] ?? 'manitoulin-regional',
            );
        });


    }
}
