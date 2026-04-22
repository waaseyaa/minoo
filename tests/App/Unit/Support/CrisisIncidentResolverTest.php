<?php

declare(strict_types=1);

namespace App\Tests\Unit\Support;

use App\Support\CrisisIncidentResolver;
use App\Support\CrisisResolveContext;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(CrisisIncidentResolver::class)]
final class CrisisIncidentResolverTest extends TestCase
{
    private string $projectRoot;

    protected function setUp(): void
    {
        $this->projectRoot = dirname(__DIR__, 4);
    }

    #[Test]
    public function resolve_returns_sagamok_spanish_river_flood(): void
    {
        $r = new CrisisIncidentResolver($this->projectRoot);
        $out = $r->resolve('sagamok-anishnawbek', 'spanish-river-flood', CrisisResolveContext::publicWeb());
        self::assertNotNull($out);
        self::assertSame('sagamok-anishnawbek', $out['registry']['community_slug']);
        self::assertSame('sagamok_flood.title', $out['incident']['title_key']);
    }

    #[Test]
    public function resolve_returns_null_for_unknown_pair(): void
    {
        $r = new CrisisIncidentResolver($this->projectRoot);
        self::assertNull($r->resolve('sagamok-anishnawbek', 'nonexistent-incident', CrisisResolveContext::publicWeb()));
        self::assertNull($r->resolve('no-such-community', 'spanish-river-flood', CrisisResolveContext::publicWeb()));
    }

    #[Test]
    public function resolve_returns_null_for_draft_sudbury_on_public_web(): void
    {
        $r = new CrisisIncidentResolver($this->projectRoot);
        self::assertNull($r->resolve('sudbury', 'state-of-emergency', CrisisResolveContext::publicWeb()));
    }

    #[Test]
    public function resolve_includes_draft_when_context_allows(): void
    {
        $r = new CrisisIncidentResolver($this->projectRoot);
        $out = $r->resolve('sudbury', 'state-of-emergency', CrisisResolveContext::withDraftIncidents());
        self::assertNotNull($out);
        self::assertSame('sudbury_soe.title', $out['incident']['title_key']);
    }

    #[Test]
    public function hub_callout_returns_sagamok_keys(): void
    {
        $r = new CrisisIncidentResolver($this->projectRoot);
        $c = $r->hubCalloutForCommunity('sagamok-anishnawbek', CrisisResolveContext::publicWeb());
        self::assertNotNull($c);
        self::assertSame('sagamok_flood.community_callout_title', $c['title_key']);
        self::assertStringContainsString('spanish-river-flood', $c['href']);
    }

    #[Test]
    public function hub_callout_skips_draft_sudbury(): void
    {
        $r = new CrisisIncidentResolver($this->projectRoot);
        self::assertNull($r->hubCalloutForCommunity('sudbury', CrisisResolveContext::publicWeb()));
    }
}
