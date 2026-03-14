<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Provider;

use Minoo\Provider\LeaderServiceProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(LeaderServiceProvider::class)]
final class LeaderServiceProviderTest extends TestCase
{
    #[Test]
    public function it_registers_leader_entity_type(): void
    {
        $provider = new LeaderServiceProvider();
        $provider->register();

        $types = $provider->getEntityTypes();

        $this->assertCount(1, $types);
        $this->assertSame('leader', $types[0]->id());
        $this->assertSame('Leader', $types[0]->getLabel());
        $this->assertSame('people', $types[0]->getGroup());
        $this->assertSame('lid', $types[0]->getKeys()['id']);
    }
}
