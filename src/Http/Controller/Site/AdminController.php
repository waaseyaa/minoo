<?php

declare(strict_types=1);

namespace App\Http\Controller\Site;

use Symfony\Component\HttpFoundation\Response;
use Waaseyaa\AdminSurface\AdminSpaFallback;

final class AdminController
{
    private readonly string $projectRoot;

    public function __construct()
    {
        $this->projectRoot = dirname(__DIR__, 4);
    }

    public function spa(): Response
    {
        $spaIndex = $this->projectRoot . '/public/admin/index.html';

        if (file_exists($spaIndex)) {
            return new Response(file_get_contents($spaIndex) ?: '');
        }

        return AdminSpaFallback::htmlResponse('Minoo');
    }
}
