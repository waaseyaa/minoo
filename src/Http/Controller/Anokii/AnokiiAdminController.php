<?php

declare(strict_types=1);

namespace App\Http\Controller\Anokii;

use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\SSR\Attribute\MapQuery;
use Waaseyaa\SSR\Attribute\MapRoute;
use Waaseyaa\User\User;

/**
 * Anokii admin shell landing (#851).
 *
 * Renders the empty, themed Anokii workspace chrome at /admin/anokii. The route
 * is role-gated (admin / elder_coordinator) by {@see \App\Provider\Routing\AnokiiRouteProvider},
 * so by the time this runs the account is an authorised staff member.
 *
 * No tools are wired yet — the nav advertises the planned tabs (Transcribe,
 * Ingest, Curate) as "coming soon". The page extends the Anokii package shell
 * via the `@anokii` namespace; it never forks it.
 */
final class AnokiiAdminController
{
    public function __construct(
        private readonly Environment $twig,
    ) {
    }

    public function index(#[MapRoute] array $params, #[MapQuery] array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $active = (string) ($params['sub'] ?? 'home');

        $html = $this->twig->render('pages/anokii/index.html.twig', [
            'path' => $request->getPathInfo(),
            'nav' => $this->nav(),
            'nav_active' => $active,
            'home_path' => '/admin/anokii',
            'logout_path' => '/logout',
            'user_label' => $this->accountLabel($account),
            'user_role' => $this->accountRole($account),
            'user_initials' => $this->initials($this->accountLabel($account)),
        ]);

        return new Response($html);
    }

    /**
     * Sidebar nav. The workspace tabs are placeholders until their issues land
     * (#853 transcribe, #854 ingest, #855 curate); each links nowhere yet and is
     * flagged "Soon" so the chrome reads as intentionally empty.
     *
     * @return list<array{id: string, label: string, href: string, group?: string, badge?: string}>
     */
    private function nav(): array
    {
        return [
            ['id' => 'home', 'label' => 'Overview', 'href' => '/admin/anokii', 'group' => 'Workspace'],
            ['id' => 'transcribe', 'label' => 'Transcribe', 'href' => '#', 'group' => 'Language', 'badge' => 'Soon'],
            ['id' => 'ingest', 'label' => 'Ingest', 'href' => '#', 'badge' => 'Soon'],
            ['id' => 'curate', 'label' => 'Curate', 'href' => '#', 'badge' => 'Soon'],
        ];
    }

    private function accountLabel(AccountInterface $account): string
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

    private function accountRole(AccountInterface $account): string
    {
        $roles = $account->getRoles();

        return $roles !== [] ? (string) reset($roles) : 'staff';
    }

    private function initials(string $label): string
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
