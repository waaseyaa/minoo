<?php

declare(strict_types=1);

namespace Minoo\Controller;

use Minoo\Entity\GameSession;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\SSR\SsrResponse;

/**
 * Shared helpers for game controllers (Shkoda, Crossword).
 *
 * Requires the using class to have a readonly $entityTypeManager property.
 */
trait GameControllerTrait
{
    abstract private function getEntityTypeManager(): EntityTypeManager;

    /** @return array<string, mixed> */
    private function jsonBody(HttpRequest $request): array
    {
        $content = $request->getContent();
        if ($content === '') {
            return [];
        }
        try {
            return (array) json_decode((string) $content, true, 16, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }
    }

    /** @param array<string, mixed> $data */
    private function json(array $data, int $status = 200): SsrResponse
    {
        return new SsrResponse(
            content: json_encode($data, JSON_THROW_ON_ERROR),
            statusCode: $status,
            headers: ['Content-Type' => 'application/json'],
        );
    }

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
