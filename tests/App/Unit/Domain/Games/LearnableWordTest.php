<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Games;

use App\Domain\Games\LearnableWord;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(LearnableWord::class)]
final class LearnableWordTest extends TestCase
{
    #[Test]
    public function accepts_a_common_word_with_a_concise_definition(): void
    {
        $this->assertTrue(LearnableWord::isLearnable('makwa', 'a bear'));
        $this->assertTrue(LearnableWord::isLearnable('nibi', 'water'));
    }

    #[Test]
    public function rejects_capitalised_proper_nouns_and_sacred_terms(): void
    {
        // Ceremony / proper-noun entries are capitalised in the dictionary.
        $this->assertFalse(LearnableWord::isLearnable('Midewiwin', 'Grand Medicine Society'));
        $this->assertFalse(LearnableWord::isLearnable('Gichigami', 'Lake Superior'));
    }

    #[Test]
    public function rejects_abbreviation_only_and_long_glosses(): void
    {
        $this->assertFalse(LearnableWord::isLearnable('aabaji', 'vai'));
        $this->assertFalse(LearnableWord::isLearnable(
            'aakomaasige',
            'she/he spreads a stink, an acrid smell by cooking or burning something',
        ));
    }

    #[Test]
    public function rejects_malformed_or_multi_token_headwords(): void
    {
        $this->assertFalse(LearnableWord::isLearnable('', 'something'));
        $this->assertFalse(LearnableWord::isLearnable('ab', 'too short'));
        $this->assertFalse(LearnableWord::isLearnable('two words', 'a phrase'));
        $this->assertFalse(LearnableWord::isLearnable('makwa', ''));
    }

    #[Test]
    public function keeps_a_multi_sense_word_when_the_first_sense_is_concise(): void
    {
        $this->assertTrue(LearnableWord::isLearnable('animosh', 'a dog; a pet'));
    }

    #[Test]
    public function sample_ids_returns_input_when_it_already_fits(): void
    {
        $ids = [1, 2, 3];
        $this->assertSame($ids, LearnableWord::sampleIds($ids, 10));
        $this->assertSame($ids, LearnableWord::sampleIds($ids, 3));
    }

    #[Test]
    public function sample_ids_returns_a_subset_of_the_requested_size(): void
    {
        $ids = range(1, 100);
        $sample = LearnableWord::sampleIds($ids, 10);
        $this->assertCount(10, $sample);
        foreach ($sample as $id) {
            $this->assertContains($id, $ids);
        }
        $this->assertSame($sample, array_unique($sample));
    }
}
