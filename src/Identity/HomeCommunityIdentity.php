<?php

declare(strict_types=1);

namespace App\Identity;

use Waaseyaa\User\User;

/**
 * Home community self-selection for the User entity (Phase 5).
 *
 * A member may self-select ONE home community. It is consent-first (NULL until
 * the member chooses) and community-level only: it carries no coordinates, so
 * proximity stays dormant. The feed uses it as the same-community affinity
 * vantage. Lives in Minoo (not Waaseyaa) like ElderIdentity, because it is
 * domain-specific to Indigenous community platforms.
 */
final class HomeCommunityIdentity
{
    private const FIELD = 'home_community_id';

    public static function getHomeCommunity(User $user): ?int
    {
        $value = $user->get(self::FIELD);

        return $value !== null && (int) $value > 0 ? (int) $value : null;
    }

    public static function setHomeCommunity(User $user, ?int $communityId): User
    {
        $user->set(self::FIELD, $communityId !== null && $communityId > 0 ? $communityId : null);

        return $user;
    }
}
