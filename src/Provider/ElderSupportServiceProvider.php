<?php

declare(strict_types=1);

namespace Minoo\Provider;

use Minoo\Entity\ElderSupportRequest;
use Minoo\Entity\Volunteer;
use Waaseyaa\Entity\EntityType;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;
use Waaseyaa\Routing\RouteBuilder;
use Waaseyaa\Routing\WaaseyaaRouter;

final class ElderSupportServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->entityType(new EntityType(
            id: 'elder_support_request',
            label: 'Elder Support Request',
            class: ElderSupportRequest::class,
            keys: ['id' => 'esrid', 'uuid' => 'uuid', 'label' => 'name'],
            group: 'elders',
            fieldDefinitions: [
                'phone' => ['type' => 'string', 'label' => 'Phone', 'weight' => 1],
                'community' => ['type' => 'entity_reference', 'label' => 'Community', 'settings' => ['target_type' => 'community'], 'weight' => 5],
                'type' => ['type' => 'string', 'label' => 'Request Type', 'weight' => 10],
                'notes' => ['type' => 'text_long', 'label' => 'Notes', 'weight' => 15],
                'status' => ['type' => 'string', 'label' => 'Status', 'weight' => 20, 'default' => 'open'],
                'assigned_volunteer' => [
                    'type' => 'integer',
                    'label' => 'Assigned Volunteer',
                    'description' => 'ID of the assigned volunteer entity.',
                    'weight' => 25,
                ],
                'assigned_at' => [
                    'type' => 'timestamp',
                    'label' => 'Assigned At',
                    'weight' => 26,
                ],
                'cancelled_reason' => ['type' => 'text_long', 'label' => 'Cancellation Reason', 'weight' => 30],
                'created_at' => ['type' => 'timestamp', 'label' => 'Created', 'weight' => 40],
                'updated_at' => ['type' => 'timestamp', 'label' => 'Updated', 'weight' => 41],
            ],
        ));

        $this->entityType(new EntityType(
            id: 'volunteer',
            label: 'Volunteer',
            class: Volunteer::class,
            keys: ['id' => 'vid', 'uuid' => 'uuid', 'label' => 'name'],
            group: 'elders',
            fieldDefinitions: [
                'phone' => ['type' => 'string', 'label' => 'Phone', 'weight' => 1],
                'community' => ['type' => 'entity_reference', 'label' => 'Community', 'settings' => ['target_type' => 'community'], 'weight' => 3],
                'availability' => ['type' => 'string', 'label' => 'Availability', 'weight' => 5],
                'skills' => ['type' => 'entity_reference', 'label' => 'Skills', 'settings' => ['target_type' => 'taxonomy_term', 'target_vocabulary' => 'volunteer_skills'], 'cardinality' => -1, 'weight' => 10],
                'max_travel_km' => ['type' => 'integer', 'label' => 'Max Travel (km)', 'weight' => 12],
                'account_id' => ['type' => 'integer', 'label' => 'Account ID', 'weight' => 35],
                'notes' => ['type' => 'text_long', 'label' => 'Notes', 'weight' => 15],
                'status' => ['type' => 'string', 'label' => 'Status', 'weight' => 20, 'default' => 'active'],
                'created_at' => ['type' => 'timestamp', 'label' => 'Created', 'weight' => 40],
                'updated_at' => ['type' => 'timestamp', 'label' => 'Updated', 'weight' => 41],
            ],
        ));
    }

    public function routes(WaaseyaaRouter $router): void
    {
        $router->addRoute(
            'elders.request.form',
            RouteBuilder::create('/elders/request')
                ->controller('Minoo\Controller\ElderSupportController::requestForm')
                ->allowAll()
                ->render()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'elders.request.submit',
            RouteBuilder::create('/elders/request')
                ->controller('Minoo\Controller\ElderSupportController::submitRequest')
                ->allowAll()
                ->render()
                ->methods('POST')
                ->build(),
        );

        $router->addRoute(
            'elders.request.detail',
            RouteBuilder::create('/elders/request/{uuid}')
                ->controller('Minoo\Controller\ElderSupportController::requestDetail')
                ->allowAll()
                ->render()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'elders.volunteer.form',
            RouteBuilder::create('/elders/volunteer')
                ->controller('Minoo\Controller\VolunteerController::signupForm')
                ->allowAll()
                ->render()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'elders.volunteer.submit',
            RouteBuilder::create('/elders/volunteer')
                ->controller('Minoo\Controller\VolunteerController::submitSignup')
                ->allowAll()
                ->render()
                ->methods('POST')
                ->build(),
        );

        $router->addRoute(
            'elders.volunteer.detail',
            RouteBuilder::create('/elders/volunteer/{uuid}')
                ->controller('Minoo\Controller\VolunteerController::signupDetail')
                ->allowAll()
                ->render()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'elder.assign',
            RouteBuilder::create('/elders/request/{esrid}/assign')
                ->controller('Minoo\Controller\ElderSupportWorkflowController::assignVolunteer')
                ->requireRole('elder_coordinator')
                ->methods('POST')
                ->build(),
        );

        $router->addRoute(
            'elder.start',
            RouteBuilder::create('/elders/request/{esrid}/start')
                ->controller('Minoo\Controller\ElderSupportWorkflowController::startRequest')
                ->requireRole('volunteer')
                ->methods('POST')
                ->build(),
        );

        $router->addRoute(
            'elder.complete',
            RouteBuilder::create('/elders/request/{esrid}/complete')
                ->controller('Minoo\Controller\ElderSupportWorkflowController::completeRequest')
                ->requireRole('volunteer')
                ->methods('POST')
                ->build(),
        );

        $router->addRoute(
            'elder.confirm',
            RouteBuilder::create('/elders/request/{esrid}/confirm')
                ->controller('Minoo\Controller\ElderSupportWorkflowController::confirmRequest')
                ->requireRole('elder_coordinator')
                ->methods('POST')
                ->build(),
        );

        $router->addRoute(
            'elder.decline',
            RouteBuilder::create('/elders/request/{esrid}/decline')
                ->controller('Minoo\Controller\ElderSupportWorkflowController::declineRequest')
                ->requireRole('volunteer')
                ->methods('POST')
                ->build(),
        );

        $router->addRoute(
            'elder.reassign',
            RouteBuilder::create('/elders/request/{esrid}/reassign')
                ->controller('Minoo\Controller\ElderSupportWorkflowController::reassignVolunteer')
                ->requireRole('elder_coordinator')
                ->methods('POST')
                ->build(),
        );

        $router->addRoute(
            'elder.cancel',
            RouteBuilder::create('/elders/request/{esrid}/cancel')
                ->controller('Minoo\Controller\ElderSupportWorkflowController::cancelRequest')
                ->requireRole('elder_coordinator')
                ->methods('POST')
                ->build(),
        );
    }
}
