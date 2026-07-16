<?php

declare(strict_types=1);

namespace App\Tests\Unit\Http\Controller\Language;

use App\Http\Controller\Language\CorpusImageController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Entity\Storage\EntityQueryInterface;
use Waaseyaa\Entity\Storage\EntityStorageInterface;

/**
 * Locks the corpus-image consent boundary (#852), mirroring the audio gate. A
 * thumbnail is served ONLY for a consent_public + published example_sentence;
 * a traversal-shaped id never reaches the filesystem, and a non-consented id
 * 404s without serving anything.
 */
#[CoversClass(CorpusImageController::class)]
final class CorpusImageControllerTest extends TestCase
{
    private AccountInterface $account;

    protected function setUp(): void
    {
        $this->account = $this->createMock(AccountInterface::class);
    }

    #[Test]
    public function thumb_rejects_a_traversal_shaped_id_before_touching_storage(): void
    {
        $etm = $this->createMock(EntityTypeManager::class);
        $etm->expects($this->never())->method('getStorage');
        $etm->expects($this->never())->method('hasDefinition');

        $controller = new CorpusImageController($etm);
        $response = $controller->thumb(['id' => '../../etc/passwd'], $this->account, HttpRequest::create('/media/corpus/thumb/x'));

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

        $storage = $this->createMock(EntityStorageInterface::class);
        $storage->method('getQuery')->willReturn($query);

        $etm = $this->createMock(EntityTypeManager::class);
        $etm->method('hasDefinition')->willReturn(true);
        $etm->method('getStorage')->willReturn($storage);

        $controller = new CorpusImageController($etm);
        $response = $controller->thumb(['id' => 'sb-999'], $this->account, HttpRequest::create('/media/corpus/thumb/sb-999'));

        $this->assertSame(404, $response->getStatusCode());
    }
}
