<?php

declare(strict_types=1);

namespace App\Tests\Integration\Http;

use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;

/**
 * Staff SSR routes live under /staff so they never collide with the admin-surface /admin/{path} SPA.
 */
final class StaffIngestionRouteTest extends HttpKernelTestCase
{
    #[Test]
    public function staff_ingestion_route_exists_and_is_not_blank_404(): void
    {
        $response = $this->send('GET', '/staff/ingestion');
        $code = $response->getStatusCode();
        self::assertNotSame(
            Response::HTTP_NOT_FOUND,
            $code,
            'GET /staff/ingestion must match staff.ingestion (or auth redirect), not admin_spa 404',
        );
        self::assertContains(
            $code,
            [
                Response::HTTP_OK,
                Response::HTTP_FOUND,
                Response::HTTP_FORBIDDEN,
                Response::HTTP_UNAUTHORIZED,
            ],
            'Unexpected status for /staff/ingestion',
        );
    }

    #[Test]
    public function legacy_admin_ingestion_redirects_to_staff(): void
    {
        $response = $this->send('GET', '/admin/ingestion');
        self::assertSame(Response::HTTP_MOVED_PERMANENTLY, $response->getStatusCode());
        $location = $response->headers->get('Location') ?? '';
        self::assertStringContainsString('/staff/ingestion', $location);
    }

    #[Test]
    public function legacy_admin_users_redirects_to_staff(): void
    {
        $response = $this->send('GET', '/admin/users');
        self::assertSame(Response::HTTP_MOVED_PERMANENTLY, $response->getStatusCode());
        $location = $response->headers->get('Location') ?? '';
        self::assertStringContainsString('/staff/users', $location);
    }
}
