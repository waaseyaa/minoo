<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Ingest;

use Minoo\Ingest\MaterializationContext;
use Minoo\Ingest\MaterializationResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(MaterializationContext::class)]
#[CoversClass(MaterializationResult::class)]
final class MaterializationContextTest extends TestCase
{
    #[Test]
    public function it_caches_resolved_speakers(): void
    {
        $ctx = new MaterializationContext();

        $this->assertNull($ctx->getSpeakerId('es'));

        $ctx->setSpeakerId('es', 42);

        $this->assertSame(42, $ctx->getSpeakerId('es'));
    }

    #[Test]
    public function it_caches_resolved_word_parts(): void
    {
        $ctx = new MaterializationContext();

        $this->assertNull($ctx->getWordPartId('makw-', 'initial'));

        $ctx->setWordPartId('makw-', 'initial', 7);

        $this->assertSame(7, $ctx->getWordPartId('makw-', 'initial'));
    }

    #[Test]
    public function materialization_result_tracks_created_entities(): void
    {
        $result = new MaterializationResult();

        $result->addCreated('speaker', ['name' => 'Eugene Stillday', 'code' => 'es']);
        $result->addCreated('dictionary_entry', ['word' => 'makwa']);

        $this->assertCount(2, $result->getCreated());
        $this->assertSame('speaker', $result->getCreated()[0]['type']);
    }

    #[Test]
    public function materialization_result_tracks_skipped_entities(): void
    {
        $result = new MaterializationResult();

        $result->addSkipped('speaker', 'es', 'Already exists');

        $this->assertCount(1, $result->getSkipped());
    }
}
