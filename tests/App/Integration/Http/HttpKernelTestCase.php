<?php

declare(strict_types=1);

namespace App\Tests\Integration\Http;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;
use Waaseyaa\Foundation\Kernel\AbstractKernel;
use Waaseyaa\Foundation\Kernel\HttpKernel;

/**
 * Boots a real HttpKernel against :memory: SQLite and exposes a send() helper
 * that drives the full middleware + routing + controller + serializer pipeline.
 *
 * HttpKernel::handle() reads from php superglobals via HttpRequest::createFromGlobals();
 * send() populates $_SERVER/$_GET/$_POST/$_COOKIE/$_REQUEST before each call.
 *
 * Baseline mask — applied to response body before comparing to baseline files.
 * Keep this list updated when new non-determinism leaks into captured output:
 *   - ISO 8601 datetimes          → <TIMESTAMP>
 *   - Unix epoch (10+ digit int)  → <EPOCH>
 *   - UUIDs                       → <UUID>
 *   - CSRF token (form + meta)    → <CSRF>
 *   - Bare 64-char hex tokens     → <HEX64>
 *   - PHPSESSID cookie values     → <SESSION>
 *   - Asset version query params  → ?v=<VERSION>
 *
 * Capture mode: UPDATE_BASELINES=1 ./vendor/bin/phpunit ...
 *   Writes the normalized response body to the baseline file instead of comparing.
 */
abstract class HttpKernelTestCase extends TestCase
{
    protected static string $projectRoot;
    protected static HttpKernel $kernel;

    public static function setUpBeforeClass(): void
    {
        // tests/App/Integration/Http/ → 4 levels up to project root.
        self::$projectRoot = dirname(__DIR__, 4);

        $cachePath = self::$projectRoot . '/storage/framework/packages.php';
        if (is_file($cachePath)) {
            unlink($cachePath);
        }

        putenv('WAASEYAA_DB=:memory:');

        self::$kernel = new HttpKernel(self::$projectRoot);
        $boot = new \ReflectionMethod(AbstractKernel::class, 'boot');
        $boot->invoke(self::$kernel);
    }

    public static function tearDownAfterClass(): void
    {
        putenv('WAASEYAA_DB');

        $cachePath = self::$projectRoot . '/storage/framework/packages.php';
        if (is_file($cachePath)) {
            unlink($cachePath);
        }
    }

    /**
     * Drive an HTTP request through the booted kernel via globals simulation.
     *
     * @param array<string, string|int> $query
     * @param array<string, string>     $server Extra $_SERVER entries (e.g. Cookie header for session tests).
     */
    protected function send(string $method, string $uri, array $query = [], array $server = []): Response
    {
        $parsed = parse_url($uri);
        $path = $parsed['path'] ?? '/';
        $queryString = http_build_query($query);

        $_GET = $query;
        $_POST = [];
        $_COOKIE = [];
        $_REQUEST = $query;
        $_FILES = [];
        // Flash messages persist in $_SESSION across test classes within a PHPUnit
        // process; clear to isolate each request from prior test session state.
        if (isset($_SESSION)) {
            $_SESSION = [];
        }

        $_SERVER = array_merge($_SERVER, [
            'REQUEST_METHOD' => $method,
            'REQUEST_URI' => $queryString !== '' ? $path . '?' . $queryString : $path,
            'QUERY_STRING' => $queryString,
            'PATH_INFO' => $path,
            'HTTP_HOST' => 'localhost',
            'SERVER_NAME' => 'localhost',
            'SERVER_PORT' => '80',
            'HTTPS' => '',
            'REQUEST_TIME' => time(),
            'REQUEST_TIME_FLOAT' => microtime(true),
        ], $server);

        return self::$kernel->handle();
    }

    /**
     * Compare response body against baseline file after normalization.
     *
     * With UPDATE_BASELINES=1 in the environment, writes the normalized body
     * to the baseline file instead of asserting equality.
     */
    protected function assertBaseline(string $name, Response $response): void
    {
        $baselineDir = __DIR__ . '/baselines';
        $baselinePath = $baselineDir . '/' . $name;

        $actual = $this->normalizeForBaseline((string) $response->getContent());

        if (getenv('UPDATE_BASELINES') === '1') {
            if (!is_dir($baselineDir)) {
                mkdir($baselineDir, 0o755, true);
            }
            file_put_contents($baselinePath, $actual);
            self::assertTrue(true, 'Baseline captured: ' . $name);

            return;
        }

        self::assertFileExists(
            $baselinePath,
            sprintf('Baseline %s missing. Run UPDATE_BASELINES=1 ./vendor/bin/phpunit to capture.', $name),
        );

        $expected = file_get_contents($baselinePath);
        self::assertSame($expected, $actual, sprintf('Response for %s diverged from baseline.', $name));
    }

    private function normalizeForBaseline(string $content): string
    {
        $content = preg_replace('/\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:?\d{2})?/', '<TIMESTAMP>', $content);
        $content = preg_replace('/\b\d{10,}\b/', '<EPOCH>', $content);
        $content = preg_replace('/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i', '<UUID>', $content);
        $content = preg_replace('/(name="_csrf_token"\s+value=")[^"]+(")/', '$1<CSRF>$2', $content);
        $content = preg_replace('/(name="csrf-token"\s+content=")[^"]+(")/', '$1<CSRF>$2', $content);
        $content = preg_replace('/\b[0-9a-f]{64}\b/i', '<HEX64>', $content);
        $content = preg_replace('/PHPSESSID=[^;"\s]+/', 'PHPSESSID=<SESSION>', $content);
        $content = preg_replace('/\?v=\d+/', '?v=<VERSION>', $content);

        return $content;
    }
}
