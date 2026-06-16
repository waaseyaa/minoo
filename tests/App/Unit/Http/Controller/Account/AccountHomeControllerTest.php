<?php

declare(strict_types=1);

namespace App\Tests\Unit\Http\Controller\Account;

use App\Entity\Community\Community;
use App\Http\Controller\Account\AccountHomeController;
use App\Identity\ElderIdentity;
use App\Identity\HomeCommunityIdentity;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Twig\Environment;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Entity\Storage\EntityStorageInterface;
use Waaseyaa\User\User;

#[CoversClass(AccountHomeController::class)]
final class AccountHomeControllerTest extends TestCase
{
    private function createController(?Environment $twig = null, ?EntityTypeManager $etm = null): AccountHomeController
    {
        return new AccountHomeController(
            $twig ?? $this->createMock(Environment::class),
            $etm ?? $this->createMock(EntityTypeManager::class),
        );
    }

    #[Test]
    public function indexRendersAccountHomePage(): void
    {
        $account = $this->createMock(AccountInterface::class);
        $account->method('id')->willReturn(1);
        $account->method('getRoles')->willReturn([]);
        $account->method('isAuthenticated')->willReturn(true);

        $twig = $this->createMock(Environment::class);
        $twig->expects($this->once())
            ->method('render')
            ->with('pages/account/index.html.twig', $this->callback(function (array $vars) {
                return $vars['account'] instanceof AccountInterface
                    && $vars['roles'] === []
                    && $vars['path'] === '/account';
            }))
            ->willReturn('<html>account home</html>');

        $controller = $this->createController($twig);
        $response = $controller->index([], [], $account, new HttpRequest());

        $this->assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function indexPassesRolesToTemplate(): void
    {
        $account = $this->createMock(AccountInterface::class);
        $account->method('id')->willReturn(2);
        $account->method('getRoles')->willReturn(['volunteer']);
        $account->method('isAuthenticated')->willReturn(true);

        $twig = $this->createMock(Environment::class);
        $twig->expects($this->once())
            ->method('render')
            ->with('pages/account/index.html.twig', $this->callback(function (array $vars) {
                return $vars['roles'] === ['volunteer'];
            }))
            ->willReturn('<html>account home</html>');

        $controller = $this->createController($twig);
        $response = $controller->index([], [], $account, new HttpRequest());

        $this->assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function index_passes_is_elder_false_for_non_elder(): void
    {
        $twig = $this->createMock(Environment::class);

        $capturedContext = null;
        $twig->expects($this->once())
            ->method('render')
            ->with('pages/account/index.html.twig', $this->callback(function (array $ctx) use (&$capturedContext) {
                $capturedContext = $ctx;

                return true;
            }))
            ->willReturn('<html></html>');

        $controller = $this->createController($twig);
        $account = new User(['uid' => 1, 'name' => 'Test']);

        $controller->index([], [], $account, HttpRequest::create('/account'));

        $this->assertArrayHasKey('is_elder', $capturedContext);
        $this->assertFalse($capturedContext['is_elder']);
    }

    #[Test]
    public function index_passes_is_elder_true_for_elder(): void
    {
        $twig = $this->createMock(Environment::class);

        $capturedContext = null;
        $twig->expects($this->once())
            ->method('render')
            ->with('pages/account/index.html.twig', $this->callback(function (array $ctx) use (&$capturedContext) {
                $capturedContext = $ctx;

                return true;
            }))
            ->willReturn('<html></html>');

        $controller = $this->createController($twig);
        $account = new User(['uid' => 1, 'name' => 'Test', 'is_elder' => 1]);

        $controller->index([], [], $account, HttpRequest::create('/account'));

        $this->assertTrue($capturedContext['is_elder']);
    }

    #[Test]
    public function toggle_elder_sets_elder_and_redirects(): void
    {
        $etm = $this->createMock(EntityTypeManager::class);
        $storage = $this->createMock(EntityStorageInterface::class);

        $user = new User(['uid' => 1, 'name' => 'Test']);
        $this->assertFalse(ElderIdentity::isElder($user));

        $etm->method('getStorage')->with('user')->willReturn($storage);
        $storage->method('load')->with(1)->willReturn($user);
        $storage->expects($this->once())->method('save')->with($user);

        $controller = $this->createController(etm: $etm);
        $account = new User(['uid' => 1, 'name' => 'Test']);

        $_SESSION = [];
        $response = $controller->toggleElder([], [], $account, HttpRequest::create('/account/elder-toggle', 'POST'));

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/account', $response->headers->get('Location'));
        $this->assertTrue(ElderIdentity::isElder($user));
    }

    #[Test]
    public function toggle_elder_unsets_elder_when_already_elder(): void
    {
        $etm = $this->createMock(EntityTypeManager::class);
        $storage = $this->createMock(EntityStorageInterface::class);

        $user = new User(['uid' => 1, 'name' => 'Test', 'is_elder' => 1]);
        $this->assertTrue(ElderIdentity::isElder($user));

        $etm->method('getStorage')->with('user')->willReturn($storage);
        $storage->method('load')->with(1)->willReturn($user);
        $storage->expects($this->once())->method('save')->with($user);

        $controller = $this->createController(etm: $etm);
        $account = new User(['uid' => 1, 'name' => 'Test', 'is_elder' => 1]);

        $_SESSION = [];
        $response = $controller->toggleElder([], [], $account, HttpRequest::create('/account/elder-toggle', 'POST'));

        $this->assertSame(302, $response->getStatusCode());
        $this->assertFalse(ElderIdentity::isElder($user));
    }

    #[Test]
    public function select_home_community_saves_a_valid_published_community(): void
    {
        $userStorage = $this->createMock(EntityStorageInterface::class);
        $user = new User(['uid' => 1, 'name' => 'Test']);
        $userStorage->method('load')->with(1)->willReturn($user);
        $userStorage->expects($this->once())->method('save')->with($user);

        $communityStorage = $this->createMock(EntityStorageInterface::class);
        $communityStorage->method('load')->with(10)->willReturn(new Community(['cid' => 10, 'name' => 'Sagamok', 'status' => 1]));

        $etm = $this->createMock(EntityTypeManager::class);
        $etm->method('hasDefinition')->willReturn(true);
        $etm->method('getStorage')->willReturnCallback(
            fn (string $type): EntityStorageInterface => $type === 'community' ? $communityStorage : $userStorage,
        );

        $controller = $this->createController(etm: $etm);
        $request = HttpRequest::create('/account/home-community', 'POST', ['home_community_id' => '10']);

        $_SESSION = [];
        $response = $controller->selectHomeCommunity([], [], new User(['uid' => 1]), $request);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/account', $response->headers->get('Location'));
        $this->assertSame(10, HomeCommunityIdentity::getHomeCommunity($user));
    }

    #[Test]
    public function select_home_community_clears_on_empty_value(): void
    {
        $userStorage = $this->createMock(EntityStorageInterface::class);
        $user = new User(['uid' => 1, 'home_community_id' => 10]);
        $userStorage->method('load')->with(1)->willReturn($user);
        $userStorage->expects($this->once())->method('save')->with($user);

        $etm = $this->createMock(EntityTypeManager::class);
        $etm->method('getStorage')->willReturn($userStorage);

        $controller = $this->createController(etm: $etm);
        $request = HttpRequest::create('/account/home-community', 'POST', ['home_community_id' => '']);

        $_SESSION = [];
        $response = $controller->selectHomeCommunity([], [], new User(['uid' => 1]), $request);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertNull(HomeCommunityIdentity::getHomeCommunity($user));
    }

    #[Test]
    public function select_home_community_rejects_an_unpublished_community(): void
    {
        $userStorage = $this->createMock(EntityStorageInterface::class);
        $user = new User(['uid' => 1, 'name' => 'Test']);
        $userStorage->method('load')->with(1)->willReturn($user);
        $userStorage->expects($this->once())->method('save')->with($user);

        $communityStorage = $this->createMock(EntityStorageInterface::class);
        // status 0 -> not published -> rejected and cleared.
        $communityStorage->method('load')->with(10)->willReturn(new Community(['cid' => 10, 'name' => 'Draft', 'status' => 0]));

        $etm = $this->createMock(EntityTypeManager::class);
        $etm->method('hasDefinition')->willReturn(true);
        $etm->method('getStorage')->willReturnCallback(
            fn (string $type): EntityStorageInterface => $type === 'community' ? $communityStorage : $userStorage,
        );

        $controller = $this->createController(etm: $etm);
        $request = HttpRequest::create('/account/home-community', 'POST', ['home_community_id' => '10']);

        $_SESSION = [];
        $controller->selectHomeCommunity([], [], new User(['uid' => 1]), $request);

        $this->assertNull(HomeCommunityIdentity::getHomeCommunity($user));
    }
}
