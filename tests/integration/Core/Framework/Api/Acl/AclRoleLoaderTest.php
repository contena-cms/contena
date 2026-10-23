<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\Api\Acl;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Contena\Core\Defaults;
use Contena\Core\Framework\Api\Acl\AclRoleLoader;
use Contena\Core\Framework\Api\Acl\AclRoles;
use Contena\Core\Framework\Api\Util\AccessKeyHelper;
use Contena\Core\Framework\Test\TestCaseHelper\TestIntegration;
use Contena\Core\Framework\Test\TestCaseHelper\TestUser;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Framework\Test\Store\ExtensionBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

/**
 * @internal
 */
class AclRoleLoaderTest extends TestCase
{
    use ExtensionBehaviour;
    use IntegrationTestBehaviour;

    private Connection $connection;

    private AclRoleLoader $loader;

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);
        $this->loader = static::getContainer()->get(AclRoleLoader::class);
    }

    public function testAnAdminUserIsUnrestricted(): void
    {
        $adminId = $this->connection->fetchOne('SELECT LOWER(HEX(id)) FROM admin_user WHERE username = :username', ['username' => 'admin']);
        static::assertIsString($adminId);

        static::assertEquals([$adminId => new AclRoles(true, [])], $this->loader->getUserRoles([$adminId]));
    }

    public function testANonAdminUserHasItsRoles(): void
    {
        $adminUser = TestUser::createNewTestUser($this->connection, ['blog:read']);

        static::assertEquals(
            [$adminUser->getUserId() => new AclRoles(false, [(string) $adminUser->getAclRoleId()])],
            $this->loader->getUserRoles([$adminUser->getUserId()])
        );
    }

    public function testAnAdminIntegrationIsUnrestricted(): void
    {
        $integrationId = TestIntegration::createAdmin($this->connection)->getId();

        static::assertEquals([$integrationId => new AclRoles(true, [])], $this->loader->getIntegrationRoles([$integrationId]));
    }

    public function testANonAdminIntegrationHasItsRoles(): void
    {
        $integration = TestIntegration::create($this->connection, ['blog:read']);

        static::assertEquals(
            [$integration->getId() => new AclRoles(false, [(string) $integration->getAclRoleId()])],
            $this->loader->getIntegrationRoles([$integration->getId()])
        );
    }

    public function testAnAppIntegrationOnlyHasTheAppsRole(): void
    {
        $this->installApp(__DIR__ . '/../../App/Manifest/_fixtures/minimal');
        $row = $this->connection->fetchNumeric('SELECT integration_id, acl_role_id FROM app WHERE name = :name', ['name' => 'minimal']);
        static::assertIsArray($row);
        [$integrationId, $appRoleId] = $row;

        $foreignRoleId = $this->createAclRole(['order:read']);
        $this->connection->insert('integration_role', ['integration_id' => $integrationId, 'acl_role_id' => Uuid::fromHexToBytes($foreignRoleId)]);
        $this->connection->update('integration', ['admin' => 1], ['id' => $integrationId]);

        static::assertEquals(
            [Uuid::fromBytesToHex($integrationId) => new AclRoles(false, [Uuid::fromBytesToHex($appRoleId)])],
            $this->loader->getIntegrationRoles([Uuid::fromBytesToHex($integrationId)])
        );

        $this->removeApp(__DIR__ . '/../../App/Manifest/_fixtures/minimal');
    }

    public function testAUserAndAnIntegrationWithTheSameIdAreKeptApart(): void
    {
        $adminUser = TestUser::createNewTestUser($this->connection, ['blog:read']);
        $this->connection->insert('integration', [
            'id' => Uuid::fromHexToBytes($adminUser->getUserId()),
            'label' => 'admin integration',
            'access_key' => AccessKeyHelper::generateAccessKey('integration'),
            'secret_access_key' => 'secret',
            'admin' => 1,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        static::assertEquals(
            [$adminUser->getUserId() => new AclRoles(false, [(string) $adminUser->getAclRoleId()])],
            $this->loader->getUserRoles([$adminUser->getUserId()])
        );
        static::assertEquals(
            [$adminUser->getUserId() => new AclRoles(true, [])],
            $this->loader->getIntegrationRoles([$adminUser->getUserId()])
        );
    }

    public function testADeletedIntegrationHasNoRoles(): void
    {
        $integration = TestIntegration::create($this->connection, ['blog:read']);
        $this->connection->update(
            'integration',
            ['deleted_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)],
            ['id' => Uuid::fromHexToBytes($integration->getId())]
        );

        static::assertSame([], $this->loader->getIntegrationRoles([$integration->getId()]));
    }

    public function testUnknownIdsAreLeftOut(): void
    {
        static::assertSame([], $this->loader->getUserRoles([Uuid::randomHex()]));
        static::assertSame([], $this->loader->getIntegrationRoles([Uuid::randomHex()]));
    }

    public function testGetPrivilegesForRoles(): void
    {
        $aclRoleId = $this->createAclRole(['user:read', 'user:create', 'category:read']);

        $privileges = $this->loader->getPrivilegesForRoles([$aclRoleId]);

        static::assertCount(1, $privileges);
        static::assertArrayHasKey($aclRoleId, $privileges);
        static::assertTrue($privileges[$aclRoleId]->isAllowed('user', 'read'));
        static::assertTrue($privileges[$aclRoleId]->isAllowed('user', 'create'));
        static::assertTrue($privileges[$aclRoleId]->isAllowed('category', 'read'));
    }

    /**
     * @param list<string> $privileges
     */
    private function createAclRole(array $privileges): string
    {
        $roleId = Uuid::randomHex();

        $this->connection->insert('acl_role', [
            'id' => Uuid::fromHexToBytes($roleId),
            'name' => $roleId,
            'privileges' => json_encode($privileges, \JSON_THROW_ON_ERROR),
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        return $roleId;
    }
}
