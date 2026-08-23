<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\Api\Acl;

use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Api\ApiException;
use Contena\Core\Framework\Api\Context\AdminApiSource;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
class AclWriteValidatorTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepository<EntityCollection<Entity>>
     */
    private EntityRepository $roleRepository;

    /**
     * @var EntityRepository<EntityCollection<Entity>>
     */
    private EntityRepository $userRoleRepository;

    /**
     * @var EntityRepository<EntityCollection<Entity>>
     */
    private EntityRepository $integrationRoleRepository;

    protected function setUp(): void
    {
        $this->roleRepository = static::getContainer()->get('acl_role.repository');
        $this->userRoleRepository = static::getContainer()->get('acl_user_role.repository');
        $this->integrationRoleRepository = static::getContainer()->get('integration_role.repository');
    }

    public function testNonAdminCannotCreateRoleWithPrivilegesTheyDoNotOwn(): void
    {
        $context = $this->createAdminApiContext(['acl_role:create']);

        $this->expectExceptionObject(ApiException::missingPrivileges(['system.plugin_maintain']));

        $context->scope(Context::SYSTEM_SCOPE, function (Context $context): void {
            $this->roleRepository->create([[
                'id' => Uuid::randomHex(),
                'code' => 'escalated-role',
                'name' => 'Escalated role',
                'privileges' => ['system.plugin_maintain'],
            ]], $context);
        });
    }

    public function testNonAdminCanCreateRoleWithOwnPrivileges(): void
    {
        $context = $this->createAdminApiContext([
            'media.viewer',
            'media:read',
        ]);

        $events = $context->scope(Context::SYSTEM_SCOPE, fn (Context $context) => $this->roleRepository->create([[
            'id' => Uuid::randomHex(),
            'code' => 'media-viewer',
            'name' => 'Media viewer',
            'privileges' => [
                'media.viewer',
                'media:read',
            ],
        ]], $context));

        static::assertNotNull($events->getEventByEntityName('acl_role'));
    }

    public function testNonAdminCannotAssignUserRoleWithPrivilegesTheyDoNotOwn(): void
    {
        $roleId = $this->createElevatedRole();
        $context = $this->createAdminApiContext(['acl_user_role:create']);

        $this->expectExceptionObject(ApiException::missingPrivileges(['system.plugin_maintain']));

        $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($roleId): void {
            $this->userRoleRepository->create([[
                'userId' => Uuid::randomHex(),
                'aclRoleId' => $roleId,
            ]], $context);
        });
    }

    public function testNonAdminCannotAssignIntegrationRoleWithPrivilegesTheyDoNotOwn(): void
    {
        $roleId = $this->createElevatedRole();
        $context = $this->createAdminApiContext(['integration_role:create']);

        $this->expectExceptionObject(ApiException::missingPrivileges(['system.plugin_maintain']));

        $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($roleId): void {
            $this->integrationRoleRepository->create([[
                'integrationId' => Uuid::randomHex(),
                'aclRoleId' => $roleId,
            ]], $context);
        });
    }

    public function testAdminCanCreateRoleWithAnyPrivilege(): void
    {
        $source = new AdminApiSource(null);
        $source->setIsAdmin(true);
        $context = new Context($source);

        $events = $context->scope(Context::SYSTEM_SCOPE, fn (Context $context) => $this->roleRepository->create([[
            'id' => Uuid::randomHex(),
            'code' => 'administrator-managed-role',
            'name' => 'Administrator managed role',
            'privileges' => ['plugin-defined.unrestricted'],
        ]], $context));

        static::assertNotNull($events->getEventByEntityName('acl_role'));
    }

    /**
     * @param list<string> $permissions
     */
    private function createAdminApiContext(array $permissions): Context
    {
        $source = new AdminApiSource(null);
        $source->setPermissions($permissions);

        return new Context($source);
    }

    private function createElevatedRole(): string
    {
        $roleId = Uuid::randomHex();
        $this->roleRepository->create([[
            'id' => $roleId,
            'code' => 'elevated-role',
            'name' => 'Elevated role',
            'privileges' => ['system.plugin_maintain'],
        ]], Context::createDefaultContext());

        return $roleId;
    }
}
