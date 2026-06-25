<?php

declare(strict_types=1);

namespace App\Language\Asr;

/**
 * The outcome of an ASR transcription request (issue #896).
 *
 * Either a transcript (ok) or an explained failure (not ok), for example the
 * Phase 0 consent gate not being satisfied. A value object so callers branch on
 * `ok` rather than on exceptions for the expected gated-off case.
 */
final readonly class AsrResult
{
    private function __construct(
        public bool $ok,
        public string $transcript,
        public string $message,
    ) {
    }

    public static function transcript(string $transcript): self
    {
        return new self(true, $transcript, '');
    }

    public static function unavailable(string $message): self
    {
        return new self(false, '', $message);
    }
}
