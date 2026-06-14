<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Community;

use App\Domain\Community\MamaweswenNations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(MamaweswenNations::class)]
final class MamaweswenNationsTest extends TestCase
{
    #[Test]
    public function lists_the_seven_member_nations(): void
    {
        $this->assertCount(7, MamaweswenNations::all());
    }

    #[Test]
    public function exactly_one_anchor_and_it_is_sagamok(): void
    {
        $anchors = array_values(array_filter(MamaweswenNations::all(), static fn (array $n): bool => $n['anchor']));
        $this->assertCount(1, $anchors);
        $this->assertSame('sagamok-anishnawbek', $anchors[0]['slug']);
    }

    #[Test]
    public function every_nation_has_complete_factual_fields_and_plausible_coordinates(): void
    {
        foreach (MamaweswenNations::all() as $nation) {
            $this->assertNotSame('', $nation['slug']);
            $this->assertNotSame('', $nation['name']);
            $this->assertGreaterThan(0, $nation['band_number']);
            $this->assertNotSame('', $nation['reserve']);
            $this->assertNotSame('', $nation['chief']);
            $this->assertStringContainsString('Robinson-Huron', $nation['treaty']);
            // North shore of Lake Huron / eastern Lake Superior, Ontario.
            $this->assertGreaterThan(45.5, $nation['lat']);
            $this->assertLessThan(47.5, $nation['lat']);
            $this->assertGreaterThan(-85.0, $nation['lng']);
            $this->assertLessThan(-80.0, $nation['lng']);
        }
    }

    #[Test]
    public function by_slug_resolves_known_nations_and_returns_null_otherwise(): void
    {
        $this->assertSame('Serpent River First Nation', MamaweswenNations::bySlug('serpent-river')['name']);
        $this->assertNull(MamaweswenNations::bySlug('not-a-nation'));
    }

    #[Test]
    public function governance_url_points_at_the_isc_profile_for_the_band(): void
    {
        $url = MamaweswenNations::governanceUrl(179);
        $this->assertStringContainsString('FNGovernance.aspx', $url);
        $this->assertStringContainsString('BAND_NUMBER=179', $url);
    }
}
