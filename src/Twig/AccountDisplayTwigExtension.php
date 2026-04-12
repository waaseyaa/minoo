<?php

declare(strict_types=1);

namespace App\Twig;

use App\Support\AccountDisplay;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;
use Waaseyaa\Access\AccountInterface;

final class AccountDisplayTwigExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('account_display_initial', $this->initial(...)),
            new TwigFunction('account_display_name', $this->name(...)),
            new TwigFunction('account_display_email', $this->email(...)),
        ];
    }

    public function initial(AccountInterface $account): string
    {
        return AccountDisplay::initial($account);
    }

    public function name(AccountInterface $account): string
    {
        return AccountDisplay::name($account);
    }

    public function email(AccountInterface $account): string
    {
        return AccountDisplay::email($account);
    }
}
