<?php

declare(strict_types=1);

namespace Minoo\Support;

use Waaseyaa\Access\AccountInterface;
use Waaseyaa\User\User;

final class AccountDisplay
{
    public static function initial(AccountInterface $account): string
    {
        if (!$account->isAuthenticated()) {
            return '';
        }

        if ($account instanceof User) {
            $name = $account->getName();
            if ($name !== '') {
                return mb_strtoupper(mb_substr($name, 0, 1));
            }
        }

        if (method_exists($account, 'label')) {
            $label = (string) $account->label();
            if ($label !== '') {
                return mb_strtoupper(mb_substr($label, 0, 1));
            }
        }

        return '?';
    }

    public static function name(AccountInterface $account): string
    {
        if (!$account->isAuthenticated()) {
            return '';
        }

        if ($account instanceof User) {
            return $account->getName();
        }

        if (method_exists($account, 'label')) {
            return (string) $account->label();
        }

        return '';
    }

    public static function email(AccountInterface $account): string
    {
        if (!$account->isAuthenticated()) {
            return '';
        }

        if ($account instanceof User) {
            return $account->getEmail();
        }

        return '';
    }
}
