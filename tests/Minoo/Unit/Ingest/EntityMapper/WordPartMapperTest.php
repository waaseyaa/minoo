<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Ingest\EntityMapper;

use Minoo\Ingest\EntityMapper\WordPartMapper;
use Minoo\Ingest\ValueObject\WordPartFields;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(WordPartMapper::class)]
#[CoversClass(WordPartFields::class)]
final class WordPartMapperTest extends TestCase
{
    #[Test]
    public function it_maps_word_part(): void
    {
        $mapper = new WordPartMapper();
        $data = ['form' => 'makw-', 'morphological_role' => 'initial', 'definition' => 'bear'];

        $result = $mapper->map($data, 'https://example.com');

        $this->assertInstanceOf(WordPartFields::class, $result);
        $this->assertSame('makw-', $result->form);
        $this->assertSame('initial', $result->type);
        $this->assertSame('bear', $result->definition);
        $this->assertSame('makw', $result->slug);
        $this->assertSame(0, $result->status);
    }

    #[Test]
    public function it_rejects_invalid_morphological_role(): void
    {
        $mapper = new WordPartMapper();
        $data = ['form' => 'test', 'morphological_role' => 'invalid', 'definition' => 'test'];

        $this->assertNull($mapper->map($data, ''));
    }

    #[Test]
    public function it_converts_to_array(): void
    {
        $mapper = new WordPartMapper();
        $data = ['form' => 'makw-', 'morphological_role' => 'initial', 'definition' => 'bear'];

        $array = $mapper->map($data, 'https://example.com')->toArray();

        $this->assertSame('makw-', $array['form']);
        $this->assertSame('initial', $array['type']);
    }
}
