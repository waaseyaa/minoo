<?php

declare(strict_types=1);

namespace App\Tests\Unit\Http\Controller\Language;

use App\Http\Controller\Language\LanguageController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\EntityTypeManager;

#[CoversClass(LanguageController::class)]
final class LanguageControllerSearchTest extends TestCase
{
    private EntityTypeManager $entityTypeManager;
    private Environment $twig;
    private AccountInterface $account;
    private HttpRequest $request;

    protected function setUp(): void
    {
        $this->entityTypeManager = $this->createMock(EntityTypeManager::class);

        $this->twig = new Environment(new ArrayLoader([
            'pages/language/search.html.twig' => '{{ path }}|{{ search_query|default("") }}|{{ search_total|default(0) }}{% for r in search_results|default([]) %}|{{ r.word|default("") }}{% endfor %}',
        ]));

        $this->account = $this->createMock(AccountInterface::class);
        $this->request = HttpRequest::create('/language/search');
    }

    #[Test]
    public function search_with_empty_query_returns_200_with_empty_results(): void
    {
        // No storage lookup happens for an empty query.
        $this->entityTypeManager->expects($this->never())->method('getStorage');

        $controller = new LanguageController($this->entityTypeManager, $this->twig);
        $response = $controller->search([], [], $this->account, $this->request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('/language/search', $response->getContent());
        $this->assertStringContainsString('|0', $response->getContent());
    }
}
