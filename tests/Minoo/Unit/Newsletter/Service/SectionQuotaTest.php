<?php
declare(strict_types=1);

namespace Minoo\Tests\Unit\Newsletter\Service;

use Minoo\Domain\Newsletter\ValueObject\SectionQuota;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(SectionQuota::class)]
final class SectionQuotaTest extends TestCase
{
    #[Test]
    public function builds_from_config_array(): void
    {
        $config = [
            'news' => ['quota' => 4, 'sources' => ['post']],
            'events' => ['quota' => 6, 'sources' => ['event']],
        ];

        $quotas = SectionQuota::fromConfig($config);

        $this->assertCount(2, $quotas);
        $this->assertSame('news', $quotas[0]->name);
        $this->assertSame(4, $quotas[0]->quota);
        $this->assertSame(['post'], $quotas[0]->sources);
        $this->assertSame('events', $quotas[1]->name);
        $this->assertSame(6, $quotas[1]->quota);
    }

    #[Test]
    public function quota_must_be_positive(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new SectionQuota('news', 0, ['post']);
    }
}
