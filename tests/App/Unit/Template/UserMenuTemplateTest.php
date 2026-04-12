<?php

declare(strict_types=1);

namespace App\Tests\Unit\Template;

use App\Twig\AccountDisplayTwigExtension;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;
use Waaseyaa\User\User;

#[CoversNothing]
final class UserMenuTemplateTest extends TestCase
{
    private Environment $twig;

    protected function setUp(): void
    {
        $root = dirname(__DIR__, 4) . '/templates';
        $this->twig = new Environment(new FilesystemLoader($root));
        $this->twig->addExtension(new AccountDisplayTwigExtension());
        $this->twig->addFunction(new TwigFunction('trans', static fn (string $key): string => $key));
        $this->twig->addFunction(new TwigFunction('lang_url', static fn (string $path): string => $path));
    }

    #[Test]
    public function authenticated_menu_includes_display_name(): void
    {
        $account = new User([
            'uid' => 2,
            'name' => 'Nookomis',
            'mail' => 'nookomis@example.test',
        ]);

        $html = $this->twig->render('components/user-menu.html.twig', ['account' => $account]);

        $this->assertStringContainsString('user-menu__trigger', $html);
        $this->assertStringContainsString('N', $html);
        $this->assertStringContainsString('Nookomis', $html);
        $this->assertStringContainsString('nookomis@example.test', $html);
        $this->assertStringNotContainsString('usermenu.log_in', $html);
    }

    #[Test]
    public function guest_menu_shows_log_in(): void
    {
        $account = new User(['uid' => 0]);

        $html = $this->twig->render('components/user-menu.html.twig', ['account' => $account]);

        $this->assertStringContainsString('usermenu.log_in', $html);
        $this->assertStringNotContainsString('user-menu__dropdown', $html);
    }
}
