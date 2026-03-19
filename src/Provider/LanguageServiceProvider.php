<?php

declare(strict_types=1);

namespace Minoo\Provider;

use Minoo\Entity\DictionaryEntry;
use Minoo\Entity\ExampleSentence;
use Minoo\Entity\Speaker;
use Minoo\Entity\WordPart;
use Waaseyaa\Entity\EntityType;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;
use Waaseyaa\Routing\RouteBuilder;
use Waaseyaa\Routing\WaaseyaaRouter;

final class LanguageServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->entityType(new EntityType(
            id: 'dictionary_entry',
            label: 'Dictionary Entry',
            class: DictionaryEntry::class,
            keys: ['id' => 'deid', 'uuid' => 'uuid', 'label' => 'word'],
            group: 'language',
            fieldDefinitions: [
                'word' => ['type' => 'string', 'label' => 'Word', 'weight' => 0],
                'slug' => ['type' => 'string', 'label' => 'URL Slug', 'weight' => 1],
                'definition' => ['type' => 'string', 'label' => 'Definition', 'weight' => 5],
                'part_of_speech' => ['type' => 'string', 'label' => 'Part of Speech', 'description' => 'Code: ni, na, vai, vti, vta, vii, nad, nid, etc.', 'weight' => 6],
                'stem' => ['type' => 'string', 'label' => 'Stem', 'description' => 'Root stem (e.g., /jiimaan-/).', 'weight' => 7],
                'inflected_forms' => ['type' => 'text', 'label' => 'Inflected Forms', 'description' => 'JSON array of form/label pairs.', 'weight' => 8],
                'language_code' => ['type' => 'string', 'label' => 'Language Code', 'description' => 'ISO-style code (e.g., oj, oj-sw, oj-nw).', 'weight' => 9, 'default' => 'oj'],
                'source_url' => ['type' => 'uri', 'label' => 'Source URL', 'weight' => 15],
                'attribution_source' => ['type' => 'string', 'label' => 'Attribution Source', 'description' => 'Source identifier (e.g., ojibwe-peoples-dictionary).', 'weight' => 16],
                'attribution_url' => ['type' => 'uri', 'label' => 'Attribution URL', 'description' => 'URL of the authoritative source.', 'weight' => 17],
                'consent_public' => ['type' => 'boolean', 'label' => 'Public Consent', 'description' => 'Whether this content may be shown on public pages.', 'weight' => 28, 'default' => 1],
                'consent_ai_training' => ['type' => 'boolean', 'label' => 'AI Training Consent', 'description' => 'Whether this content may be used for AI training. Default: no.', 'weight' => 29, 'default' => 0],
                'status' => ['type' => 'boolean', 'label' => 'Published', 'weight' => 30, 'default' => 1],
                'created_at' => ['type' => 'timestamp', 'label' => 'Created', 'weight' => 40],
                'updated_at' => ['type' => 'timestamp', 'label' => 'Updated', 'weight' => 41],
            ],
        ));

        $this->entityType(new EntityType(
            id: 'example_sentence',
            label: 'Example Sentence',
            class: ExampleSentence::class,
            keys: ['id' => 'esid', 'uuid' => 'uuid', 'label' => 'ojibwe_text'],
            group: 'language',
            fieldDefinitions: [
                'ojibwe_text' => ['type' => 'string', 'label' => 'Ojibwe Text', 'weight' => 0],
                'english_text' => ['type' => 'string', 'label' => 'English Translation', 'weight' => 5],
                'dictionary_entry_id' => ['type' => 'entity_reference', 'label' => 'Dictionary Entry', 'settings' => ['target_type' => 'dictionary_entry'], 'weight' => 10],
                'speaker_id' => ['type' => 'entity_reference', 'label' => 'Speaker', 'settings' => ['target_type' => 'speaker'], 'weight' => 15],
                'audio_url' => ['type' => 'uri', 'label' => 'Audio URL', 'weight' => 20],
                'source_sentence_id' => ['type' => 'string', 'label' => 'Source Sentence ID', 'description' => 'Unique ID from source for dedup across re-crawls.', 'weight' => 22],
                'language_code' => ['type' => 'string', 'label' => 'Language Code', 'weight' => 25, 'default' => 'oj'],
                'status' => ['type' => 'boolean', 'label' => 'Published', 'weight' => 30, 'default' => 1],
                'created_at' => ['type' => 'timestamp', 'label' => 'Created', 'weight' => 40],
                'updated_at' => ['type' => 'timestamp', 'label' => 'Updated', 'weight' => 41],
            ],
        ));

        $this->entityType(new EntityType(
            id: 'word_part',
            label: 'Word Part',
            class: WordPart::class,
            keys: ['id' => 'wpid', 'uuid' => 'uuid', 'label' => 'form'],
            group: 'language',
            fieldDefinitions: [
                'form' => ['type' => 'string', 'label' => 'Form', 'weight' => 0],
                'slug' => ['type' => 'string', 'label' => 'URL Slug', 'weight' => 1],
                'type' => ['type' => 'string', 'label' => 'Type', 'description' => 'initial, medial, or final.', 'weight' => 5],
                'definition' => ['type' => 'string', 'label' => 'Definition', 'weight' => 10],
                'source_url' => ['type' => 'uri', 'label' => 'Source URL', 'weight' => 15],
                'status' => ['type' => 'boolean', 'label' => 'Published', 'weight' => 30, 'default' => 1],
                'created_at' => ['type' => 'timestamp', 'label' => 'Created', 'weight' => 40],
                'updated_at' => ['type' => 'timestamp', 'label' => 'Updated', 'weight' => 41],
            ],
        ));

        $this->entityType(new EntityType(
            id: 'speaker',
            label: 'Speaker',
            class: Speaker::class,
            keys: ['id' => 'sid', 'uuid' => 'uuid', 'label' => 'name'],
            group: 'language',
            fieldDefinitions: [
                'name' => ['type' => 'string', 'label' => 'Name', 'weight' => 0],
                'slug' => ['type' => 'string', 'label' => 'URL Slug', 'weight' => 1],
                'code' => ['type' => 'string', 'label' => 'Speaker Code', 'description' => 'Abbreviation (e.g., es, nj, gh).', 'weight' => 5],
                'bio' => ['type' => 'text_long', 'label' => 'Biography', 'weight' => 10],
                'media_id' => ['type' => 'entity_reference', 'label' => 'Photo', 'settings' => ['target_type' => 'media'], 'weight' => 20],
                'copyright_status' => [
                    'type' => 'string',
                    'label' => 'Copyright Status',
                    'description' => 'Media copyright status: community_owned, cc_by_nc_sa, requires_permission, unknown.',
                    'default_value' => 'unknown',
                    'weight' => 99,
                ],
                'status' => ['type' => 'boolean', 'label' => 'Published', 'weight' => 30, 'default' => 1],
                'created_at' => ['type' => 'timestamp', 'label' => 'Created', 'weight' => 40],
                'updated_at' => ['type' => 'timestamp', 'label' => 'Updated', 'weight' => 41],
            ],
        ));
    }

    public function routes(WaaseyaaRouter $router, ?\Waaseyaa\Entity\EntityTypeManager $entityTypeManager = null): void
    {
        $router->addRoute(
            'language.list',
            RouteBuilder::create('/language')
                ->controller('Minoo\\Controller\\LanguageController::list')
                ->allowAll()
                ->render()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'language.show',
            RouteBuilder::create('/language/{slug}')
                ->controller('Minoo\\Controller\\LanguageController::show')
                ->allowAll()
                ->render()
                ->methods('GET')
                ->requirement('slug', '[a-z0-9][a-z0-9-]*[a-z0-9]')
                ->build(),
        );
    }
}
