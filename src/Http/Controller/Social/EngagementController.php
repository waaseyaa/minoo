<?php

declare(strict_types=1);

namespace App\Http\Controller\Social;

use App\Http\Controller\Concerns\JsonResponseTrait;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Symfony\Component\HttpFoundation\Response;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Media\UploadHandler;
use Waaseyaa\SSR\Attribute\MapQuery;
use Waaseyaa\SSR\Attribute\MapRoute;

final class EngagementController
{
    use JsonResponseTrait;

    /** @var list<string> Entity types that can be reaction/comment/follow targets */
    private const ALLOWED_TARGET_TYPES = [
        'event', 'community_group', 'community', 'post', 'dictionary_entry',
    ];

    /** @var list<string> The Minoo reaction vocabulary (#812). */
    private const ALLOWED_REACTION_TYPES = ['like', 'interested', 'recommend', 'miigwech', 'connect'];

    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
        private readonly UploadHandler $uploadHandler,
    ) {
    }

    public function react(#[MapRoute] array $params, #[MapQuery] array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $data = $this->jsonBody($request);

        if (!isset($data['reaction_type'], $data['target_type'], $data['target_id'])) {
            return $this->json(['error' => 'Missing required fields: reaction_type, target_type, target_id'], 422);
        }

        if (!$this->isValidTargetType($data['target_type'])) {
            return $this->json(['error' => 'Invalid target_type'], 422);
        }

        $reactionType = trim($data['reaction_type']);

        if (!in_array($reactionType, self::ALLOWED_REACTION_TYPES, true)) {
            return $this->json(['error' => 'Invalid reaction_type. Allowed: ' . implode(', ', self::ALLOWED_REACTION_TYPES)], 422);
        }

        $storage = $this->entityTypeManager->getStorage('reaction');

        try {
            $entity = $storage->create([
                'reaction_type' => $reactionType,
                'user_id' => $account->id(),
                'target_type' => $data['target_type'],
                'target_id' => (int) $data['target_id'],
            ]);
            $storage->save($entity);
        } catch (\InvalidArgumentException) {
            return $this->json(['error' => 'Invalid entity data'], 422);
        }

        return $this->json(['id' => $entity->id(), 'reaction_type' => $entity->get('reaction_type')], 201);
    }

    public function deleteReaction(#[MapRoute] array $params, #[MapQuery] array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $id = (int) ($params['id'] ?? 0);
        $storage = $this->entityTypeManager->getStorage('reaction');
        $entity = $storage->load($id);

        if ($entity === null) {
            return $this->json(['error' => 'Not found'], 404);
        }

        if ((int) $entity->get('user_id') !== (int) $account->id() && !$account->hasPermission('administer content')) {
            return $this->json(['error' => 'Forbidden'], 403);
        }

        $storage->delete([$entity]);

        return $this->json(['deleted' => true]);
    }

    public function comment(#[MapRoute] array $params, #[MapQuery] array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $data = $this->jsonBody($request);

        if (!isset($data['body'], $data['target_type'], $data['target_id'])) {
            return $this->json(['error' => 'Missing required fields: body, target_type, target_id'], 422);
        }

        if (!$this->isValidTargetType($data['target_type'])) {
            return $this->json(['error' => 'Invalid target_type'], 422);
        }

        $body = trim($data['body']);
        if ($body === '' || mb_strlen($body) > 2000) {
            return $this->json(['error' => 'Body must be 1-2000 characters'], 422);
        }

        $storage = $this->entityTypeManager->getStorage('comment');

        try {
            $entity = $storage->create([
                'body' => $body,
                'user_id' => $account->id(),
                'target_type' => $data['target_type'],
                'target_id' => (int) $data['target_id'],
            ]);
            $storage->save($entity);
        } catch (\InvalidArgumentException) {
            return $this->json(['error' => 'Invalid entity data'], 422);
        }

        return $this->json([
            'id' => $entity->id(),
            'body' => $entity->get('body'),
            'created_at' => $entity->get('created_at'),
        ], 201);
    }

    public function deleteComment(#[MapRoute] array $params, #[MapQuery] array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $id = (int) ($params['id'] ?? 0);
        $storage = $this->entityTypeManager->getStorage('comment');
        $entity = $storage->load($id);

        if ($entity === null) {
            return $this->json(['error' => 'Not found'], 404);
        }

        if ((int) $entity->get('user_id') !== (int) $account->id() && !$account->hasPermission('administer content')) {
            return $this->json(['error' => 'Forbidden'], 403);
        }

        $storage->delete([$entity]);

        return $this->json(['deleted' => true]);
    }

    public function getComments(#[MapRoute] array $params, #[MapQuery] array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $targetType = $params['target_type'] ?? '';
        if (!$this->isValidTargetType($targetType)) {
            return $this->json(['error' => 'Invalid target_type'], 422);
        }

        $targetId = (int) ($params['target_id'] ?? 0);
        $limit = min((int) ($query['limit'] ?? 20), 50);
        $offset = max((int) ($query['offset'] ?? 0), 0);

        $storage = $this->entityTypeManager->getStorage('comment');
        $ids = $storage->getQuery()->setAccount($account)
            ->condition('target_type', $targetType)
            ->condition('target_id', $targetId)
            ->condition('status', 1)
            ->sort('created_at', 'DESC')
            ->range($offset, $limit)
            ->execute();

        $comments = $ids !== [] ? array_values($storage->loadMultiple($ids)) : [];

        $items = array_map(fn ($c) => [
            'id' => $c->id(),
            'body' => $c->get('body'),
            'user_id' => $c->get('user_id'),
            'created_at' => $c->get('created_at'),
        ], $comments);

        return $this->json(['comments' => $items]);
    }

    // Following is DEFERRED — no follow()/deleteFollow() surface. The follow
    // table + the waaseyaa/engagement package stay; re-add the methods + routes
    // when following is approved.

    public function createPost(#[MapRoute] array $params, #[MapQuery] array $query, AccountInterface $account, HttpRequest $request): Response
    {
        // Support both JSON and multipart form data
        // Try JSON first (existing API), fall back to form data (multipart with images)
        $data = $this->jsonBody($request);
        if (isset($data['body'])) {
            $body = trim($data['body']);
            $communityId = (int) ($data['community_id'] ?? 0);
        } else {
            $body = trim((string) $request->request->get('body', ''));
            $communityId = (int) $request->request->get('community_id', 0);
        }

        if ($communityId === 0) {
            return $this->json(['error' => 'Missing required fields: body, community_id'], 422);
        }

        if ($body === '' || mb_strlen($body) > 5000) {
            return $this->json(['error' => 'Body must be 1-5000 characters'], 422);
        }

        $storage = $this->entityTypeManager->getStorage('post');

        // Resolve author display name for feed attribution
        $authorName = '';
        if ($account instanceof \Waaseyaa\User\User) {
            $authorName = $account->getName();
        }

        try {
            $entity = $storage->create([
                'body' => $body,
                'user_id' => $account->id(),
                'community_id' => $communityId,
                'author_name' => $authorName,
            ]);
            $storage->save($entity);
        } catch (\InvalidArgumentException) {
            return $this->json(['error' => 'Invalid entity data'], 422);
        }

        // Handle image uploads
        $uploadedFiles = $this->extractUploadedImages($request);
        if ($uploadedFiles !== []) {
            $imagePaths = [];
            foreach ($uploadedFiles as $file) {
                $fileArray = [
                    'name' => $file->getClientOriginalName(),
                    'tmp_name' => $file->getPathname(),
                    'size' => $file->getSize(),
                    // Client-declared type only; UploadHandler::validate() re-checks
                    // the REAL mime via finfo on the tmp file, rejecting spoofs.
                    'type' => (string) $file->getClientMimeType(),
                    'error' => $file->getError(),
                ];
                if ($this->uploadHandler->validate($fileArray) === []) {
                    $imagePaths[] = $this->uploadHandler->moveUpload($fileArray, 'posts/' . $entity->id());
                }
            }
            if ($imagePaths !== []) {
                $entity->set('images', json_encode($imagePaths));
                $storage->save($entity);
            }
        }

        return $this->json([
            'id' => $entity->id(),
            'body' => $entity->get('body'),
            'created_at' => $entity->get('created_at'),
        ], 201);
    }

    /** @return list<UploadedFile> */
    private function extractUploadedImages(HttpRequest $request): array
    {
        $candidates = [];
        foreach (['images', 'images[]'] as $key) {
            try {
                $all = $request->files->all($key);
                if ($all !== []) {
                    $candidates[] = $all;
                }
            } catch (\Throwable) {
                // Some payload shapes throw when all() expects array but gets a single file.
            }

            $single = $request->files->get($key);
            if ($single !== null) {
                $candidates[] = $single;
            }
        }

        $files = [];
        $flatten = function (mixed $value) use (&$files, &$flatten): void {
            if ($value instanceof UploadedFile) {
                $files[] = $value;

                return;
            }

            if (is_array($value)) {
                foreach ($value as $item) {
                    $flatten($item);
                }
            }
        };

        foreach ($candidates as $candidate) {
            $flatten($candidate);
        }

        return array_slice($files, 0, 4);
    }

    public function deletePost(#[MapRoute] array $params, #[MapQuery] array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $id = (int) ($params['id'] ?? 0);
        $storage = $this->entityTypeManager->getStorage('post');
        $entity = $storage->load($id);

        if ($entity === null) {
            return $this->json(['error' => 'Not found'], 404);
        }

        if ((int) $entity->get('user_id') !== (int) $account->id() && !$account->hasPermission('administer content')) {
            return $this->json(['error' => 'Forbidden'], 403);
        }

        $storage->delete([$entity]);
        $this->uploadHandler->deleteDirectory('posts/' . $id);

        return $this->json(['deleted' => true]);
    }

    private function isValidTargetType(string $type): bool
    {
        return in_array($type, self::ALLOWED_TARGET_TYPES, true);
    }

}
