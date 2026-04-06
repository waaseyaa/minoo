<?php

declare(strict_types=1);

namespace Minoo\Provider;

use Minoo\Entity\NewsletterEdition;
use Minoo\Entity\NewsletterItem;
use Minoo\Entity\NewsletterSubmission;
use Waaseyaa\Entity\EntityType;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;

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
    }
}
