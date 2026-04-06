<?php

declare(strict_types=1);

namespace Minoo\Controller;

use Symfony\Component\HttpFoundation\Response;

final class AdminController
{
    private readonly string $projectRoot;

    public function __construct()
    {
        $this->projectRoot = dirname(__DIR__, 2);
    }

    public function spa(): Response
    {
        $spaIndex = $this->projectRoot . '/public/admin/index.html';

        if (file_exists($spaIndex)) {
            return new Response(file_get_contents($spaIndex));
        }

        $html = <<<'HTML'
        <!doctype html>
        <html lang="en">
        <head>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title>Admin — Minoo</title>
            <style>
                body { font-family: system-ui, sans-serif; max-width: 40rem; margin: 4rem auto; padding: 0 1rem; }
                h1 { font-size: 1.5rem; }
                code { background: #f3f4f6; padding: 0.2em 0.4em; border-radius: 4px; font-size: 0.9em; }
                .endpoints { margin-block: 1rem; }
                .endpoints dt { font-weight: 600; margin-block-start: 0.5rem; }
                .endpoints dd { margin-inline-start: 1rem; color: #6b7280; }
            </style>
        </head>
        <body>
            <h1>Minoo Admin Surface</h1>
            <p>The admin SPA is not yet built. API endpoints are active:</p>
            <dl class="endpoints">
                <dt>GET /admin/surface/session</dt>
                <dd>Session resolution (requires authentication)</dd>
                <dt>GET /admin/surface/catalog</dt>
                <dd>Entity type catalog</dd>
                <dt>GET /admin/surface/{type}</dt>
                <dd>Entity listing</dd>
                <dt>GET /admin/surface/{type}/{id}</dt>
                <dd>Entity detail</dd>
                <dt>POST /admin/surface/{type}/action/{action}</dt>
                <dd>Action dispatch</dd>
            </dl>
            <p>To run the admin SPA in development:</p>
            <pre><code>cd ../waaseyaa/packages/admin && npm run dev</code></pre>
        </body>
        </html>
        HTML;

        return new Response($html);
    }
}
