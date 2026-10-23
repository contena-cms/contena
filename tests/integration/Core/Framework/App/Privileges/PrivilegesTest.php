<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\App\Privileges;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\App\AppCollection;
use Contena\Core\Framework\App\AppException;
use Contena\Core\Framework\App\Privileges\Privileges;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
class PrivilegesTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Connection $connection;

    private Privileges $privileges;

    /**
     * @var EntityRepository<AppCollection>
     */
    private EntityRepository $appRepository;

    protected function setUp(): void
    {
        $this->connection = $this->getContainer()->get(Connection::class);
        $this->privileges = $this->getContainer()->get(Privileges::class);
        $this->appRepository = $this->getContainer()->get('app.repository');
    }

    public function testSetPrivileges(): void
    {
        $appId = $this->createApp();
        $context = Context::createDefaultContext();

        $this->privileges->setPrivileges($appId, ['user:read', 'user:update'], $context);

        $this->assertPrivileges(
            'TestApp',
            ['user:read', 'user:update'],
            [],
        );
    }

    public function testRequestPrivileges(): void
    {
        $appId = $this->createApp();
        $context = Context::createDefaultContext();

        $this->privileges->requestPrivileges($appId, ['user:read', 'user:update'], $context);

        $this->assertPrivileges(
            'TestApp',
            [],
            ['user:read', 'user:update'],
        );
    }

    public function testRequestPrivilegesRemovesExistingPrivilegesNotIncludedInRequest(): void
    {
        $appId = $this->createApp();
        $context = Context::createDefaultContext();

        $this->privileges->setPrivileges($appId, ['user:read', 'user:update'], $context);
        $this->privileges->requestPrivileges($appId, ['user:read', 'user:write'], $context);

        $this->assertPrivileges(
            'TestApp',
            ['user:read'],
            ['user:write'],
        );
    }

    public function testRequestSamePrivilegesAsExisting(): void
    {
        $appId = $this->createApp();
        $context = Context::createDefaultContext();

        $this->privileges->setPrivileges($appId, ['blog:read', 'blog:update'], $context);
        $this->privileges->requestPrivileges($appId, ['blog:read', 'blog:update'], $context);

        $this->assertPrivileges(
            'TestApp',
            ['blog:read', 'blog:update'],
            [],
        );
    }

    public function testRevokeAllPrivileges(): void
    {
        $appId = $this->createApp();
        $context = Context::createDefaultContext();

        $setPrivileges = ['user:read', 'user:update', 'blog:read', 'blog:update'];
        $this->privileges->setPrivileges($appId, $setPrivileges, $context);

        $this->assertPrivileges(
            'TestApp',
            $setPrivileges,
            []
        );

        $this->privileges->revokeAllForApps([$appId], $context);

        $this->assertPrivileges(
            'TestApp',
            [],
            $setPrivileges,
        );
    }

    public function testAcceptAllPrivilegesAcceptsRequestedPrivileges(): void
    {
        $appId = $this->createApp();
        $context = Context::createDefaultContext();

        $this->privileges->requestPrivileges($appId, ['user:read', 'user:update'], $context);

        $this->assertPrivileges(
            'TestApp',
            [],
            ['user:read', 'user:update']
        );

        $this->privileges->acceptAllForApps([$appId], $context);

        $this->assertPrivileges(
            'TestApp',
            ['user:read', 'user:update'],
            [],
        );
    }

    public function testUpdatePrivilegesAcceptOnly(): void
    {
        $appId = $this->createApp();
        $context = Context::createDefaultContext();

        $this->privileges->requestPrivileges($appId, ['user:read', 'user:update'], $context);

        $this->assertPrivileges(
            'TestApp',
            [],
            ['user:read', 'user:update'],
        );

        $this->privileges->updatePrivileges($appId, ['user:update'], [], $context);

        $this->assertPrivileges(
            'TestApp',
            ['user:update'],
            ['user:read'],
        );
    }

    public function testUpdatePrivilegesRevokeOnly(): void
    {
        $appId = $this->createApp();
        $context = Context::createDefaultContext();

        $this->privileges->setPrivileges($appId, ['user:read', 'user:update'], $context);

        $this->assertPrivileges(
            'TestApp',
            ['user:read', 'user:update'],
            [],
        );

        $this->privileges->updatePrivileges($appId, [], ['user:update'], $context);

        $this->assertPrivileges(
            'TestApp',
            ['user:read'],
            ['user:update'],
        );
    }

    public function testUpdatePrivilegesBoth(): void
    {
        $appId = $this->createApp();
        $context = Context::createDefaultContext();

        $this->privileges->setPrivileges($appId, ['user:read'], $context);
        $this->privileges->requestPrivileges($appId, ['blog:read', 'blog:update'], $context);

        $this->assertPrivileges(
            'TestApp',
            [],
            ['blog:read', 'blog:update'],
        );

        $this->privileges->updatePrivileges($appId, ['blog:read'], ['user:read'], $context);

        // - blog:read is accepted (moved from requested to existing)
        // - user:read is revoked (it was not active, so no change to requested based on this revoke)
        // - blog:update remains in requested
        $this->assertPrivileges(
            'TestApp',
            ['blog:read'],
            ['blog:update'],
        );
    }

    public function testUpdatePrivilegesThrowsExceptionOnConflict(): void
    {
        $appId = $this->createApp();
        $context = Context::createDefaultContext();

        $this->privileges->requestPrivileges($appId, ['user:read', 'user:update'], $context);

        $this->expectExceptionObject(AppException::conflictingPrivilegeUpdate());

        $this->privileges->updatePrivileges($appId, ['user:read'], ['user:read'], $context);
    }

    public function testUpdatePrivilegesNoChanges(): void
    {
        $appId = $this->createApp();
        $context = Context::createDefaultContext();

        $this->privileges->setPrivileges($appId, ['user:read'], $context);
        $this->privileges->requestPrivileges($appId, ['blog:read'], $context);

        $this->assertPrivileges(
            'TestApp',
            [],
            ['blog:read'],
        );

        $this->privileges->updatePrivileges($appId, [], [], $context);
        $this->assertPrivileges(
            'TestApp',
            [],
            ['blog:read'],
        );
    }

    public function testUpdatePrivilegesAcceptNonRequestedPrivileges(): void
    {
        $appId = $this->createApp();
        $context = Context::createDefaultContext();
        $this->privileges->requestPrivileges($appId, ['user:read'], $context);
        $this->assertPrivileges(
            'TestApp',
            [],
            ['user:read'],
        );

        $this->privileges->updatePrivileges($appId, ['blog:read'], [], $context);
        $this->assertPrivileges(
            'TestApp',
            [],
            ['user:read'],
        );
    }

    public function testGetRequestedPrivilegesSingleApp(): void
    {
        $appId = $this->createApp();
        $context = Context::createDefaultContext();

        $this->privileges->requestPrivileges($appId, ['user:read', 'user:update'], $context);

        static::assertSame(
            [
                $appId => ['user:read', 'user:update'],
            ],
            $this->privileges->getRequestedPrivileges([$appId])
        );
    }

    public function testGetRequestedPrivilegesMultiApp(): void
    {
        $appId1 = $this->createApp();
        $appId2 = $this->createApp('App2');
        $context = Context::createDefaultContext();

        $this->privileges->requestPrivileges($appId1, ['user:read', 'user:update'], $context);
        $this->privileges->requestPrivileges($appId2, ['blog:read', 'blog:update'], $context);

        static::assertSame(
            [
                $appId1 => ['user:read', 'user:update'],
                $appId2 => ['blog:read', 'blog:update'],
            ],
            $this->privileges->getRequestedPrivileges([$appId1, $appId2])
        );
    }

    public function testGetPrivilegesSingleApp(): void
    {
        $appId = $this->createApp();
        $context = Context::createDefaultContext();

        $this->privileges->setPrivileges($appId, ['user:read', 'user:update'], $context);

        static::assertSame(
            [
                $appId => ['user:read', 'user:update'],
            ],
            $this->privileges->getPrivileges([$appId])
        );
    }

    public function testGetPrivilegesMultiApp(): void
    {
        $appId1 = $this->createApp();
        $appId2 = $this->createApp('App2');
        $context = Context::createDefaultContext();

        $this->privileges->setPrivileges($appId1, ['user:read', 'user:update'], $context);
        $this->privileges->setPrivileges($appId2, ['blog:read', 'blog:update'], $context);

        static::assertSame(
            [
                $appId1 => ['user:read', 'user:update'],
                $appId2 => ['blog:read', 'blog:update'],
            ],
            $this->privileges->getPrivileges([$appId1, $appId2])
        );
    }

    public function testGetRequestedPrivilegesForAllApps(): void
    {
        $appId1 = $this->createApp();
        $appId2 = $this->createApp('App2');
        $context = Context::createDefaultContext();

        $this->privileges->requestPrivileges($appId1, ['user:read', 'user:update'], $context);
        $this->privileges->requestPrivileges($appId2, ['blog:read', 'blog:update'], $context);

        static::assertSame(
            [
                'TestApp' => ['user:read', 'user:update'],
                'App2' => ['blog:read', 'blog:update'],
            ],
            $this->privileges->getRequestedPrivilegesForAllApps()
        );
    }

    public function testUpdatePrivilegesRevokeNonExistentPrivileges(): void
    {
        $appId = $this->createApp();
        $context = Context::createDefaultContext();

        $this->privileges->setPrivileges($appId, ['user:read'], $context);

        $this->assertPrivileges(
            'TestApp',
            ['user:read'],
            [],
        );

        $this->privileges->updatePrivileges($appId, [], ['blog:read'], $context);
        $this->assertPrivileges(
            'TestApp',
            ['user:read'],
            [],
        );
    }

    /**
     * @param list<string> $expectedPrivileges
     * @param list<string> $expectedRequestedPrivileges
     */
    private function assertPrivileges(
        string $appName,
        array $expectedPrivileges,
        array $expectedRequestedPrivileges
    ): void {
        $privileges = $this->connection->fetchAllAssociative(
            <<<'SQL'
                SELECT acl_role.privileges as privileges, app.requested_privileges as requested_privileges
                FROM app
                INNER JOIN acl_role ON (acl_role.id = app.acl_role_id)
                WHERE app.name = :name
            SQL,
            ['name' => $appName]
        );

        static::assertCount(1, $privileges);

        static::assertSame($expectedPrivileges, json_decode($privileges[0]['privileges'], true, flags: \JSON_THROW_ON_ERROR));
        static::assertSame($expectedRequestedPrivileges, json_decode($privileges[0]['requested_privileges'], true, flags: \JSON_THROW_ON_ERROR));
    }

    private function createApp(string $name = 'TestApp'): string
    {
        $id = Uuid::randomHex();
        $app = [
            'id' => $id,
            'name' => $name,
            'active' => true,
            'path' => __DIR__,
            'version' => '0.0.1',
            'label' => 'test',
            'accessToken' => 'test',
            'appSecret' => 's3cr3t',
            'integration' => [
                'label' => 'test',
                'accessKey' => 'api access key',
                'secretAccessKey' => 'test',
            ],
            'aclRole' => [
                'name' => $name,
            ],
        ];

        $this->appRepository->create([$app], Context::createDefaultContext());

        return $id;
    }
}
