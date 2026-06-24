<?php

declare(strict_types=1);

namespace App\Http\Controller\Lesson;

use App\Http\View\LayoutTwigContext;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\EntityInterface;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\SSR\Attribute\MapQuery;
use Waaseyaa\SSR\Attribute\MapRoute;

/**
 * Lesson 1 (Kitchen) course surface over the Elder corpus.
 *
 * Public since Phase 4: written consent has been granted, so the corpus rows
 * are published (consent_public + status 1) and these routes are open. The
 * Anishinaabemowin is read verbatim from the corpus at runtime, never altered
 * or committed. Audio and whiteboard thumbnails are streamed from the
 * community-controlled corpus directory (MINOO_CORPUS_PATH), never copied in.
 */
final class LessonController
{
    /**
     * Location of the community-controlled corpus directory (audio + whiteboard
     * thumbnails). Override with MINOO_CORPUS_PATH. Nothing under this path is
     * committed to the repository.
     */
    private const string DEFAULT_CORPUS_PATH = 'C:/Users/jones/Projects/LLC/anishinaabemowin/content/corpus';

    /** Steven Bennett's Facebook profile, credited as the source of every card. */
    private const string STEVEN_PROFILE_URL = 'https://www.facebook.com/profile.php?id=61582894730998';

    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
        private readonly Environment $twig,
    ) {
    }

    /** Landing: the list of available lessons. */
    public function index(#[MapRoute] array $params, #[MapQuery] array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $lessons = [];
        foreach ($this->lessons() as $lesson) {
            $def = $this->lessonDefinition($lesson['config']);
            $lessons[] = [
                'number' => $lesson['number'],
                'title' => $def['title'],
                'slug' => $lesson['slug'],
                'url' => '/lessons/' . $lesson['slug'],
                'count' => $this->lessonItemCount($def),
            ];
        }

        $html = $this->twig->render('pages/lesson/index.html.twig', LayoutTwigContext::withAccount($account, [
            'path' => '/lessons',
            'lessons' => $lessons,
            'steven_url' => self::STEVEN_PROFILE_URL,
        ]));

        return new Response($html);
    }

    /** A single lesson by slug: cards grouped by section. */
    public function show(#[MapRoute] array $params, #[MapQuery] array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $slug = (string) ($params['slug'] ?? '');
        $lesson = $this->lessonBySlug($slug);
        if ($lesson === null) {
            return new Response(
                $this->twig->render('404.html.twig', LayoutTwigContext::withAccount($account, ['path' => $request->getPathInfo()])),
                404,
            );
        }

        $def = $this->lessonDefinition($lesson['config']);
        $corpus = $this->corpusRowsBySourceId();

        $groups = [];
        $total = 0;
        foreach ($def['groups'] as $group) {
            $items = [];
            foreach ($group['items'] as $item) {
                $row = $corpus['corpus:' . $item['id']] ?? null;
                if (!$row instanceof EntityInterface) {
                    // Corpus row not present: skip rather than invent a card.
                    continue;
                }
                $items[] = [
                    'id' => $item['id'],
                    // Anishinaabemowin, verbatim from the migrated corpus. Never altered.
                    'ojibwe' => (string) $row->get('ojibwe_text'),
                    'english' => $item['english'],
                    'thumb' => '/lesson/media/thumb/' . $item['id'],
                    'audio' => '/lesson/media/audio/' . $item['id'],
                    'source_url' => (string) $row->get('source_url'),
                ];
                ++$total;
            }
            if ($items !== []) {
                $groups[] = ['label' => $group['label'], 'items' => $items];
            }
        }

        $html = $this->twig->render('pages/lesson/lesson1.html.twig', LayoutTwigContext::withAccount($account, [
            'path' => '/lessons/' . $lesson['slug'],
            'lesson_title' => $def['title'],
            'lesson_slug' => $lesson['slug'],
            'groups' => $groups,
            'total' => $total,
            'steven_url' => self::STEVEN_PROFILE_URL,
        ]));

        return new Response($html);
    }

    /** Stream a whiteboard thumbnail or audio clip from the corpus directory. */
    public function media(#[MapRoute] array $params, #[MapQuery] array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $kind = (string) ($params['kind'] ?? '');
        $id = (string) ($params['id'] ?? '');

        // Hard allowlist: only the two known kinds, and only IDs that exactly
        // match a known lesson item. No request-derived value reaches the
        // filesystem path beyond an exact-match ID, so traversal is impossible.
        if (!in_array($kind, ['thumb', 'audio'], true) || !in_array($id, $this->allowedIds(), true)) {
            return new Response('Not found', 404);
        }

        [$subdir, $ext, $contentType] = $kind === 'thumb'
            ? ['thumbs', 'jpg', 'image/jpeg']
            : ['audio', 'opus', 'audio/ogg'];

        $file = $this->corpusPath() . '/' . $subdir . '/' . $id . '.' . $ext;
        if (!is_file($file)) {
            return new Response('Not found', 404);
        }

        return new Response((string) file_get_contents($file), 200, [
            'Content-Type' => $contentType,
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    /** @return array<string, EntityInterface> keyed by source_sentence_id */
    private function corpusRowsBySourceId(): array
    {
        $storage = $this->entityTypeManager->getStorage('example_sentence');
        // Public surface: only published, consent-public corpus rows. Guard the
        // empty set so loadMultiple([]) cannot dump the whole table.
        $ids = $storage->getQuery()->accessCheck(false)
            ->condition('status', 1)
            ->condition('consent_public', 1)
            ->execute();
        if ($ids === []) {
            return [];
        }

        $rows = [];
        foreach ($storage->loadMultiple($ids) as $entity) {
            $rows[(string) $entity->get('source_sentence_id')] = $entity;
        }

        return $rows;
    }

    /**
     * The lesson registry. Each entry maps a stable slug + number to its
     * curation config under config/. Add lessons here.
     *
     * @return list<array{number: int, slug: string, config: string}>
     */
    private function lessons(): array
    {
        return [
            ['number' => 1, 'slug' => 'the-kitchen', 'config' => 'lesson1.php'],
        ];
    }

    /** @return array{number: int, slug: string, config: string}|null */
    private function lessonBySlug(string $slug): ?array
    {
        foreach ($this->lessons() as $lesson) {
            if ($lesson['slug'] === $slug) {
                return $lesson;
            }
        }

        return null;
    }

    /**
     * @param array{title: string, groups: list<array{label: string, items: list<array{id: string, english: string}>}>} $def
     */
    private function lessonItemCount(array $def): int
    {
        $count = 0;
        foreach ($def['groups'] as $group) {
            $count += count($group['items']);
        }

        return $count;
    }

    /**
     * @return array{title: string, groups: list<array{label: string, items: list<array{id: string, english: string}>}>}
     */
    private function lessonDefinition(string $configFile): array
    {
        /** @var array{title: string, groups: list<array{label: string, items: list<array{id: string, english: string}>}>} $def */
        $def = require dirname(__DIR__, 4) . '/config/' . $configFile;

        return $def;
    }

    /** Every lesson item id across all lessons (the media route's allowlist). @return list<string> */
    private function allowedIds(): array
    {
        $ids = [];
        foreach ($this->lessons() as $lesson) {
            foreach ($this->lessonDefinition($lesson['config'])['groups'] as $group) {
                foreach ($group['items'] as $item) {
                    $ids[] = $item['id'];
                }
            }
        }

        return $ids;
    }

    private function corpusPath(): string
    {
        $env = getenv('MINOO_CORPUS_PATH');

        return is_string($env) && $env !== '' ? rtrim($env, '/\\') : self::DEFAULT_CORPUS_PATH;
    }
}
