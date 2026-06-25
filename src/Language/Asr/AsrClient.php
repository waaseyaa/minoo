<?php

declare(strict_types=1);

namespace App\Language\Asr;

/**
 * The seam the language module resolves to reach the separate Python/GPU ASR
 * worker (issue #896, decision D8).
 *
 * minoo.live never transcribes in-process: the Pi serves inference only, and
 * training and transcription run on a separate GPU worker this client calls. The
 * default binding is {@see UnavailableAsrClient}, which is fail-closed until the
 * Phase 0 consent agreement with Steven Bennett / Sagamok exists and a real HTTP
 * client is wired. There is no public ASR surface yet.
 */
interface AsrClient
{
    /**
     * Whether transcription is available on this install (a worker is configured
     * and the consent gate is satisfied).
     */
    public function isAvailable(): bool;

    /**
     * Transcribe an audio reference (a corpus path or id) in the given dialect.
     * Returns a non-ok {@see AsrResult} when unavailable rather than throwing for
     * the expected gated-off case.
     */
    public function transcribe(string $audioReference, ?string $dialect = null): AsrResult;
}
