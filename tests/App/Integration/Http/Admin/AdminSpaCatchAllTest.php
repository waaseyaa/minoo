<?php

declare(strict_types=1);

namespace App\Tests\Integration\Http\Admin;

use App\Tests\Integration\Http\HttpKernelTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;

#[CoversNothing]
final class AdminSpaCatchAllTest extends HttpKernelTestCase
{
    private static ?string $nuxtAssetName = null;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        $jsFiles = glob(self::$projectRoot . '/vendor/waaseyaa/admin-surface/dist/_nuxt/*.js') ?: [];
        sort($jsFiles);
        self::$nuxtAssetName = $jsFiles !== [] ? basename($jsFiles[0]) : null;
    }

    protected function setUp(): void
    {
        $indexPath = self::$projectRoot . '/vendor/waaseyaa/admin-surface/dist/index.html';
        if (!is_file($indexPath)) {
            self::markTestSkipped('admin-surface dist missing — run composer install or bin/waaseyaa admin:build.');
        }
    }

    #[Test]
    public function admin_root_serves_nuxt_html_shell(): void
    {
        $response = $this->send('GET', '/admin/');

        self::assertSame(200, $response->getStatusCode());
        self::assertStringStartsWith('text/html', (string) $response->headers->get('Content-Type'));

        $body = (string) $response->getContent();
        self::assertGreaterThan(500, strlen($body));
        self::assertStringContainsString('/_nuxt/', $body);
    }

    #[Test]
    public function admin_unknown_spa_path_serves_same_shell(): void
    {
        $home = $this->send('GET', '/admin/');
        $login = $this->send('GET', '/admin/login');

        self::assertSame(200, $home->getStatusCode());
        self::assertSame(200, $login->getStatusCode());
        self::assertSame(
            (string) $home->getContent(),
            (string) $login->getContent(),
            'SPA fallback must return identical HTML shell across client-side routes',
        );
    }

    #[Test]
    public function nuxt_asset_is_served_with_javascript_mime(): void
    {
        if (self::$nuxtAssetName === null) {
            self::markTestSkipped('No _nuxt JS asset found in vendor dist.');
        }

        $response = $this->send('GET', '/admin/_nuxt/' . self::$nuxtAssetName);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/javascript', $response->headers->get('Content-Type'));
        self::assertGreaterThan(0, strlen((string) $response->getContent()));
    }

    #[Test]
    public function favicon_is_served_with_image_icon_mime(): void
    {
        $response = $this->send('GET', '/admin/favicon.ico');

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('image/x-icon', $response->headers->get('Content-Type'));
        self::assertGreaterThan(0, strlen((string) $response->getContent()));
    }

    #[Test]
    public function path_traversal_attempts_do_not_leak_filesystem(): void
    {
        $probes = [
            '/admin/../../etc/passwd',
            '/admin/..%2F..%2Fetc%2Fpasswd',
            '/admin/_nuxt/..%2F..%2F..%2Fconfig%2Fwaaseyaa.php',
        ];

        foreach ($probes as $uri) {
            $body = (string) $this->send('GET', $uri)->getContent();

            self::assertStringNotContainsString('root:', $body, "Traversal probe {$uri} leaked /etc/passwd content");
            self::assertStringNotContainsString('nobody:', $body, "Traversal probe {$uri} leaked /etc/passwd content");
            self::assertStringNotContainsString('<?php', $body, "Traversal probe {$uri} leaked PHP source");
        }
    }
}
