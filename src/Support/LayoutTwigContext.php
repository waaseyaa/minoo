<?php

declare(strict_types=1);

namespace App\Support;

use Waaseyaa\Access\AccountInterface;
use Waaseyaa\User\Middleware\CsrfMiddleware;

final class LayoutTwigContext
{
    /**
     * Merge layout globals for templates that extend `base.html.twig` (user menu, CSRF meta).
     *
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public static function withAccount(AccountInterface $account, array $context, bool $includeCsrf = true): array
    {
        $merged = array_merge($context, ['account' => $account]);
        if ($includeCsrf) {
            $merged['csrf_token'] = CsrfMiddleware::token();
        }

        return $merged;
    }
}
