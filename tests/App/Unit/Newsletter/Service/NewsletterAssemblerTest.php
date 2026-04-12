<?php
declare(strict_types=1);

namespace App\Tests\Unit\Newsletter\Service;

use App\Domain\Newsletter\Assembler\ItemCandidate;
use App\Domain\Newsletter\Service\NewsletterAssembler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Smoke-level coverage for NewsletterAssembler.
 *
 * The framework's `Waaseyaa\Entity\Storage\EntityStorageInterface` exposes a
 * wider surface than is convenient to fake by hand (`loadByKey`,
 * `getEntityTypeId`, an `EntityQueryInterface` return type, `save` returning
 * `int`, etc.). The implementation plan for #641 explicitly permits
 * downgrading the unit test to a smoke and relying on the in-memory
 * `:memory:` SQLite kernel boot in Task 12 (`NewsletterEndToEndTest`) for
 * real behavioural verification.
 *
 * What this test guarantees:
 *   - The class loads under PSR-4 / autoload.
 *   - The `ItemCandidate` DTO loads and round-trips its fields.
 *   - The `assemble()` method exists with the expected signature.
 *
 * Real behavioural assertions (quota capping, ordering by recency,
 * idempotent re-assemble, draft -> curating transition, submission
 * filtering by community + approved status) live in the integration test
 * delivered with Task 12.
 */
#[CoversClass(NewsletterAssembler::class)]
#[CoversClass(ItemCandidate::class)]
final class NewsletterAssemblerTest extends TestCase
{
    #[Test]
    public function assembler_class_exists_and_has_assemble_method(): void
    {
        $this->assertTrue(class_exists(NewsletterAssembler::class));

        $reflection = new \ReflectionClass(NewsletterAssembler::class);
        $this->assertTrue($reflection->isFinal(), 'NewsletterAssembler must be final');
        $this->assertTrue($reflection->hasMethod('assemble'));

        $assemble = $reflection->getMethod('assemble');
        $this->assertSame('void', (string) $assemble->getReturnType());
        $this->assertCount(1, $assemble->getParameters());
        $this->assertSame(
            'Waaseyaa\\Entity\\EntityInterface',
            (string) $assemble->getParameters()[0]->getType(),
        );
    }

    #[Test]
    public function item_candidate_round_trips_its_fields(): void
    {
        $candidate = new ItemCandidate(
            section: 'events',
            sourceType: 'event',
            sourceId: 42,
            blurb: 'Sugar Bush Day',
            score: 0.875,
        );

        $this->assertSame('events', $candidate->section);
        $this->assertSame('event', $candidate->sourceType);
        $this->assertSame(42, $candidate->sourceId);
        $this->assertSame('Sugar Bush Day', $candidate->blurb);
        $this->assertSame(0.875, $candidate->score);

        $reflection = new \ReflectionClass(ItemCandidate::class);
        $this->assertTrue($reflection->isFinal());
        $this->assertTrue($reflection->isReadOnly());
    }
}
