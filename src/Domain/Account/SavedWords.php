<?php

declare(strict_types=1);

namespace App\Domain\Account;

use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\EntityTypeManager;

/**
 * Helpers for a member's personal word list (#806). Centralises the
 * saved_word queries so the toggle endpoint, the "My words" page, and the
 * dictionary entry page stay consistent. (The old loadMultiple([]) "load
 * all" footgun is gone: EntityRepositoryInterface::findMany([]) is
 * fail-closed and returns [].)
 */
final class SavedWords
{
    /** The saved_word id for (user, entry), or null if not saved. */
    public static function savedId(EntityTypeManager $etm, AccountInterface $account, int $entryId): ?int
    {
        if (!$account->isAuthenticated() || $entryId <= 0) {
            return null;
        }

        $ids = $etm->getRepository('saved_word')->getQuery()->setAccount($account)
            ->condition('user_id', (int) $account->id())
            ->condition('dictionary_entry_id', $entryId)
            ->range(0, 1)
            ->execute();

        return $ids === [] ? null : (int) reset($ids);
    }

    public static function isSaved(EntityTypeManager $etm, AccountInterface $account, int $entryId): bool
    {
        return self::savedId($etm, $account, $entryId) !== null;
    }

    /** How many words this member has saved. */
    public static function countFor(EntityTypeManager $etm, AccountInterface $account): int
    {
        if (!$account->isAuthenticated()) {
            return 0;
        }

        // count()->execute() returns a single-element [N] array (framework gotcha).
        $result = $etm->getRepository('saved_word')->getQuery()->setAccount($account)
            ->condition('user_id', (int) $account->id())
            ->count()
            ->execute();

        return (int) (reset($result) ?: 0);
    }

    /**
     * A member's saved words, newest first.
     *
     * @return list<array{word: string, slug: string, definition: string, created_at: int}>
     */
    public static function listFor(EntityTypeManager $etm, AccountInterface $account): array
    {
        if (!$account->isAuthenticated()) {
            return [];
        }

        $repository = $etm->getRepository('saved_word');
        $ids = $repository->getQuery()->setAccount($account)
            ->condition('user_id', (int) $account->id())
            ->sort('created_at', 'DESC')
            ->range(0, 500)
            ->execute();

        if ($ids === []) {
            return [];
        }

        $out = [];
        foreach ($repository->findMany($ids) as $row) {
            $out[] = [
                'word' => (string) $row->get('word'),
                'slug' => (string) $row->get('slug'),
                'definition' => (string) $row->get('definition'),
                'created_at' => (int) $row->get('created_at'),
            ];
        }

        return $out;
    }
}
