<?php

declare(strict_types=1);

namespace App\Tests\Integration\Entity;

use App\Tests\Integration\Http\HttpKernelTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;

/**
 * The language module's data model is registered by LanguageModuleServiceProvider
 * and round-trips through entity storage (issue #890). Boots the real kernel, so
 * it also proves the provider is discovered and the :memory: tables auto-create
 * from the entity definitions.
 */
#[CoversNothing]
final class LanguageModuleEntitiesTest extends HttpKernelTestCase
{
    #[Test]
    public function translation_memory_round_trips_through_storage(): void
    {
        $storage = self::$kernel->getEntityTypeManager()->getStorage('translation_memory');

        $tm = $storage->create([
            'source_en' => 'bear',
            'source_hash' => hash('sha256', 'bear'),
            'language_tag' => 'oj-x-sagamok',
            'translation' => 'makwa',
            'confidence' => 80,
            'created_at' => time(),
            'updated_at' => time(),
        ]);
        $storage->save($tm);

        $loaded = $storage->load($tm->id());
        self::assertNotNull($loaded);
        self::assertSame('bear', $loaded->get('source_en'));
        self::assertSame('makwa', $loaded->get('translation'));
        self::assertSame('oj-x-sagamok', $loaded->get('language_tag'));
        self::assertSame(1, $loaded->get('needs_speaker_review'), 'Default review flag persists.');
    }

    #[Test]
    public function tm_gap_log_round_trips_through_storage(): void
    {
        $storage = self::$kernel->getEntityTypeManager()->getStorage('tm_gap_log');

        $gap = $storage->create([
            'source_en' => 'snowmobile',
            'source_hash' => hash('sha256', 'snowmobile'),
            'language_tag' => 'oj-x-sagamok',
            'lookup_type' => 'exact_miss',
            'request_count' => 3,
            'last_requested_at' => time(),
            'created_at' => time(),
            'updated_at' => time(),
        ]);
        $storage->save($gap);

        $loaded = $storage->load($gap->id());
        self::assertNotNull($loaded);
        self::assertSame('snowmobile', $loaded->get('source_en'));
        self::assertSame('exact_miss', $loaded->get('lookup_type'));
        self::assertSame(3, $loaded->get('request_count'));
        self::assertSame('open', $loaded->get('status'), 'Default lifecycle status persists.');
    }

    #[Test]
    public function tm_backlog_round_trips_with_its_defaults(): void
    {
        $storage = self::$kernel->getEntityTypeManager()->getStorage('tm_backlog');

        $backlog = $storage->create([
            'english_text' => 'Economic Development',
            'concept_key' => 'economic development',
            'demand_sites' => 10,
            'demand_total' => 225,
            'category' => 'governance-nav',
            'provenance' => 'seed-crawl-2026-06-25',
            'created_at' => time(),
            'updated_at' => time(),
        ]);
        $storage->save($backlog);

        $loaded = $storage->load($backlog->id());
        self::assertNotNull($loaded);
        self::assertSame('Economic Development', $loaded->get('english_text'));
        self::assertSame('governance-nav', $loaded->get('category'));
        self::assertSame(10, (int) $loaded->get('demand_sites'));
        self::assertSame('oj-x-sagamok', $loaded->get('target_tag'), 'Default first target persists.');
        self::assertSame('awaiting_translation', $loaded->get('status'), 'Default lifecycle status persists.');
    }
}
