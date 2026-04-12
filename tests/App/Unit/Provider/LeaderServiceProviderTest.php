<?php

declare(strict_types=1);

namespace App\Tests\Unit\Provider;

use App\Provider\AppServiceProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(AppServiceProvider::class)]
final class LeaderServiceProviderTest extends TestCase
{
    #[Test]
    public function it_registers_leader_entity_type(): void
    {
        $provider = new AppServiceProvider();
        $provider->register();

        $types = $provider->getEntityTypes();
        $leaderType = null;

        foreach ($types as $type) {
            if ($type->id() === 'leader') {
                $leaderType = $type;
                break;
            }
        }

        $this->assertNotNull($leaderType, 'leader entity type should be registered');
        $this->assertSame('Leader', $leaderType->getLabel());
        $this->assertSame('people', $leaderType->getGroup());
        $this->assertSame('lid', $leaderType->getKeys()['id']);
    }
}
