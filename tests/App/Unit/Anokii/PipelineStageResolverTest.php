<?php

declare(strict_types=1);

namespace App\Tests\Unit\Anokii;

use App\Anokii\Pipeline\PipelineStage;
use App\Anokii\Pipeline\PipelineStageResolver;
use App\Entity\Language\ExampleSentence;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(PipelineStageResolver::class)]
#[CoversClass(PipelineStage::class)]
final class PipelineStageResolverTest extends TestCase
{
    private PipelineStageResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new PipelineStageResolver();
    }

    #[Test]
    public function explicit_pipeline_status_always_wins(): void
    {
        // A row that would derive to "curated" (it has a dictionary link) but
        // carries an explicit stage must report the explicit stage verbatim.
        $sentence = new ExampleSentence([
            'ojibwe_text' => 'Aaniin',
            'english_text' => 'Hello',
            'dictionary_entry_id' => 7,
            'pipeline_status' => PipelineStage::TRANSCRIBED,
        ]);

        self::assertSame(PipelineStage::TRANSCRIBED, $this->resolver->resolve($sentence));
    }

    #[Test]
    public function invalid_explicit_value_falls_back_to_derivation(): void
    {
        $sentence = new ExampleSentence([
            'ojibwe_text' => 'Aaniin',
            'english_text' => 'Hello',
            'pipeline_status' => 'garbage',
        ]);

        self::assertSame(PipelineStage::TRANSCRIBED, $this->resolver->resolve($sentence));
    }

    #[Test]
    public function empty_row_derives_ingested(): void
    {
        $sentence = new ExampleSentence(['ojibwe_text' => '', 'english_text' => '']);

        self::assertSame(PipelineStage::INGESTED, $this->resolver->resolve($sentence));
    }

    #[Test]
    public function row_with_media_but_no_translation_derives_drafted(): void
    {
        $sentence = new ExampleSentence([
            'ojibwe_text' => '',
            'english_text' => '',
            'thumbnail_url' => '/media/corpus/thumb/sb-1',
        ]);

        self::assertSame(PipelineStage::DRAFTED, $this->resolver->resolve($sentence));
    }

    #[Test]
    public function row_with_both_languages_derives_transcribed(): void
    {
        $sentence = new ExampleSentence([
            'ojibwe_text' => 'Mino-bimaadiziwin',
            'english_text' => 'the good life',
        ]);

        self::assertSame(PipelineStage::TRANSCRIBED, $this->resolver->resolve($sentence));
    }

    #[Test]
    public function promoted_unpublished_row_derives_curated(): void
    {
        $sentence = new ExampleSentence([
            'ojibwe_text' => 'Makwa',
            'english_text' => 'bear',
            'dictionary_entry_id' => 12,
            'status' => 0,
            'consent_public' => 0,
        ]);

        self::assertSame(PipelineStage::CURATED, $this->resolver->resolve($sentence));
    }

    #[Test]
    public function promoted_public_row_derives_published(): void
    {
        $sentence = new ExampleSentence([
            'ojibwe_text' => 'Makwa',
            'english_text' => 'bear',
            'dictionary_entry_id' => 12,
            'status' => 1,
            'consent_public' => 1,
        ]);

        self::assertSame(PipelineStage::PUBLISHED, $this->resolver->resolve($sentence));
    }
}
