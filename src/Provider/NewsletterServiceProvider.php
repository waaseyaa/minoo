<?php

declare(strict_types=1);

namespace Minoo\Provider;

use Minoo\Domain\Newsletter\Service\EditionLifecycle;
use Minoo\Domain\Newsletter\Service\NewsletterAssembler;
use Minoo\Domain\Newsletter\Service\RenderTokenStore;
use Minoo\Domain\Newsletter\ValueObject\SectionQuota;
use Minoo\Entity\NewsletterEdition;
use Minoo\Entity\NewsletterItem;
use Minoo\Entity\NewsletterSubmission;
use Waaseyaa\Entity\EntityType;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;
use Waaseyaa\Routing\RouteBuilder;
use Waaseyaa\Routing\WaaseyaaRouter;

final class NewsletterServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->entityType(new EntityType(
            id: 'newsletter_edition',
            label: 'Newsletter Edition',
            class: NewsletterEdition::class,
            keys: ['id' => 'neid', 'uuid' => 'uuid', 'label' => 'headline'],
            group: 'newsletter',
            fieldDefinitions: [
                'community_id' => ['type' => 'string', 'label' => 'Community ID', 'description' => 'Null = regional issue.'],
                'volume' => ['type' => 'integer', 'label' => 'Volume', 'default' => 1],
                'issue_number' => ['type' => 'integer', 'label' => 'Issue Number', 'default' => 1],
                'publish_date' => ['type' => 'string', 'label' => 'Publish Date'],
                'status' => ['type' => 'string', 'label' => 'Status', 'default' => 'draft'],
                'pdf_path' => ['type' => 'string', 'label' => 'PDF Path'],
                'pdf_hash' => ['type' => 'string', 'label' => 'PDF SHA256'],
                'sent_at' => ['type' => 'datetime', 'label' => 'Sent At'],
                'created_by' => ['type' => 'integer', 'label' => 'Created By'],
                'approved_by' => ['type' => 'integer', 'label' => 'Approved By'],
                'approved_at' => ['type' => 'datetime', 'label' => 'Approved At'],
                'headline' => ['type' => 'string', 'label' => 'Headline'],
            ],
        ));

        $this->entityType(new EntityType(
            id: 'newsletter_item',
            label: 'Newsletter Item',
            class: NewsletterItem::class,
            keys: ['id' => 'nitid', 'uuid' => 'uuid', 'label' => 'editor_blurb'],
            group: 'newsletter',
            fieldDefinitions: [
                'edition_id' => ['type' => 'integer', 'label' => 'Edition ID'],
                'position' => ['type' => 'integer', 'label' => 'Position', 'default' => 0],
                'section' => ['type' => 'string', 'label' => 'Section'],
                'source_type' => ['type' => 'string', 'label' => 'Source Type'],
                'source_id' => ['type' => 'integer', 'label' => 'Source ID'],
                'inline_title' => ['type' => 'string', 'label' => 'Inline Title'],
                'inline_body' => ['type' => 'text_long', 'label' => 'Inline Body'],
                'editor_blurb' => ['type' => 'string', 'label' => 'Editor Blurb'],
                'included' => ['type' => 'boolean', 'label' => 'Included', 'default' => 1],
            ],
        ));

        $this->entityType(new EntityType(
            id: 'newsletter_submission',
            label: 'Newsletter Submission',
            class: NewsletterSubmission::class,
            keys: ['id' => 'nsuid', 'uuid' => 'uuid', 'label' => 'title'],
            group: 'newsletter',
            fieldDefinitions: [
                'community_id' => ['type' => 'string', 'label' => 'Community ID'],
                'submitted_by' => ['type' => 'integer', 'label' => 'Submitted By'],
                'submitted_at' => ['type' => 'datetime', 'label' => 'Submitted At'],
                'category' => ['type' => 'string', 'label' => 'Category'],
                'title' => ['type' => 'string', 'label' => 'Title'],
                'body' => ['type' => 'text_long', 'label' => 'Body'],
                'status' => ['type' => 'string', 'label' => 'Status', 'default' => 'submitted'],
                'approved_by' => ['type' => 'integer', 'label' => 'Approved By'],
                'approved_at' => ['type' => 'datetime', 'label' => 'Approved At'],
                'included_in_edition_id' => ['type' => 'integer', 'label' => 'Included In Edition ID'],
            ],
        ));

        $this->singleton(EditionLifecycle::class, function () {
            return new EditionLifecycle();
        });

        $this->singleton(NewsletterAssembler::class, function () {
            $config = require __DIR__ . '/../../config/newsletter.php';
            return new NewsletterAssembler(
                entityTypeManager: $this->resolve(EntityTypeManager::class),
                lifecycle: $this->resolve(EditionLifecycle::class),
                quotas: SectionQuota::fromConfig($config['sections']),
            );
        });

        $this->singleton(RenderTokenStore::class, function () {
            $config = require __DIR__ . '/../../config/newsletter.php';
            $dir = dirname(__DIR__, 2) . '/' . $config['storage_dir'] . '/render-tokens';
            return new RenderTokenStore(
                storageDir: $dir,
                ttlSeconds: 60,
            );
        });
    }

    public function routes(WaaseyaaRouter $router, ?EntityTypeManager $entityTypeManager = null): void
    {
        $router->addRoute(
            'newsletter.editor.list',
            RouteBuilder::create('/coordinator/newsletter')
                ->controller('Minoo\Controller\NewsletterEditorController::list')
                ->requireRole('community_coordinator')
                ->render()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'newsletter.editor.new',
            RouteBuilder::create('/coordinator/newsletter/new')
                ->controller('Minoo\Controller\NewsletterEditorController::create')
                ->requireRole('community_coordinator')
                ->render()
                ->methods('POST')
                ->build(),
        );

        $router->addRoute(
            'newsletter.editor.assemble',
            RouteBuilder::create('/coordinator/newsletter/{id}/assemble')
                ->controller('Minoo\Controller\NewsletterEditorController::assemble')
                ->requireRole('community_coordinator')
                ->render()
                ->methods('POST')
                ->build(),
        );

        $router->addRoute(
            'newsletter.editor.show',
            RouteBuilder::create('/coordinator/newsletter/{id}')
                ->controller('Minoo\Controller\NewsletterEditorController::show')
                ->requireRole('community_coordinator')
                ->render()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'newsletter.editor.approve',
            RouteBuilder::create('/coordinator/newsletter/{id}/approve')
                ->controller('Minoo\Controller\NewsletterEditorController::approve')
                ->requireRole('community_coordinator')
                ->render()
                ->methods('POST')
                ->build(),
        );

        $router->addRoute(
            'newsletter.print_preview',
            RouteBuilder::create('/newsletter/_internal/{id}/print')
                ->controller('Minoo\Controller\NewsletterController::printPreview')
                ->allowAll()
                ->render()
                ->methods('GET')
                ->build(),
        );
    }
}
