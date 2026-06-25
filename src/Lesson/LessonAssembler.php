<?php

declare(strict_types=1);

namespace App\Lesson;

use Waaseyaa\Entity\EntityInterface;
use Waaseyaa\Entity\EntityTypeManager;

/**
 * Assembles a lesson page dynamically from the corpus (#912).
 *
 * A lesson renders the `example_sentence` rows assigned to its `lesson_slug`
 * that are published, consent-public, and curated (have a `dictionary_entry`),
 * grouped by `lesson_group` and ordered by `lesson_weight`. The Anishinaabemowin
 * is read verbatim from each row; the English is the curated gloss from the
 * linked `dictionary_entry` definition. Drafts / rough data never appear because
 * the gate requires status=1, consent_public=1, and a dictionary_entry.
 *
 * The card shape matches `templates/pages/lesson/lesson1.html.twig`, so the
 * renderer is unchanged. Replaces the old hardcoded config/lessonN.php item
 * lists (which now hold only {title, slug}).
 */
final class LessonAssembler
{
    private const string MEDIA_THUMB = '/lesson/media/thumb/';
    private const string MEDIA_AUDIO = '/lesson/media/audio/';
    private const string MEDIA_VIDEO = '/lessons/media/video/';

    /** Heading for cards added to a lesson without a section label. */
    private const string DEFAULT_GROUP = 'More words';

    public function __construct(private readonly EntityTypeManager $entityTypeManager)
    {
    }

    /**
     * @return array{groups: list<array{label: string, items: list<array{id: string, ojibwe: string, english: string, thumb: string, audio: string, video: string|null, source_url: string}>}>, total: int}
     */
    public function assemble(string $slug): array
    {
        $rows = $this->assignedRows($slug);
        if ($rows === []) {
            return ['groups' => [], 'total' => 0];
        }

        $entries = $this->entityTypeManager->getStorage('dictionary_entry');
        $cards = [];
        foreach ($rows as $row) {
            $deid = (int) ($row->get('dictionary_entry_id') ?? 0);
            $entry = $deid > 0 ? $entries->load($deid) : null;
            if (!$entry instanceof EntityInterface) {
                continue; // curated gate already required deid>0; defensive.
            }
            $id = str_replace('corpus:', '', (string) $row->get('source_sentence_id'));
            $hasVideo = trim((string) ($row->get('video_url') ?? '')) !== '';
            $cards[] = [
                '_group' => trim((string) ($row->get('lesson_group') ?? '')),
                '_weight' => (int) ($row->get('lesson_weight') ?? 0),
                '_esid' => (int) $row->id(),
                'id' => $id,
                // Verbatim corpus Anishinaabemowin (ADR 0003), never altered.
                'ojibwe' => (string) $row->get('ojibwe_text'),
                'english' => $this->gloss((string) $entry->get('definition')),
                'thumb' => self::MEDIA_THUMB . $id,
                'audio' => self::MEDIA_AUDIO . $id,
                'video' => $hasVideo ? self::MEDIA_VIDEO . $id : null,
                'source_url' => (string) $row->get('source_url'),
            ];
        }

        usort($cards, static fn (array $a, array $b): int => $a['_weight'] <=> $b['_weight'] ?: $a['_esid'] <=> $b['_esid']);

        // Bucket consecutive (weight-ordered) cards into sections by label; a
        // section appears at its lowest-weight card, so kitchen sections keep
        // their config order.
        $groups = [];
        $byLabel = [];
        foreach ($cards as $card) {
            $label = $card['_group'] !== '' ? $card['_group'] : self::DEFAULT_GROUP;
            unset($card['_group'], $card['_weight'], $card['_esid']);
            if (!isset($byLabel[$label])) {
                $byLabel[$label] = count($groups);
                $groups[] = ['label' => $label, 'items' => []];
            }
            $groups[$byLabel[$label]]['items'][] = $card;
        }

        return ['groups' => $groups, 'total' => count($cards)];
    }

    /**
     * Corpus media ids servable through the lesson media endpoint: every
     * published+public+curated row assigned to a known lesson.
     *
     * @param list<string> $slugs
     *
     * @return list<string>
     */
    public function allowedMediaIds(array $slugs): array
    {
        $ids = [];
        foreach ($slugs as $slug) {
            foreach ($this->assignedRows($slug) as $row) {
                $ids[] = str_replace('corpus:', '', (string) $row->get('source_sentence_id'));
            }
        }

        return array_values(array_unique($ids));
    }

    /** @return list<EntityInterface> */
    private function assignedRows(string $slug): array
    {
        if ($slug === '') {
            return [];
        }
        $storage = $this->entityTypeManager->getStorage('example_sentence');
        // Public surface: only published, consent-public, curated rows assigned to
        // this lesson. accessCheck(false) - the gate is status+consent, mirroring
        // LessonController::corpusRowsBySourceId (see the bypass audit doc).
        $ids = $storage->getQuery()->accessCheck(false)
            ->condition('lesson_slug', $slug)
            ->condition('status', 1)
            ->condition('consent_public', 1)
            ->condition('dictionary_entry_id', 0, '>')
            ->execute();
        if ($ids === []) {
            return [];
        }

        return array_values($storage->loadMultiple($ids));
    }

    /** Decode a JSON-wrapped dictionary definition (e.g. ["cup, glass"]) to display text. */
    private function gloss(string $definition): string
    {
        $decoded = json_decode($definition, true);
        if (is_array($decoded)) {
            return implode('; ', array_map(static fn (mixed $v): string => trim((string) $v), $decoded));
        }

        return trim($definition);
    }
}
