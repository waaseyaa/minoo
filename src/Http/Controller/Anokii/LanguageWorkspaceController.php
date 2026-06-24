<?php

declare(strict_types=1);

namespace App\Http\Controller\Anokii;

use App\Anokii\Pipeline\PipelineCounts;
use App\Http\View\AnokiiShellContext;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\EntityTypeManager;

/**
 * The language module landing at /admin/anokii/language (#888).
 *
 * The corpus pipeline (ingest, transcribe, curate, publish) is the admin face of
 * the language module. This is its Overview: the live funnel (a count per stage,
 * each a jump to where those items are worked) plus the single "do next" CTA. It
 * is the page issue 2 lifted off the workspace home when /admin/anokii became the
 * AdminModules catalog; the Language catalog card links here.
 *
 * Role-gated (admin / elder_coordinator) by {@see \App\Provider\Routing\AnokiiRouteProvider}.
 * Chrome (pipeline nav + breadcrumb) comes from {@see AnokiiShellContext::build()};
 * the Ingest / Transcribe / Curate tabs keep their own controllers.
 */
final class LanguageWorkspaceController
{
    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
        private readonly Environment $twig,
    ) {
    }

    public function index(AccountInterface $account, HttpRequest $request): Response
    {
        $snapshot = (new PipelineCounts($this->entityTypeManager))->compute();

        $html = $this->twig->render('pages/anokii/index.html.twig', AnokiiShellContext::build($account, 'home', [
            'path' => $request->getPathInfo(),
            'pipeline' => $snapshot,
            'counts' => $snapshot['counts'],
            'total' => $snapshot['total'],
            'do_next' => $snapshot['do_next'],
        ]));

        return new Response($html);
    }
}
