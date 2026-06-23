<?php

declare(strict_types=1);

namespace App\Ingestion\Corpus;

/**
 * Drafts the Ojibwe + English gloss from a whiteboard keyframe via a vision LLM.
 * Behind a seam so ingest can run (and be tested) without an LLM provider.
 */
interface WhiteboardReaderInterface
{
    /**
     * @return array{ojibwe: string, english: string, notes: string}
     *         Best-effort draft; blank strings when the reader cannot determine a value.
     *         The Ojibwe is returned exactly as read — never normalized (ADR 0003).
     */
    public function read(string $keyframePath): array;
}
