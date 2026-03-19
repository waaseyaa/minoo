<?php

declare(strict_types=1);

namespace Minoo\Surface;

use Symfony\Component\HttpFoundation\Request;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Access\EntityAccessHandler;
use Waaseyaa\AdminSurface\Catalog\CatalogBuilder;
use Waaseyaa\AdminSurface\Host\AbstractAdminSurfaceHost;
use Waaseyaa\AdminSurface\Host\AdminSurfaceResultData;
use Waaseyaa\AdminSurface\Host\AdminSurfaceSessionData;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Entity\Storage\EntityStorageInterface;

final class MinooSurfaceHost extends AbstractAdminSurfaceHost
{
    private ?AccountInterface $currentAccount = null;

    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
        private readonly ?EntityAccessHandler $accessHandler = null,
    ) {}

    public function resolveSession(Request $request): ?AdminSurfaceSessionData
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

        $items = array_values(array_map(fn($e) => $e->toArray(), $entities));

        return AdminSurfaceResultData::success($items, [
            'type' => $type,
            'total' => count($items),
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

        return AdminSurfaceResultData::success($entity->toArray());
    }

    public function action(string $type, string $action, array $payload = []): AdminSurfaceResultData
    {
        if (!$this->entityTypeManager->hasDefinition($type)) {
            return AdminSurfaceResultData::error(404, 'Unknown entity type', "Type '{$type}' is not registered.");
        }

        $storage = $this->entityTypeManager->getStorage($type);

        return match ($action) {
            'delete' => $this->handleDelete($storage, $type, $payload),
            default => AdminSurfaceResultData::error(400, 'Unknown action', "Action '{$action}' is not supported."),
        };
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
