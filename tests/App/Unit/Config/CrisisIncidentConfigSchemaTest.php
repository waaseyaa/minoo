<?php

declare(strict_types=1);

namespace App\Tests\Unit\Config;

use App\Support\CrisisIncidentResolver;
use App\Support\CrisisResolveContext;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class CrisisIncidentConfigSchemaTest extends TestCase
{
    private string $projectRoot;

    protected function setUp(): void
    {
        $this->projectRoot = dirname(__DIR__, 4);
    }

    #[Test]
    public function each_registry_incident_config_has_required_structure(): void
    {
        $resolver = new CrisisIncidentResolver($this->projectRoot);
        $registry = $resolver->loadRegistry();

        foreach ($registry as $row) {
            $slug = (string) ($row['community_slug'] ?? '');
            $incident = (string) ($row['incident_slug'] ?? '');
            $resolved = $resolver->resolve($slug, $incident, CrisisResolveContext::withDraftIncidents());
            self::assertNotNull($resolved, "Failed to load config for {$slug}/{$incident}");
            /** @var array<string, mixed> $c */
            $c = $resolved['incident'];

            foreach ([
                'emergency_open_graph', 'last_verified_date', 'og_image_path', 'page_theme',
                'carousel_id', 'title_key', 'meta_description_key', 'breadcrumb_key',
                'soe_eyebrow_key', 'soe_title_key', 'soe_meta_key',
                'official_label_key', 'official_text_before_key', 'official_link_key',
                'notice_url', 'official_feed_url',
                'timeline_heading_key', 'tiles_heading_key',
                'contacts_heading_key', 'contacts_verified_key', 'contacts_notice_link_key',
                'contacts_note_key', 'contacts_notice_link_short_key',
                'prep_heading_key', 'footer_updated_key', 'back_top_key',
            ] as $key) {
                self::assertArrayHasKey($key, $c, "Missing {$key} in {$slug}/{$incident}");
            }

            self::assertIsArray($c['timeline'] ?? null);
            self::assertIsArray($c['tiles'] ?? null);
            self::assertIsArray($c['contacts'] ?? null);
            self::assertIsArray($c['info_cards'] ?? null);
            self::assertIsArray($c['prep_checklist_keys'] ?? null);
            self::assertIsArray($c['disclaimer_keys'] ?? null);
            self::assertIsArray($c['gallery'] ?? null);

            foreach ($c['timeline'] as $i => $t) {
                self::assertArrayHasKey('datetime', $t, "timeline[{$i}]");
                self::assertArrayHasKey('date_key', $t);
                self::assertArrayHasKey('body_key', $t);
            }
            foreach ($c['tiles'] as $i => $t) {
                self::assertArrayHasKey('label_key', $t, "tiles[{$i}]");
                self::assertArrayHasKey('pill_key', $t);
                self::assertArrayHasKey('pill_tone', $t);
                self::assertContains($t['pill_tone'], ['emergency', 'warn'], "tiles[{$i}].pill_tone");
                self::assertArrayHasKey('note_key', $t);
            }
            foreach ($c['contacts'] as $i => $t) {
                self::assertArrayHasKey('name_key', $t, "contacts[{$i}]");
                self::assertArrayHasKey('role_key', $t);
                self::assertArrayHasKey('tel_href', $t);
                self::assertArrayHasKey('tel_display', $t);
            }
        }
    }
}
