<?php

declare(strict_types=1);

namespace App\Http\View;

use Waaseyaa\Access\AccountInterface;
use Waaseyaa\User\User;

/**
 * Shared Twig context for pages rendered into the Anokii shell (#851, #853).
 *
 * Centralises the sidebar nav and the user-chip fields so every workspace tab
 * (Overview, Transcribe, …) renders identical chrome. Tabs pass their own
 * `nav_active` id and merge page-specific keys via $extra.
 */
final class AnokiiShellContext
{
    /**
     * @param array<string, mixed> $extra
     *
     * @return array<string, mixed>
     */
    public static function build(AccountInterface $account, string $active, array $extra = []): array
    {
        $label = self::accountLabel($account);

        return $extra + [
            'nav' => self::nav(),
            'nav_active' => $active,
            'home_path' => '/admin/anokii',
            'logout_path' => '/logout',
            'user_label' => $label,
            'user_role' => self::accountRole($account),
            'user_initials' => self::initials($label),
        ];
    }

    /**
     * Sidebar nav. Transcribe (#853) and Curate (#855) are live; Ingest (#854)
     * is a CLI command (bin/waaseyaa ingest:corpus), so it has no tab.
     *
     * @return list<array{id: string, label: string, href: string, group?: string, badge?: string}>
     */
    private static function nav(): array
    {
        return [
            ['id' => 'home', 'label' => 'Overview', 'href' => '/admin/anokii', 'group' => 'Workspace'],
            ['id' => 'transcribe', 'label' => 'Transcribe', 'href' => '/admin/anokii/transcribe', 'group' => 'Language'],
            ['id' => 'curate', 'label' => 'Curate', 'href' => '/admin/anokii/curate'],
        ];
    }

    private static function accountLabel(AccountInterface $account): string
    {
        if ($account instanceof User) {
            $name = trim($account->getName());
            if ($name !== '') {
                return $name;
            }
            $email = trim($account->getEmail());
            if ($email !== '') {
                return $email;
            }
        }

        return 'Staff';
    }

    private static function accountRole(AccountInterface $account): string
    {
        $roles = $account->getRoles();

        return $roles !== [] ? (string) reset($roles) : 'staff';
    }

    private static function initials(string $label): string
    {
        $parts = preg_split('/[\s@._-]+/', $label, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $initials = '';
        foreach ($parts as $part) {
            $initials .= strtoupper(substr($part, 0, 1));
            if (strlen($initials) === 2) {
                break;
            }
        }

        return $initials !== '' ? $initials : '?';
    }
}
