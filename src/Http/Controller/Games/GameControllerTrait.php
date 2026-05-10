<?php

declare(strict_types=1);

namespace App\Http\Controller\Games;

use App\Entity\Games\GameSession;
use App\Http\Controller\Concerns\JsonResponseTrait;
use Waaseyaa\Entity\EntityTypeManager;

/**
 * Shared helpers for game API controllers (Shkoda, Crossword, Matcher, Agim, Journey, etc.).
 *
 * Requires the using class to have a readonly $entityTypeManager property.
 */
trait GameControllerTrait
{
    use JsonResponseTrait;

    abstract private function getEntityTypeManager(): EntityTypeManager;

    /** Extract a clean definition string from a field that may be JSON-encoded. */
    private function cleanDefinition(string $raw): string
    {
        if ($raw === '') {
            return '';
        }
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $raw = implode('; ', array_filter(array_map('trim', $decoded)));
        }

        // Expand OPD linguistic abbreviations for readability
        // Order matters: longer patterns first to avoid partial replacement
        $raw = str_replace(
            ['h/self', 's/he', 'h/', 's.t.', 's.o.'],
            ['himself/herself', 'she/he', 'him/her', 'something', 'someone'],
            $raw,
        );

        return $raw;
    }

    private function loadSessionByToken(string $uuid): ?GameSession
    {
        $entity = $this->getEntityTypeManager()->getStorage('game_session')->loadByKey('uuid', $uuid);
        return $entity instanceof GameSession ? $entity : null;
    }
}
