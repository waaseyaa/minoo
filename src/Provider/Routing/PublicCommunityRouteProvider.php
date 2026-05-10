<?php

declare(strict_types=1);

namespace App\Provider\Routing;

use App\Entity\Community\Community;
use App\Provider\AppCoreServiceProvider;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Routing\RouteBuilder;
use Waaseyaa\Routing\WaaseyaaRouter;

final class PublicCommunityRouteProvider extends AppCoreServiceProvider
{
    public function routes(WaaseyaaRouter $router, ?EntityTypeManager $entityTypeManager = null): void
    {
        // =====================================================================
        // --- People ---
        // =====================================================================

        $router->addRoute(
            'people.list',
            RouteBuilder::create('/people')
                ->controller('App\Http\Controller\People\PeopleController::list')
                ->allowAll()
                ->render()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'people.show',
            RouteBuilder::create('/people/{slug}')
                ->controller('App\Http\Controller\People\PeopleController::show')
                ->allowAll()
                ->render()
                ->methods('GET')
                ->requirement('slug', '[a-z0-9][a-z0-9-]*[a-z0-9]')
                ->build(),
        );

        // =====================================================================
        // --- Elder Support ---
        // =====================================================================

        $router->addRoute(
            'elders.request.form',
            RouteBuilder::create('/elders/request')
                ->controller('App\Http\Controller\ElderSupport\ElderSupportController::requestForm')
                ->allowAll()
                ->render()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'elders.request.submit',
            RouteBuilder::create('/elders/request')
                ->controller('App\Http\Controller\ElderSupport\ElderSupportController::submitRequest')
                ->allowAll()
                ->render()
                ->methods('POST')
                ->build(),
        );

        $router->addRoute(
            'elders.request.detail',
            RouteBuilder::create('/elders/request/{uuid}')
                ->controller('App\Http\Controller\ElderSupport\ElderSupportController::requestDetail')
                ->allowAll()
                ->render()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'elders.volunteer.form',
            RouteBuilder::create('/elders/volunteer')
                ->controller('App\Http\Controller\People\VolunteerController::signupForm')
                ->allowAll()
                ->render()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'elders.volunteer.submit',
            RouteBuilder::create('/elders/volunteer')
                ->controller('App\Http\Controller\People\VolunteerController::submitSignup')
                ->allowAll()
                ->render()
                ->methods('POST')
                ->build(),
        );

        $router->addRoute(
            'elders.volunteer.detail',
            RouteBuilder::create('/elders/volunteer/{uuid}')
                ->controller('App\Http\Controller\People\VolunteerController::signupDetail')
                ->allowAll()
                ->render()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'elder.assign',
            RouteBuilder::create('/elders/request/{esrid}/assign')
                ->controller('App\Http\Controller\ElderSupport\ElderSupportWorkflowController::assignVolunteer')
                ->requireRole('elder_coordinator')
                ->methods('POST')
                ->build(),
        );

        $router->addRoute(
            'elder.start',
            RouteBuilder::create('/elders/request/{esrid}/start')
                ->controller('App\Http\Controller\ElderSupport\ElderSupportWorkflowController::startRequest')
                ->requireRole('volunteer')
                ->methods('POST')
                ->build(),
        );

        $router->addRoute(
            'elder.complete',
            RouteBuilder::create('/elders/request/{esrid}/complete')
                ->controller('App\Http\Controller\ElderSupport\ElderSupportWorkflowController::completeRequest')
                ->requireRole('volunteer')
                ->methods('POST')
                ->build(),
        );

        $router->addRoute(
            'elder.confirm',
            RouteBuilder::create('/elders/request/{esrid}/confirm')
                ->controller('App\Http\Controller\ElderSupport\ElderSupportWorkflowController::confirmRequest')
                ->requireRole('elder_coordinator')
                ->methods('POST')
                ->build(),
        );

        $router->addRoute(
            'elder.decline',
            RouteBuilder::create('/elders/request/{esrid}/decline')
                ->controller('App\Http\Controller\ElderSupport\ElderSupportWorkflowController::declineRequest')
                ->requireRole('volunteer')
                ->methods('POST')
                ->build(),
        );

        $router->addRoute(
            'elder.reassign',
            RouteBuilder::create('/elders/request/{esrid}/reassign')
                ->controller('App\Http\Controller\ElderSupport\ElderSupportWorkflowController::reassignVolunteer')
                ->requireRole('elder_coordinator')
                ->methods('POST')
                ->build(),
        );

        $router->addRoute(
            'elder.cancel',
            RouteBuilder::create('/elders/request/{esrid}/cancel')
                ->controller('App\Http\Controller\ElderSupport\ElderSupportWorkflowController::cancelRequest')
                ->requireRole('elder_coordinator')
                ->methods('POST')
                ->build(),
        );

        // =====================================================================
        // --- Community ---
        // =====================================================================

        $router->addRoute(
            'communities.list',
            RouteBuilder::create('/communities')
                ->controller('App\\Http\\Controller\\Community\\CommunityController::list')
                ->allowAll()
                ->render()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'communities.crisis_incident',
            RouteBuilder::create('/communities/{slug}/{incident}')
                ->controller('App\\Http\\Controller\\Community\\CommunityController::crisisIncident')
                ->allowAll()
                ->render()
                ->methods('GET')
                ->requirement('slug', '[a-z0-9][a-z0-9-]*[a-z0-9]')
                ->requirement('incident', '[a-z0-9][a-z0-9-]*[a-z0-9]')
                ->build(),
        );

        $router->addRoute(
            'communities.show',
            RouteBuilder::create('/communities/{slug}')
                ->controller('App\\Http\\Controller\\Community\\CommunityController::show')
                ->allowAll()
                ->render()
                ->methods('GET')
                ->requirement('slug', '[a-z0-9][a-z0-9-]*[a-z0-9]')
                ->build(),
        );

        $router->addRoute(
            'communities.autocomplete',
            RouteBuilder::create('/api/communities/autocomplete')
                ->controller('App\\Http\\Controller\\Community\\CommunityController::autocomplete')
                ->allowAll()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'location.current',
            RouteBuilder::create('/api/location/current')
                ->controller('App\\Http\\Controller\\Community\\LocationController::current')
                ->allowAll()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'location.set',
            RouteBuilder::create('/api/location/set')
                ->controller('App\\Http\\Controller\\Community\\LocationController::set')
                ->allowAll()
                ->methods('POST')
                ->build(),
        );

        $router->addRoute(
            'location.update',
            RouteBuilder::create('/api/location/update')
                ->controller('App\\Http\\Controller\\Community\\LocationController::update')
                ->allowAll()
                ->methods('POST')
                ->build(),
        );

        // =====================================================================
        // --- Oral History ---
        // =====================================================================

        $router->addRoute(
            'oral_histories.list',
            RouteBuilder::create('/oral-histories')
                ->controller('App\\Http\\Controller\\OralHistory\\OralHistoryController::list')
                ->allowAll()
                ->render()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'oral_histories.collection',
            RouteBuilder::create('/oral-histories/collections/{slug}')
                ->controller('App\\Http\\Controller\\OralHistory\\OralHistoryController::collection')
                ->allowAll()
                ->render()
                ->methods('GET')
                ->requirement('slug', '[a-z0-9][a-z0-9-]*[a-z0-9]')
                ->build(),
        );

        $router->addRoute(
            'oral_histories.show',
            RouteBuilder::create('/oral-histories/{slug}')
                ->controller('App\\Http\\Controller\\OralHistory\\OralHistoryController::show')
                ->allowAll()
                ->render()
                ->methods('GET')
                ->requirement('slug', '[a-z0-9][a-z0-9-]*[a-z0-9]')
                ->build(),
        );

        // =====================================================================
        // --- Contributors ---
        // =====================================================================

        $router->addRoute(
            'contributors.list',
            RouteBuilder::create('/contributors')
                ->controller('App\\Http\\Controller\\Community\\ContributorController::list')
                ->allowAll()
                ->render()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'contributors.show',
            RouteBuilder::create('/contributors/{slug}')
                ->controller('App\\Http\\Controller\\Community\\ContributorController::show')
                ->allowAll()
                ->render()
                ->methods('GET')
                ->requirement('slug', '[a-z0-9][a-z0-9-]*[a-z0-9]')
                ->build(),
        );
    }
}
