<?php

declare(strict_types=1);

namespace App\Support;

use Waaseyaa\User\User;

/**
 * Elder self-identification helpers for the User entity.
 *
 * Elder status is self-declared cultural identity, not an operational role.
 * This lives in Minoo (not Waaseyaa) because it's domain-specific to
 * Indigenous community platforms.
 */
final class ElderIdentity
{
    private const FIELD = 'is_elder';

    public static function isElder(User $user): bool
    {
        return (int) ($user->get(self::FIELD) ?? 0) === 1;
    }

    public static function setElder(User $user, bool $elder): User
    {
        $user->set(self::FIELD, $elder ? 1 : 0);

        return $user;
    }
}
