<?php

declare(strict_types=1);

namespace App\Tests\Unit\Http\Controller\Language;

use App\Http\Controller\Language\CorpusAudioController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Entity\Repository\EntityRepositoryInterface;
use Waaseyaa\Entity\Storage\EntityQueryInterface;

/**
 * Locks the corpus-audio consent boundary (Phase 4). Audio is served ONLY for a
 * consent_public + published example_sentence; a bad id never reaches the
 * filesystem, and a non-consented id 404s without serving anything.
 */
#[CoversClass(CorpusAudioController::class)]
final class CorpusAudioControllerTest extends TestCase
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
        // A non-allowlisted id must be refused before any repository/filesystem access.
        $etm->expects($this->never())->method('getRepository');
        $etm->expects($this->never())->method('hasDefinition');

        $controller = new CorpusAudioController($etm);
        $response = $controller->audio(['id' => '../../etc/passwd'], $this->account, HttpRequest::create('/media/corpus/audio/x'));

        $this->assertSame(404, $response->getStatusCode());
    }

    #[Test]
    public function non_consented_id_returns_404(): void
    {
        $query = $this->createMock(EntityQueryInterface::class);
        $query->method('setAccount')->willReturnSelf();
        $query->method('condition')->willReturnSelf();
        $query->method('range')->willReturnSelf();
        // No published, consent-public row carries this id.
        $query->method('execute')->willReturn([]);

        $repository = $this->createMock(EntityRepositoryInterface::class);
        $repository->method('getQuery')->willReturn($query);

        $etm = $this->createMock(EntityTypeManager::class);
        $etm->method('hasDefinition')->willReturn(true);
        $etm->method('getRepository')->willReturn($repository);

        $controller = new CorpusAudioController($etm);
        $response = $controller->audio(['id' => 'sb-999'], $this->account, HttpRequest::create('/media/corpus/audio/sb-999'));

        $this->assertSame(404, $response->getStatusCode());
    }
}
