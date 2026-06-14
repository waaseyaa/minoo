<?php

declare(strict_types=1);

namespace App\Tests\Unit\Http\Controller\Community;

use App\Http\Controller\Community\CommunityController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Waaseyaa\Access\AccountInterface;

#[CoversClass(CommunityController::class)]
final class CommunityControllerTest extends TestCase
{
    private Environment $twig;
    private AccountInterface $account;
    private HttpRequest $request;

    protected function setUp(): void
    {
        $this->twig = new Environment(new ArrayLoader([
            'pages/community/index.html.twig' => '{{ path }}|{% for n in nations %}{{ n.name }};{% endfor %}|{{ map_markers|raw }}',
            'pages/community/show.html.twig' => '{{ path }}|{% if nation %}{{ nation.name }}|{{ nation.chief }}|{{ governance_url }}{% else %}NOT_FOUND{% endif %}',
        ]));
        $this->account = $this->createMock(AccountInterface::class);
        $this->request = HttpRequest::create('/');
    }

    #[Test]
    public function index_lists_the_seven_nations_with_map_markers(): void
    {
        $controller = new CommunityController($this->twig);
        $response = $controller->index([], [], $this->account, $this->request);

        $this->assertSame(200, $response->getStatusCode());
        $body = $response->getContent();
        $this->assertStringContainsString('Sagamok Anishnawbek', $body);
        $this->assertStringContainsString('Atikameksheng Anishnawbek', $body);
        $this->assertStringContainsString('"lat":46.167', $body);
    }

    #[Test]
    public function show_returns_200_with_authoritative_leadership_for_a_known_nation(): void
    {
        $controller = new CommunityController($this->twig);
        $response = $controller->show(['slug' => 'sagamok-anishnawbek'], [], $this->account, $this->request);

        $this->assertSame(200, $response->getStatusCode());
        $body = $response->getContent();
        $this->assertStringContainsString('Angus Toulouse', $body);
        $this->assertStringContainsString('FNGovernance.aspx', $body);
    }

    #[Test]
    public function show_returns_404_for_an_unknown_nation(): void
    {
        $controller = new CommunityController($this->twig);
        $response = $controller->show(['slug' => 'not-a-nation'], [], $this->account, $this->request);

        $this->assertSame(404, $response->getStatusCode());
        $this->assertStringContainsString('NOT_FOUND', $response->getContent());
    }
}
