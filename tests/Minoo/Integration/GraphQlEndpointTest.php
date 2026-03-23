<?php

declare(strict_types=1);

namespace Minoo\Tests\Integration;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Access\EntityAccessHandler;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Foundation\Kernel\AbstractKernel;
use Waaseyaa\Foundation\Kernel\HttpKernel;
use Waaseyaa\GraphQL\GraphQlEndpoint;

#[CoversNothing]
final class GraphQlEndpointTest extends TestCase
{
    private static string $projectRoot;
    private static EntityTypeManager $entityTypeManager;
    private static EntityAccessHandler $accessHandler;

    public static function setUpBeforeClass(): void
    {
        self::$projectRoot = dirname(__DIR__, 3);
        $cachePath = self::$projectRoot . '/storage/framework/packages.php';
        if (is_file($cachePath)) {
            unlink($cachePath);
        }
        putenv('WAASEYAA_DB=:memory:');
        $kernel = new HttpKernel(self::$projectRoot);
        $boot = new \ReflectionMethod(AbstractKernel::class, 'boot');
        $boot->invoke($kernel);
        self::$entityTypeManager = (new \ReflectionProperty(AbstractKernel::class, 'entityTypeManager'))->getValue($kernel);
        self::$accessHandler = (new \ReflectionProperty(AbstractKernel::class, 'accessHandler'))->getValue($kernel);
    }

    public static function tearDownAfterClass(): void
    {
        putenv('WAASEYAA_DB');
        $cachePath = self::$projectRoot . '/storage/framework/packages.php';
        if (is_file($cachePath)) {
            unlink($cachePath);
        }
    }

    private function buildEndpoint(int $userId = 1, bool $authenticated = true): GraphQlEndpoint
    {
        $account = $this->createStub(AccountInterface::class);
        $account->method('id')->willReturn($userId);
        $account->method('isAuthenticated')->willReturn($authenticated);
        $account->method('hasPermission')->willReturn($authenticated);

        return new GraphQlEndpoint(
            entityTypeManager: self::$entityTypeManager,
            accessHandler: self::$accessHandler,
            account: $account,
        );
    }

    #[Test]
    public function post_entity_type_is_registered(): void
    {
        self::assertTrue(self::$entityTypeManager->hasDefinition('post'), 'Post entity type must be registered');
    }

    #[Test]
    public function authenticated_user_can_query_schema(): void
    {
        $result = $this->buildEndpoint()->handle('POST', json_encode(['query' => '{ __typename }']), []);
        self::assertSame(200, $result['statusCode']);
        self::assertArrayHasKey('data', $result['body']);
        self::assertSame('Query', $result['body']['data']['__typename']);
    }

    #[Test]
    public function post_mutations_exist_in_schema(): void
    {
        $result = $this->buildEndpoint()->handle('POST', json_encode([
            'query' => '{ __schema { mutationType { fields { name } } } }',
        ]), []);
        self::assertSame(200, $result['statusCode']);
        $fieldNames = array_column($result['body']['data']['__schema']['mutationType']['fields'], 'name');
        self::assertContains('createPost', $fieldNames);
        self::assertContains('updatePost', $fieldNames);
        self::assertContains('deletePost', $fieldNames);
    }

    #[Test]
    public function delete_post_mutation_accepts_id_arg(): void
    {
        $result = $this->buildEndpoint()->handle('POST', json_encode([
            'query' => '{ __type(name: "Mutation") { fields { name args { name type { name kind ofType { name } } } } } }',
        ]), []);
        self::assertSame(200, $result['statusCode']);
        $fields = $result['body']['data']['__type']['fields'];
        $deletePost = array_values(array_filter($fields, fn($f) => $f['name'] === 'deletePost'));
        self::assertNotEmpty($deletePost, 'deletePost mutation should exist');
        self::assertSame('id', $deletePost[0]['args'][0]['name']);
    }

    #[Test]
    public function update_post_mutation_accepts_id_and_input(): void
    {
        $result = $this->buildEndpoint()->handle('POST', json_encode([
            'query' => '{ __type(name: "Mutation") { fields { name args { name type { name kind ofType { name } } } } } }',
        ]), []);
        self::assertSame(200, $result['statusCode']);
        $fields = $result['body']['data']['__type']['fields'];
        $updatePost = array_values(array_filter($fields, fn($f) => $f['name'] === 'updatePost'));
        self::assertNotEmpty($updatePost, 'updatePost mutation should exist');
        $argNames = array_column($updatePost[0]['args'], 'name');
        self::assertContains('id', $argNames);
        self::assertContains('input', $argNames);
    }

    #[Test]
    public function anonymous_user_cannot_introspect(): void
    {
        $result = $this->buildEndpoint(userId: 0, authenticated: false)->handle('POST', json_encode([
            'query' => '{ __schema { queryType { name } } }',
        ]), []);
        self::assertSame(200, $result['statusCode']);
        self::assertNotEmpty($result['body']['errors'] ?? []);
    }
}
