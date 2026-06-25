<?php

declare(strict_types=1);

namespace App\Tests\Integration\Language;

use App\Language\Backlog\BacklogBoard;
use App\Seed\TranslationBacklogSeeder;
use App\Tests\Integration\Http\HttpKernelTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

/**
 * The translation backlog seeds idempotently into tm_backlog and reads back as a
 * ranked, concept-grouped, admin-only board (issue #906). Boots the real kernel,
 * so it also proves LanguageModuleServiceProvider registers tm_backlog and the
 * :memory: table auto-creates from the entity definition.
 */
#[CoversClass(TranslationBacklogSeeder::class)]
#[CoversClass(BacklogBoard::class)]
final class TranslationBacklogTest extends HttpKernelTestCase
{
    /**
     * @return list<array{english_text: string, concept_key: string, demand_sites: int, demand_total: int, category: string}>
     */
    private static function rows(): array
    {
        return [
            ['english_text' => 'Contact', 'concept_key' => 'contact', 'demand_sites' => 16, 'demand_total' => 494, 'category' => 'governance-nav'],
            ['english_text' => 'Contact Us', 'concept_key' => 'contact', 'demand_sites' => 15, 'demand_total' => 298, 'category' => 'governance-nav'],
            ['english_text' => 'Search', 'concept_key' => 'search', 'demand_sites' => 9, 'demand_total' => 170, 'category' => 'global-ui'],
        ];
    }

    #[Test]
    public function it_seeds_rows_with_the_backlog_contract(): void
    {
        $seeder = new TranslationBacklogSeeder(self::$kernel->getEntityTypeManager());
        $result = $seeder->seed(self::rows());

        self::assertSame(3, $result['created']);
        self::assertSame(0, $result['updated']);
        self::assertSame(['governance-nav' => 2, 'global-ui' => 1], $result['by_category']);

        $storage = self::$kernel->getEntityTypeManager()->getStorage('tm_backlog');
        $ids = $storage->getQuery()->accessCheck(false)->condition('english_text', 'Contact')->execute();
        self::assertNotEmpty($ids);
        $row = $storage->load((int) reset($ids));
        self::assertNotNull($row);
        self::assertSame('oj-x-sagamok', $row->get('target_tag'), 'Sagamok is the default first target.');
        self::assertSame('awaiting_translation', $row->get('status'));
        self::assertSame('seed-crawl-2026-06-25', $row->get('provenance'));
        self::assertSame(16, (int) $row->get('demand_sites'));
    }

    #[Test]
    public function re_seeding_upserts_and_never_duplicates(): void
    {
        $etm = self::$kernel->getEntityTypeManager();
        $seeder = new TranslationBacklogSeeder($etm);

        $seeder->seed(self::rows());
        // Re-run with a refreshed demand count for one row.
        $rows = self::rows();
        $rows[0]['demand_sites'] = 18;
        $second = $seeder->seed($rows);

        self::assertSame(0, $second['created'], 'No new rows on re-seed.');
        self::assertSame(3, $second['updated']);

        $storage = $etm->getStorage('tm_backlog');
        $all = $storage->getQuery()->accessCheck(false)->execute();
        self::assertCount(3, $all, 'Idempotent: still exactly three rows.');

        $ids = $storage->getQuery()->accessCheck(false)->condition('english_text', 'Contact')->execute();
        self::assertSame(18, (int) $storage->load((int) reset($ids))->get('demand_sites'), 'Demand refreshed in place.');
    }

    #[Test]
    public function the_board_ranks_by_demand_and_groups_by_concept(): void
    {
        $etm = self::$kernel->getEntityTypeManager();
        (new TranslationBacklogSeeder($etm))->seed(self::rows());

        $board = (new BacklogBoard($etm))->summarize();

        self::assertSame(3, $board['total']);
        self::assertSame(['global-ui' => 1, 'governance-nav' => 2], $board['by_category']);
        // Two concepts: "contact" (Contact + Contact Us) ranks above "search".
        self::assertSame('contact', $board['concepts'][0]['concept_key']);
        self::assertCount(2, $board['concepts'][0]['forms']);
        self::assertSame('Contact', $board['concepts'][0]['forms'][0]['english_text'], 'Strongest form first.');
        self::assertSame('search', $board['concepts'][1]['concept_key']);
    }
}
