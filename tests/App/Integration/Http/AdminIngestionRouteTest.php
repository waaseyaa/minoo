<?php

declare(strict_types=1);

namespace App\Tests\Integration\Http;

use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ensures SSR admin routes are not swallowed by the /admin/{path} catch-all.
 */
final class AdminIngestionRouteTest extends HttpKernelTestCase
{
    #[Test]
    public function admin_ingestion_route_exists_and_is_not_blank_404(): void
    {
        $response = $this->send('GET', '/admin/ingestion');
        $code = $response->getStatusCode();
        self::assertNotSame(
            Response::HTTP_NOT_FOUND,
            $code,
            'GET /admin/ingestion must match admin.ingestion (or auth redirect), not admin_spa 404',
        );
        self::assertContains(
            $code,
            [Response::HTTP_OK, Response::HTTP_FOUND, Response::HTTP_FORBIDDEN, Response::HTTP_UNAUTHORIZED],
            'Unexpected status for /admin/ingestion',
        );
    }
}
