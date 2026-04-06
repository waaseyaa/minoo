<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Controller;

use Minoo\Controller\LanguageController;
use Minoo\Support\NorthCloudClient;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\EntityTypeManager;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

#[CoversClass(LanguageController::class)]
final class LanguageControllerSearchTest extends TestCase
{
    private EntityTypeManager $entityTypeManager;
    private Environment $twig;
    private NorthCloudClient $northCloudClient;
    private AccountInterface $account;
    private HttpRequest $request;

    protected function setUp(): void
    {
        $this->entityTypeManager = $this->createMock(EntityTypeManager::class);

        $this->twig = new Environment(new ArrayLoader([
            'language.html.twig' => '{{ path }}|{{ search_query|default("") }}|{{ search_total|default(0) }}{% for r in search_results|default([]) %}|{{ r.lemma|default(r.word|default("")) }}{% endfor %}',
        ]));

        $this->northCloudClient = $this->createMock(NorthCloudClient::class);
        $this->account = $this->createMock(AccountInterface::class);
        $this->request = HttpRequest::create('/language/search');
    }

    #[Test]
    public function search_with_empty_query_returns_200_with_empty_results(): void
    {
        $this->northCloudClient->expects($this->never())->method('searchDictionary');

        $controller = new LanguageController($this->entityTypeManager, $this->twig, $this->northCloudClient);
        $response = $controller->search([], [], $this->account, $this->request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('/language/search', $response->getContent());
        $this->assertStringContainsString('|0', $response->getContent());
    }

    #[Test]
    public function search_with_query_calls_north_cloud_and_returns_results(): void
    {
        $this->northCloudClient->expects($this->once())
            ->method('searchDictionary')
            ->with('makwa')
            ->willReturn([
                'entries' => [
                    ['lemma' => 'makwa', 'word_class_normalized' => 'na', 'definitions' => ['bear'], 'slug' => 'makwa'],
                ],
                'total' => 1,
                'attribution' => NorthCloudClient::DICTIONARY_ATTRIBUTION,
            ]);

        $controller = new LanguageController($this->entityTypeManager, $this->twig, $this->northCloudClient);
        $response = $controller->search([], ['q' => 'makwa'], $this->account, $this->request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('makwa', $response->getContent());
        $this->assertStringContainsString('|1', $response->getContent());
    }

    #[Test]
    public function search_with_whitespace_query_returns_empty_results(): void
    {
        $this->northCloudClient->expects($this->never())->method('searchDictionary');

        $controller = new LanguageController($this->entityTypeManager, $this->twig, $this->northCloudClient);
        $response = $controller->search([], ['q' => '   '], $this->account, $this->request);

        $this->assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function search_handles_null_api_response_gracefully(): void
    {
        $this->northCloudClient->expects($this->once())
            ->method('searchDictionary')
            ->with('zzz')
            ->willReturn(null);

        $controller = new LanguageController($this->entityTypeManager, $this->twig, $this->northCloudClient);
        $response = $controller->search([], ['q' => 'zzz'], $this->account, $this->request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('|0', $response->getContent());
    }
}
