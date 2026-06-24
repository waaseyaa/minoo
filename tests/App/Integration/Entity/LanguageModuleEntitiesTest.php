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
            'dialect_code' => 'oji-east',
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
        self::assertSame('oji-east', $loaded->get('dialect_code'));
        self::assertSame(1, $loaded->get('needs_speaker_review'), 'Default review flag persists.');
    }

    #[Test]
    public function tm_gap_log_round_trips_through_storage(): void
    {
        $storage = self::$kernel->getEntityTypeManager()->getStorage('tm_gap_log');

        $gap = $storage->create([
            'source_en' => 'snowmobile',
            'source_hash' => hash('sha256', 'snowmobile'),
            'dialect_code' => 'oji-east',
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
}
