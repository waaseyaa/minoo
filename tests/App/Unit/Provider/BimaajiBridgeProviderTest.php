<?php

declare(strict_types=1);

namespace App\Tests\Unit\Provider;

use App\Provider\BimaajiBridgeProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Bimaaji\Graph\ApplicationGraphGenerator;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;

#[CoversClass(BimaajiBridgeProvider::class)]
final class BimaajiBridgeProviderTest extends TestCase
{
    private BimaajiBridgeProvider $provider;

    protected function setUp(): void
    {
        $this->provider = new BimaajiBridgeProvider();

        $etm = $this->createMock(EntityTypeManager::class);

        $this->provider->setKernelResolver(function (string $abstract) use ($etm): ?object {
            if ($abstract === EntityTypeManager::class) {
                return $etm;
            }
            return null;
        });

        $this->provider->register();
    }

    #[Test]
    public function it_extends_service_provider(): void
    {
        $this->assertInstanceOf(ServiceProvider::class, $this->provider);
    }

    #[Test]
    public function it_registers_application_graph_generator_as_singleton(): void
    {
        $generator = $this->provider->resolve(ApplicationGraphGenerator::class);

        $this->assertInstanceOf(ApplicationGraphGenerator::class, $generator);
    }

    #[Test]
    public function it_returns_same_instance_on_repeated_resolve(): void
    {
        $first = $this->provider->resolve(ApplicationGraphGenerator::class);
        $second = $this->provider->resolve(ApplicationGraphGenerator::class);

        $this->assertSame($first, $second);
    }
}
