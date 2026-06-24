<?php

declare(strict_types=1);

namespace App\Anokii\Pipeline;

/**
 * The Anokii corpus pipeline (#876). Every corpus item moves through these
 * ordered stages, and the whole workspace UI is driven off them:
 *
 *   ingested   raw upload, no draft yet
 *   drafted    vision/ASR filled, unreviewed
 *   transcribed human-confirmed Ojibwe + English
 *   curated    promoted to dictionary/lesson
 *   published  live on public surfaces
 *
 * Stage is stored verbatim in example_sentence.pipeline_status; legacy rows are
 * resolved on the fly by {@see PipelineStageResolver}. This class is the single
 * source of truth for stage identity, ordering, labels, and where each stage's
 * items live in the workspace.
 */
final class PipelineStage
{
    public const string INGESTED = 'ingested';
    public const string DRAFTED = 'drafted';
    public const string TRANSCRIBED = 'transcribed';
    public const string CURATED = 'curated';
    public const string PUBLISHED = 'published';

    /** Stages in pipeline order. */
    public const array ORDER = [
        self::INGESTED,
        self::DRAFTED,
        self::TRANSCRIBED,
        self::CURATED,
        self::PUBLISHED,
    ];

    /** @var array<string, string> */
    private const array LABELS = [
        self::INGESTED => 'Ingested',
        self::DRAFTED => 'Awaiting transcription',
        self::TRANSCRIBED => 'Ready to curate',
        self::CURATED => 'In lessons',
        self::PUBLISHED => 'Published',
    ];

    /**
     * Where the funnel sends a reviewer to act on a stage's items. Each carries a
     * `?stage=` so the destination tab can filter to exactly those rows (#878).
     *
     * @var array<string, string>
     */
    private const array HREFS = [
        self::INGESTED => '/admin/anokii/ingest?stage=ingested',
        self::DRAFTED => '/admin/anokii/transcribe?stage=drafted',
        self::TRANSCRIBED => '/admin/anokii/curate?stage=transcribed',
        self::CURATED => '/admin/anokii/curate?stage=curated',
        self::PUBLISHED => '/admin/anokii/curate?stage=published',
    ];

    public static function isValid(string $stage): bool
    {
        return in_array($stage, self::ORDER, true);
    }

    public static function label(string $stage): string
    {
        return self::LABELS[$stage] ?? ucfirst($stage);
    }

    public static function href(string $stage): string
    {
        return self::HREFS[$stage] ?? '/admin/anokii';
    }
}
