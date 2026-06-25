<?php

declare(strict_types=1);

namespace App\Language\Asr;

/**
 * The default, fail-closed ASR client (issue #896).
 *
 * Always unavailable: no transcription happens on this install until the Phase 0
 * consent agreement with Steven Bennett / Sagamok exists and a real worker-backed
 * client replaces this binding. Every call returns a non-ok result explaining the
 * gate, so a future caller cannot accidentally transcribe ungated audio.
 */
final class UnavailableAsrClient implements AsrClient
{
    private const string GATE_MESSAGE = 'ASR is gated behind the Phase 0 consent agreement and is not available on this install.';

    public function isAvailable(): bool
    {
        return false;
    }

    public function transcribe(string $audioReference, ?string $dialect = null): AsrResult
    {
        return AsrResult::unavailable(self::GATE_MESSAGE);
    }
}
