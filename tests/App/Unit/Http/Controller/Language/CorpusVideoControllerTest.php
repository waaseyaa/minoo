<?php

declare(strict_types=1);

namespace App\Tests\Unit\Http\Controller\Language;

use App\Http\Controller\Language\CorpusVideoController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Entity\Repository\EntityRepositoryInterface;
use Waaseyaa\Entity\Storage\EntityQueryInterface;

/**
 * Locks the corpus-video consent boundary (#873), mirroring the audio/image
 * gate: a reel is served ONLY for a consent_public + published example_sentence;
 * a traversal-shaped id never reaches the filesystem, and a non-consented id
 * 404s without serving anything.
 */
#[CoversClass(CorpusVideoController::class)]
final class CorpusVideoControllerTest extends TestCase
{
    private AccountInterface $account;

    protected function setUp(): void
    {
        $this->account = $this->createMock(AccountInterface::class);
    }

    #[Test]
    public function rejects_a_traversal_shaped_id_before_touching_storage(): void
    {
        $etm = $this->createMock(EntityTypeManager::class);
        $etm->expects($this->never())->method('getRepository');
        $etm->expects($this->never())->method('hasDefinition');

        $controller = new CorpusVideoController($etm);
        $response = $controller->video(['id' => '../../etc/passwd'], $this->account, HttpRequest::create('/media/corpus/video/x'));

        $this->assertSame(404, $response->getStatusCode());
    }

    #[Test]
    public function non_consented_id_returns_404(): void
    {
        $query = $this->createMock(EntityQueryInterface::class);
        $query->method('setAccount')->willReturnSelf();
        $query->method('condition')->willReturnSelf();
        $query->method('range')->willReturnSelf();
        $query->method('execute')->willReturn([]);

        $repository = $this->createMock(EntityRepositoryInterface::class);
        $repository->method('getQuery')->willReturn($query);

        $etm = $this->createMock(EntityTypeManager::class);
        $etm->method('hasDefinition')->willReturn(true);
        $etm->method('getRepository')->willReturn($repository);

        $controller = new CorpusVideoController($etm);
        $response = $controller->video(['id' => 'sb-999'], $this->account, HttpRequest::create('/media/corpus/video/sb-999'));

        $this->assertSame(404, $response->getStatusCode());
    }
}
