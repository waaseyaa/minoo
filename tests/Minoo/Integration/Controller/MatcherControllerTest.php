<?php

declare(strict_types=1);

namespace Minoo\Tests\Integration\Controller;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Foundation\Kernel\HttpKernel;

#[CoversNothing]
final class MatcherControllerTest extends TestCase
{
    private static HttpKernel $kernel;

    public static function setUpBeforeClass(): void
    {
        putenv('WAASEYAA_DB=:memory:');
        // tests/Minoo/Integration/Controller/ → 4 levels up to project root.
        $projectRoot = dirname(__DIR__, 4);

        // Delete stale manifest cache to force fresh compilation.
        $cachePath = $projectRoot . '/storage/framework/packages.php';
        if (is_file($cachePath)) {
            unlink($cachePath);
        }

        self::$kernel = new HttpKernel($projectRoot);
        $ref = new \ReflectionMethod(self::$kernel, 'boot');
        $ref->invoke(self::$kernel);
    }

    #[Test]
    public function matcher_page_renders(): void
    {
        $etm = self::$kernel->getEntityTypeManager();
        $this->assertNotNull($etm->getDefinition('game_session'));

        // Verify matcher game_type is accepted
        $storage = $etm->getStorage('game_session');
        $session = $storage->create([
            'game_type' => 'matcher',
            'mode' => 'practice',
            'direction' => 'ojibwe_to_english',
            'difficulty_tier' => 'easy',
        ]);
        $this->assertSame('matcher', $session->get('game_type'));
        $this->assertSame('in_progress', $session->get('status'));
    }

    #[Test]
    public function matcher_session_saves_and_loads(): void
    {
        $etm = self::$kernel->getEntityTypeManager();
        $storage = $etm->getStorage('game_session');

        $session = $storage->create([
            'game_type' => 'matcher',
            'mode' => 'daily',
            'direction' => 'ojibwe_to_english',
            'difficulty_tier' => 'easy',
            'guesses' => json_encode([['id' => 1, 'ojibwe' => 'makwa', 'english' => 'bear']]),
            'grid_state' => json_encode([['left_id' => '1', 'right_id' => '1', 'correct' => true]]),
        ]);
        $storage->save($session);

        $loaded = $storage->loadByKey('uuid', $session->get('uuid'));
        $this->assertNotNull($loaded);
        $this->assertSame('matcher', $loaded->get('game_type'));

        $pairs = json_decode((string) $loaded->get('guesses'), true);
        $this->assertCount(1, $pairs);
        $this->assertSame('makwa', $pairs[0]['ojibwe']);
    }

    #[Test]
    public function matcher_session_completes(): void
    {
        $etm = self::$kernel->getEntityTypeManager();
        $storage = $etm->getStorage('game_session');

        $session = $storage->create([
            'game_type' => 'matcher',
            'mode' => 'practice',
            'direction' => 'ojibwe_to_english',
            'difficulty_tier' => 'easy',
        ]);
        $storage->save($session);

        $session->set('status', 'completed');
        $storage->save($session);

        $loaded = $storage->load($session->id());
        $this->assertSame('completed', $loaded->get('status'));
    }
}
