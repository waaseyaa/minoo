<?php

declare(strict_types=1);

namespace App\Controller;

use App\Support\LayoutTwigContext;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Twig\Environment;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\EntityTypeManager;
use Symfony\Component\HttpFoundation\Response;

final class OralHistoryController
{
    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
        private readonly Environment $twig,
    ) {}

    /** @param array<string, mixed> $params */
    /** @param array<string, mixed> $query */
    public function list(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $collectionStorage = $this->entityTypeManager->getStorage('oral_history_collection');
        $collectionIds = $collectionStorage->getQuery()
            ->condition('status', 1)
            ->sort('title', 'ASC')
            ->execute();
        $collections = $collectionIds !== [] ? array_values($collectionStorage->loadMultiple($collectionIds)) : [];

        $storyStorage = $this->entityTypeManager->getStorage('oral_history');
        $storyIds = $storyStorage->getQuery()
            ->condition('status', 1)
            ->condition('consent_public', 1)
            ->sort('created_at', 'DESC')
            ->execute();
        $stories = $storyIds !== [] ? array_values($storyStorage->loadMultiple($storyIds)) : [];

        $html = $this->twig->render('pages/oral-histories/index.html.twig', LayoutTwigContext::withAccount($account, [
            'path' => '/oral-histories',
            'collections' => $collections,
            'stories' => $stories,
        ]));

        return new Response($html);
    }

    /** @param array<string, mixed> $params */
    /** @param array<string, mixed> $query */
    public function collection(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $slug = $params['slug'] ?? '';
        $collectionStorage = $this->entityTypeManager->getStorage('oral_history_collection');
        $collectionIds = $collectionStorage->getQuery()
            ->condition('slug', $slug)
            ->condition('status', 1)
            ->execute();
        $collection = $collectionIds !== [] ? $collectionStorage->load(reset($collectionIds)) : null;

        $stories = [];
        $curator = null;

        if ($collection !== null) {
            $storyStorage = $this->entityTypeManager->getStorage('oral_history');
            $storyIds = $storyStorage->getQuery()
                ->condition('collection_id', $collection->id())
                ->condition('status', 1)
                ->condition('consent_public', 1)
                ->sort('story_order', 'ASC')
                ->execute();
            $stories = $storyIds !== [] ? array_values($storyStorage->loadMultiple($storyIds)) : [];

            $curatorId = $collection->get('curator_id');
            if ($curatorId !== null && $curatorId !== '') {
                $contributorStorage = $this->entityTypeManager->getStorage('contributor');
                $curator = $contributorStorage->load($curatorId);
            }
        }

        $html = $this->twig->render('pages/oral-histories/collection.html.twig', LayoutTwigContext::withAccount($account, [
            'path' => '/oral-histories/collections/' . $slug,
            'collection' => $collection,
            'stories' => $stories,
            'curator' => $curator,
        ]));

        return new Response($html, $collection !== null ? 200 : 404);
    }

    /** @param array<string, mixed> $params */
    /** @param array<string, mixed> $query */
    public function show(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $slug = $params['slug'] ?? '';
        $storyStorage = $this->entityTypeManager->getStorage('oral_history');
        $storyIds = $storyStorage->getQuery()
            ->condition('slug', $slug)
            ->condition('status', 1)
            ->condition('consent_public', 1)
            ->execute();
        $story = $storyIds !== [] ? $storyStorage->load(reset($storyIds)) : null;

        $contributor = null;
        $collection = null;
        $prevStory = null;
        $nextStory = null;

        if ($story !== null) {
            $contributorId = $story->get('contributor_id');
            if ($contributorId !== null && $contributorId !== '') {
                $contributorStorage = $this->entityTypeManager->getStorage('contributor');
                $contributor = $contributorStorage->load($contributorId);
            }

            $collectionId = $story->get('collection_id');
            if ($collectionId !== null && $collectionId !== '') {
                $collectionStorage = $this->entityTypeManager->getStorage('oral_history_collection');
                $collection = $collectionStorage->load($collectionId);

                if ($collection !== null) {
                    $siblingIds = $storyStorage->getQuery()
                        ->condition('collection_id', $collectionId)
                        ->condition('status', 1)
                        ->condition('consent_public', 1)
                        ->sort('story_order', 'ASC')
                        ->execute();
                    $siblings = $siblingIds !== [] ? array_values($storyStorage->loadMultiple($siblingIds)) : [];

                    $currentIndex = null;
                    foreach ($siblings as $i => $sibling) {
                        if ($sibling->id() === $story->id()) {
                            $currentIndex = $i;
                            break;
                        }
                    }

                    if ($currentIndex !== null) {
                        $prevStory = $currentIndex > 0 ? $siblings[$currentIndex - 1] : null;
                        $nextStory = $currentIndex < count($siblings) - 1 ? $siblings[$currentIndex + 1] : null;
                    }
                }
            }
        }

        $html = $this->twig->render('pages/oral-histories/show.html.twig', LayoutTwigContext::withAccount($account, [
            'path' => '/oral-histories/' . $slug,
            'story' => $story,
            'contributor' => $contributor,
            'collection' => $collection,
            'prev_story' => $prevStory,
            'next_story' => $nextStory,
        ]));

        return new Response($html, $story !== null ? 200 : 404);
    }
}
