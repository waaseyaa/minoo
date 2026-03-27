<?php

declare(strict_types=1);

namespace Minoo\Surface;

use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Access\EntityAccessHandler;
use Waaseyaa\AdminSurface\Catalog\CatalogBuilder;
use Waaseyaa\AdminSurface\Host\AbstractAdminSurfaceHost;
use Waaseyaa\AdminSurface\Host\AdminSurfaceResultData;
use Waaseyaa\AdminSurface\Host\AdminSurfaceSessionData;
use Waaseyaa\Api\Controller\SchemaController;
use Waaseyaa\Api\JsonApiController;
use Waaseyaa\Api\JsonApiError;
use Waaseyaa\Api\JsonApiResource;
use Waaseyaa\Api\ResourceSerializer;
use Waaseyaa\Api\Schema\SchemaPresenter;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Entity\Storage\EntityStorageInterface;

final class MinooSurfaceHost extends AbstractAdminSurfaceHost
{
    private ?AccountInterface $currentAccount = null;

    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
        private readonly ?EntityAccessHandler $accessHandler = null,
        private readonly ?SchemaPresenter $schemaPresenter = null,
    ) {}

    public function resolveSession(\Symfony\Component\HttpFoundation\Request $request): ?AdminSurfaceSessionData
    {
        $account = $request->attributes->get('account');

        if (!$account instanceof AccountInterface) {
            return null;
        }

        if (!$account->hasPermission('administer content')) {
            return null;
        }

        $this->currentAccount = $account;

        $policies = $this->discoverPolicyNames();

        return new AdminSurfaceSessionData(
            accountId: (string) $account->id(),
            accountName: 'Admin',
            roles: $account->getRoles(),
            policies: $policies,
            tenantId: 'minoo',
            tenantName: 'Minoo',
        );
    }

    private const array READ_ONLY_TYPES = ['ingest_log'];

    public function buildCatalog(AdminSurfaceSessionData $session): CatalogBuilder
    {
        $catalog = new CatalogBuilder();

        foreach ($this->entityTypeManager->getDefinitions() as $definition) {
            $entity = $catalog->defineEntity($definition->id(), $definition->getLabel());

            $group = $definition->getGroup();
            if ($group !== null) {
                $entity->group($group);
            }

            foreach ($definition->getFieldDefinitions() as $name => $fieldDef) {
                $entity->field(
                    $name,
                    $fieldDef['label'] ?? $name,
                    $fieldDef['type'] ?? 'string',
                );
            }

            $isConfig = is_subclass_of($definition->getClass(), \Waaseyaa\Entity\ConfigEntityBase::class);
            $isReadOnly = $isConfig || in_array($definition->id(), self::READ_ONLY_TYPES, true);

            if ($isReadOnly) {
                $entity->capabilities([
                    'create' => false,
                    'update' => false,
                    'delete' => false,
                ]);
            } else {
                $entity->action('delete', 'Delete')
                    ->confirm('Are you sure you want to delete this item?')
                    ->dangerous();
            }
        }

        return $catalog;
    }

    public function list(string $type, array $query = []): AdminSurfaceResultData
    {
        if (!$this->entityTypeManager->hasDefinition($type)) {
            return AdminSurfaceResultData::error(404, 'Unknown entity type', "Type '{$type}' is not registered.");
        }

        $storage = $this->entityTypeManager->getStorage($type);
        $entities = $storage->loadMultiple();

        if ($this->accessHandler !== null && $this->currentAccount !== null) {
            $entities = array_filter(
                $entities,
                fn($e) => $this->accessHandler->check($e, 'view', $this->currentAccount)->isAllowed(),
            );
        }

        $entities = array_values($entities);
        $total = count($entities);

        $offset = max(0, (int) ($query['page[offset]'] ?? $query['page']['offset'] ?? 0));
        $limit = (int) ($query['page[limit]'] ?? $query['page']['limit'] ?? 50);
        if ($limit < 1) {
            $limit = 50;
        }
        $limit = min($limit, 500);

        $serializer = $this->serializer();
        $pageEntities = array_slice($entities, $offset, $limit);

        $surfaceEntities = [];
        foreach ($pageEntities as $entity) {
            $surfaceEntities[] = $this->jsonApiResourceToSurfaceEntity(
                $serializer->serialize($entity, $this->accessHandler, $this->currentAccount),
            );
        }

        return AdminSurfaceResultData::success([
            'entities' => $surfaceEntities,
            'total' => $total,
            'offset' => $offset,
            'limit' => $limit,
        ]);
    }

    public function get(string $type, string $id): AdminSurfaceResultData
    {
        if (!$this->entityTypeManager->hasDefinition($type)) {
            return AdminSurfaceResultData::error(404, 'Unknown entity type', "Type '{$type}' is not registered.");
        }

        $storage = $this->entityTypeManager->getStorage($type);
        $entity = $storage->load($id);

        if ($entity === null) {
            return AdminSurfaceResultData::error(404, 'Not found', "Entity '{$type}/{$id}' does not exist.");
        }

        if ($this->accessHandler !== null && $this->currentAccount !== null) {
            if (!$this->accessHandler->check($entity, 'view', $this->currentAccount)->isAllowed()) {
                return AdminSurfaceResultData::error(403, 'Access denied', 'You do not have permission to view this entity.');
            }
        }

        $resource = $this->serializer()->serialize($entity, $this->accessHandler, $this->currentAccount);

        return AdminSurfaceResultData::success($this->jsonApiResourceToSurfaceEntity($resource));
    }

    public function action(string $type, string $action, array $payload = []): AdminSurfaceResultData
    {
        if (!$this->entityTypeManager->hasDefinition($type)) {
            return AdminSurfaceResultData::error(404, 'Unknown entity type', "Type '{$type}' is not registered.");
        }

        $storage = $this->entityTypeManager->getStorage($type);

        return match ($action) {
            'schema' => $this->handleSchema($type),
            'create' => $this->handleCreate($type, $payload),
            'update' => $this->handleUpdate($type, $payload),
            'delete' => $this->handleDelete($storage, $type, $payload),
            default => AdminSurfaceResultData::error(400, 'Unknown action', "Action '{$action}' is not supported."),
        };
    }

    private function handleSchema(string $type): AdminSurfaceResultData
    {
        $presenter = $this->schemaPresenter ?? new SchemaPresenter();
        $controller = new SchemaController(
            $this->entityTypeManager,
            $presenter,
            $this->accessHandler,
            $this->currentAccount,
        );
        $doc = $controller->show($type);
        if ($doc->errors !== []) {
            return $this->jsonApiDocumentToSurfaceError($doc);
        }

        $schema = $doc->meta['schema'] ?? null;
        if (!is_array($schema)) {
            return AdminSurfaceResultData::error(500, 'Internal error', 'Schema payload missing.');
        }

        return AdminSurfaceResultData::success($schema);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function handleCreate(string $type, array $payload): AdminSurfaceResultData
    {
        $api = $this->jsonApi();

        try {
            $doc = $api->store($type, [
                'data' => [
                    'type' => $type,
                    'attributes' => $payload['attributes'] ?? [],
                ],
            ]);
        } catch (\InvalidArgumentException $e) {
            return AdminSurfaceResultData::error(422, 'Unprocessable', $e->getMessage());
        }

        if ($doc->errors !== []) {
            return $this->jsonApiDocumentToSurfaceError($doc);
        }

        if (!$doc->data instanceof JsonApiResource) {
            return AdminSurfaceResultData::error(500, 'Internal error', 'Create returned no resource.');
        }

        return AdminSurfaceResultData::success($this->jsonApiResourceToSurfaceEntity($doc->data));
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function handleUpdate(string $type, array $payload): AdminSurfaceResultData
    {
        $id = $payload['id'] ?? null;
        if ($id === null || $id === '') {
            return AdminSurfaceResultData::error(400, 'Missing ID', 'Payload must include an id field.');
        }

        $api = $this->jsonApi();

        try {
            $doc = $api->update($type, (string) $id, [
                'data' => [
                    'type' => $type,
                    'attributes' => $payload['attributes'] ?? [],
                ],
            ]);
        } catch (\InvalidArgumentException $e) {
            return AdminSurfaceResultData::error(422, 'Unprocessable', $e->getMessage());
        }

        if ($doc->errors !== []) {
            return $this->jsonApiDocumentToSurfaceError($doc);
        }

        if (!$doc->data instanceof JsonApiResource) {
            return AdminSurfaceResultData::error(500, 'Internal error', 'Update returned no resource.');
        }

        return AdminSurfaceResultData::success($this->jsonApiResourceToSurfaceEntity($doc->data));
    }

    private function handleDelete(
        EntityStorageInterface $storage,
        string $type,
        array $payload,
    ): AdminSurfaceResultData {
        $id = $payload['id'] ?? null;

        if ($id === null) {
            return AdminSurfaceResultData::error(400, 'Missing ID', 'Payload must include an id field.');
        }

        $entity = $storage->load($id);

        if ($entity === null) {
            return AdminSurfaceResultData::error(404, 'Not found', "Entity '{$type}/{$id}' does not exist.");
        }

        if ($this->accessHandler !== null && $this->currentAccount !== null) {
            if (!$this->accessHandler->check($entity, 'delete', $this->currentAccount)->isAllowed()) {
                return AdminSurfaceResultData::error(403, 'Access denied', 'You do not have permission to delete this entity.');
            }
        }

        $storage->delete([$entity]);

        return AdminSurfaceResultData::success(['deleted' => true]);
    }

    private function jsonApi(): JsonApiController
    {
        return new JsonApiController(
            $this->entityTypeManager,
            $this->serializer(),
            $this->accessHandler,
            $this->currentAccount,
        );
    }

    private function serializer(): ResourceSerializer
    {
        return new ResourceSerializer($this->entityTypeManager);
    }

    /**
     * @return array{type: string, id: string, attributes: array<string, mixed>}
     */
    private function jsonApiResourceToSurfaceEntity(JsonApiResource $resource): array
    {
        return [
            'type' => $resource->type,
            'id' => $resource->id,
            'attributes' => $resource->attributes,
        ];
    }

    private function jsonApiDocumentToSurfaceError(\Waaseyaa\Api\JsonApiDocument $doc): AdminSurfaceResultData
    {
        $first = $doc->errors[0] ?? null;
        if (!$first instanceof JsonApiError) {
            return AdminSurfaceResultData::error($doc->statusCode, 'Error', 'Request failed.');
        }

        $status = (int) $first->status;

        return AdminSurfaceResultData::error(
            $status,
            $first->title,
            $first->detail !== '' ? $first->detail : null,
        );
    }

    /**
     * @return string[]
     */
    private function discoverPolicyNames(): array
    {
        $policyDir = dirname(__DIR__) . '/Access';

        if (!is_dir($policyDir)) {
            return [];
        }

        $policies = [];

        foreach (glob($policyDir . '/*AccessPolicy.php') as $file) {
            $class = 'Minoo\\Access\\' . basename($file, '.php');
            if (class_exists($class)) {
                $policies[] = $class;
            }
        }

        return $policies;
    }
}
