<?php

declare(strict_types=1);

namespace App\Tests\Unit\Provider;

use App\Provider\IngestServiceProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(IngestServiceProvider::class)]
final class IngestServiceProviderTest extends TestCase
{
    #[Test]
    public function it_registers_ingest_log_entity_type(): void
    {
        $provider = new IngestServiceProvider();
        $provider->register();

        $types = $provider->getEntityTypes();

        $this->assertCount(1, $types);
        $this->assertSame('ingest_log', $types[0]->id());
        $this->assertSame('Ingestion Log', $types[0]->getLabel());
        $this->assertSame('ingestion', $types[0]->getGroup());
        $this->assertSame('ilid', $types[0]->getKeys()['id']);
    }
}
