<?php

declare(strict_types=1);

namespace App\Tests\Unit\Language\Backlog;

use App\Language\Backlog\BacklogBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(BacklogBuilder::class)]
final class BacklogBuilderTest extends TestCase
{
    /**
     * @return list<array{string: string, distinct_sites: int, total: int}>
     */
    private static function sample(): array
    {
        return [
            ['string' => 'Contact', 'distinct_sites' => 9, 'total' => 200],
            ['string' => 'Contact Us', 'distinct_sites' => 7, 'total' => 100],
            ['string' => 'Search', 'distinct_sites' => 5, 'total' => 80],   // global-ui
            ['string' => 'Niigaaniin', 'distinct_sites' => 4, 'total' => 60], // anishinaabemowin -> excluded
            ['string' => 'Skip to content', 'distinct_sites' => 8, 'total' => 150], // noise -> dropped
            ['string' => 'June 2026', 'distinct_sites' => 3, 'total' => 40], // date noise -> dropped
            ['string' => 'Their Own Program', 'distinct_sites' => 1, 'total' => 5], // below floor
        ];
    }

    #[Test]
    public function it_keeps_only_cross_site_strings_and_logs_exclusions(): void
    {
        $out = (new BacklogBuilder())->build(self::sample());

        self::assertSame(['Niigaaniin'], $out['excluded_anishinaabemowin']);
        self::assertSame(7, $out['stats']['considered']);
        self::assertSame(1, $out['stats']['below_floor']);
        self::assertSame(2, $out['stats']['dropped_noise']);
        self::assertSame(1, $out['stats']['excluded_ojibwe']);
        self::assertSame(3, $out['stats']['kept']);

        $texts = array_column($out['rows'], 'english_text');
        self::assertSame(['Contact', 'Contact Us', 'Search'], $texts, 'Ranked by demand_sites desc.');
    }

    #[Test]
    public function it_clusters_and_categorises_kept_rows(): void
    {
        $rows = (new BacklogBuilder())->build(self::sample())['rows'];
        $byText = [];
        foreach ($rows as $r) {
            $byText[$r['english_text']] = $r;
        }

        self::assertSame($byText['Contact']['concept_key'], $byText['Contact Us']['concept_key']);
        self::assertSame('governance-nav', $byText['Contact']['category']);
        self::assertSame('global-ui', $byText['Search']['category']);
    }
}
