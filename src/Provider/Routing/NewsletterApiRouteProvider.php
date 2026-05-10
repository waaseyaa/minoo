<?php

declare(strict_types=1);

namespace App\Provider\Routing;

use App\Entity\Community;
use App\Provider\AppCoreServiceProvider;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Routing\RouteBuilder;
use Waaseyaa\Routing\WaaseyaaRouter;

final class NewsletterApiRouteProvider extends AppCoreServiceProvider
{
    public function routes(WaaseyaaRouter $router, ?EntityTypeManager $entityTypeManager = null): void
    {
        // =====================================================================
        // --- Newsletter ---
        // =====================================================================

        $router->addRoute(
            'newsletter.editor.list',
            RouteBuilder::create('/coordinator/newsletter')
                ->controller('App\Http\Controller\Newsletter\NewsletterEditorController::list')
                ->requireRole('community_coordinator')
                ->render()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'newsletter.editor.new',
            RouteBuilder::create('/coordinator/newsletter/new')
                ->controller('App\Http\Controller\Newsletter\NewsletterEditorController::create')
                ->requireRole('community_coordinator')
                ->render()
                ->methods('POST')
                ->build(),
        );

        // Submission moderation routes are registered BEFORE the generic
        // /coordinator/newsletter/{id} route so "/submissions" is not
        // accidentally captured as an edition id.
        $router->addRoute(
            'newsletter.editor.submissions',
            RouteBuilder::create('/coordinator/newsletter/submissions')
                ->controller('App\Http\Controller\Newsletter\NewsletterEditorController::submissionsList')
                ->requireRole('community_coordinator')
                ->render()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'newsletter.editor.submission_approve',
            RouteBuilder::create('/coordinator/newsletter/submissions/{id}/approve')
                ->controller('App\Http\Controller\Newsletter\NewsletterEditorController::submissionApprove')
                ->requireRole('community_coordinator')
                ->render()
                ->methods('POST')
                ->build(),
        );

        $router->addRoute(
            'newsletter.editor.submission_reject',
            RouteBuilder::create('/coordinator/newsletter/submissions/{id}/reject')
                ->controller('App\Http\Controller\Newsletter\NewsletterEditorController::submissionReject')
                ->requireRole('community_coordinator')
                ->render()
                ->methods('POST')
                ->build(),
        );

        $router->addRoute(
            'newsletter.editor.assemble',
            RouteBuilder::create('/coordinator/newsletter/{id}/assemble')
                ->controller('App\Http\Controller\Newsletter\NewsletterEditorController::assemble')
                ->requireRole('community_coordinator')
                ->render()
                ->methods('POST')
                ->build(),
        );

        $router->addRoute(
            'newsletter.editor.show',
            RouteBuilder::create('/coordinator/newsletter/{id}')
                ->controller('App\Http\Controller\Newsletter\NewsletterEditorController::show')
                ->requireRole('community_coordinator')
                ->render()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'newsletter.editor.approve',
            RouteBuilder::create('/coordinator/newsletter/{id}/approve')
                ->controller('App\Http\Controller\Newsletter\NewsletterEditorController::approve')
                ->requireRole('community_coordinator')
                ->render()
                ->methods('POST')
                ->build(),
        );

        $router->addRoute(
            'newsletter.editor.generate',
            RouteBuilder::create('/coordinator/newsletter/{id}/generate')
                ->controller('App\Http\Controller\Newsletter\NewsletterEditorController::generate')
                ->requireRole('community_coordinator')
                ->render()
                ->methods('POST')
                ->build(),
        );

        $router->addRoute(
            'newsletter.editor.send',
            RouteBuilder::create('/coordinator/newsletter/{id}/send')
                ->controller('App\Http\Controller\Newsletter\NewsletterEditorController::send')
                ->requireRole('community_coordinator')
                ->render()
                ->methods('POST')
                ->build(),
        );

        // Public newsletter surface. Order matters: more specific routes
        // (print_preview, /newsletter, /newsletter/submit, .pdf, vol-issue)
        // are registered BEFORE the catch-all /newsletter/{community}.
        $router->addRoute(
            'newsletter.print_preview',
            RouteBuilder::create('/newsletter/_internal/{id}/print')
                ->controller('App\Http\Controller\Newsletter\NewsletterController::printPreview')
                ->allowAll()
                ->render()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'newsletter.public.index',
            RouteBuilder::create('/newsletter')
                ->controller('App\Http\Controller\Newsletter\NewsletterController::index')
                ->allowAll()
                ->render()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'newsletter.public.submit_form',
            RouteBuilder::create('/newsletter/submit')
                ->controller('App\Http\Controller\Newsletter\NewsletterController::submitForm')
                ->allowAll()
                ->render()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'newsletter.public.submit_post',
            RouteBuilder::create('/newsletter/submit')
                ->controller('App\Http\Controller\Newsletter\NewsletterController::submitPost')
                ->allowAll()
                ->render()
                ->methods('POST')
                ->build(),
        );

        $router->addRoute(
            'newsletter.public.pdf',
            RouteBuilder::create('/newsletter/{community}/{volume}-{issue}.pdf')
                ->controller('App\Http\Controller\Newsletter\NewsletterController::downloadPdf')
                ->allowAll()
                ->render()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'newsletter.public.edition',
            RouteBuilder::create('/newsletter/{community}/{volume}-{issue}')
                ->controller('App\Http\Controller\Newsletter\NewsletterController::showEdition')
                ->allowAll()
                ->render()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'newsletter.public.community',
            RouteBuilder::create('/newsletter/{community}')
                ->controller('App\Http\Controller\Newsletter\NewsletterController::showCommunity')
                ->allowAll()
                ->render()
                ->methods('GET')
                ->build(),
        );

        // =====================================================================
        // Route lockdown (Phase 1): explicit routes for pages that previously
        // depended on `RenderController::tryRenderPathTemplate()` fallback.
        // Preserves URLs and behavior exactly; no new render logic.
        // =====================================================================

        $router->addRoute(
            'static.about',
            RouteBuilder::create('/about')
                ->controller('App\Http\Controller\Site\StaticPageController::about')
                ->allowAll()->render()->methods('GET')->build(),
        );

        $router->addRoute(
            'games.agim.short',
            RouteBuilder::create('/agim')
                ->controller('App\Http\Controller\Games\AgimController::page')
                ->allowAll()->render()->methods('GET')->build(),
        );

        $router->addRoute(
            'games.guess_price.short',
            RouteBuilder::create('/guess-price')
                ->controller('App\Http\Controller\Games\GuessPriceController::page')
                ->allowAll()->render()->methods('GET')->build(),
        );

        $router->addRoute(
            'games.guess_price.short.trailing_redirect',
            RouteBuilder::create('/guess-price/')
                ->controller(static fn (): Response => new RedirectResponse('/guess-price', Response::HTTP_PERMANENTLY_REDIRECT))
                ->allowAll()
                ->methods('GET', 'HEAD')
                ->build(),
        );

        $router->addRoute(
            'games.crossword.short',
            RouteBuilder::create('/crossword')
                ->controller('App\Http\Controller\Games\CrosswordController::page')
                ->allowAll()->render()->methods('GET')->build(),
        );

        $router->addRoute(
            'static.data_sovereignty',
            RouteBuilder::create('/data-sovereignty')
                ->controller('App\Http\Controller\Site\StaticPageController::dataSovereignty')
                ->allowAll()->render()->methods('GET')->build(),
        );

        $router->addRoute(
            'static.elders',
            RouteBuilder::create('/elders')
                ->controller('App\Http\Controller\Site\StaticPageController::elders')
                ->allowAll()->render()->methods('GET')->build(),
        );

        $router->addRoute(
            'static.games',
            RouteBuilder::create('/games')
                ->controller('App\Http\Controller\Site\StaticPageController::games')
                ->allowAll()->render()->methods('GET')->build(),
        );

        $router->addRoute(
            'static.games.trailing_redirect',
            RouteBuilder::create('/games/')
                ->controller(static fn (): Response => new RedirectResponse('/games', Response::HTTP_PERMANENTLY_REDIRECT))
                ->allowAll()
                ->methods('GET', 'HEAD')
                ->build(),
        );

        $router->addRoute(
            'static.get_involved',
            RouteBuilder::create('/get-involved')
                ->controller('App\Http\Controller\Site\StaticPageController::getInvolved')
                ->allowAll()->render()->methods('GET')->build(),
        );

        $router->addRoute(
            'static.how_it_works',
            RouteBuilder::create('/how-it-works')
                ->controller('App\Http\Controller\Site\StaticPageController::howItWorks')
                ->allowAll()->render()->methods('GET')->build(),
        );

        $router->addRoute(
            'static.journey',
            RouteBuilder::create('/journey')
                ->controller('App\Http\Controller\Site\StaticPageController::journey')
                ->allowAll()->render()->methods('GET')->build(),
        );

        $router->addRoute(
            'static.legal',
            RouteBuilder::create('/legal')
                ->controller('App\Http\Controller\Site\StaticPageController::legal')
                ->allowAll()->render()->methods('GET')->build(),
        );

        $router->addRoute(
            'static.legal.section',
            RouteBuilder::create('/legal/{section}')
                ->controller('App\Http\Controller\Site\StaticPageController::legal')
                ->allowAll()->render()->methods('GET')->build(),
        );

        $router->addRoute(
            'static.matcher',
            RouteBuilder::create('/matcher')
                ->controller('App\Http\Controller\Site\StaticPageController::matcher')
                ->allowAll()->render()->methods('GET')->build(),
        );

        $router->addRoute(
            'static.messages',
            RouteBuilder::create('/messages')
                ->controller('App\Http\Controller\Site\StaticPageController::messages')
                ->allowAll()->render()->methods('GET')->build(),
        );

        $router->addRoute(
            'static.safety',
            RouteBuilder::create('/safety')
                ->controller('App\Http\Controller\Site\StaticPageController::safety')
                ->allowAll()->render()->methods('GET')->build(),
        );

        $router->addRoute(
            'static.search',
            RouteBuilder::create('/search')
                ->controller('App\Http\Controller\Site\StaticPageController::search')
                ->allowAll()->render()->methods('GET')->build(),
        );

        $router->addRoute(
            'games.shkoda.short',
            RouteBuilder::create('/shkoda')
                ->controller('App\Http\Controller\Games\ShkodaController::page')
                ->allowAll()->render()->methods('GET')->build(),
        );

        $router->addRoute(
            'static.studio',
            RouteBuilder::create('/studio')
                ->controller('App\Http\Controller\Site\StaticPageController::studio')
                ->allowAll()->render()->methods('GET')->build(),
        );

        $router->addRoute(
            'static.volunteer',
            RouteBuilder::create('/volunteer')
                ->controller('App\Http\Controller\Site\StaticPageController::volunteer')
                ->allowAll()->render()->methods('GET')->build(),
        );


    }
}
