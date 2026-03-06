<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Entity;

use Minoo\Entity\WordPart;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(WordPart::class)]
final class WordPartTest extends TestCase
{
    #[Test]
    public function it_creates_with_required_fields(): void
    {
        $part = new WordPart([
            'form' => 'minw-',
            'type' => 'initial',
        ]);

        $this->assertSame('minw-', $part->get('form'));
        $this->assertSame('initial', $part->get('type'));
        $this->assertSame('word_part', $part->getEntityTypeId());
    }

    #[Test]
    public function it_supports_definition_and_source(): void
    {
        $part = new WordPart([
            'form' => '-aabid-',
            'type' => 'medial',
            'definition' => 'tooth, teeth',
            'source_url' => 'https://ojibwe.lib.umn.edu/word-part/aabid-medial',
        ]);

        $this->assertSame('tooth, teeth', $part->get('definition'));
        $this->assertSame('https://ojibwe.lib.umn.edu/word-part/aabid-medial', $part->get('source_url'));
    }
}
