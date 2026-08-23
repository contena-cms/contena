<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\System;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Contena\Core\Defaults;
use Contena\Core\Framework\Api\Acl\Role\AclRoleCollection;
use Contena\Core\Framework\Api\Acl\Role\AclRoleEntity;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Contena\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Organization\Aggregate\OrganizationUnit\OrganizationUnitCollection;
use Contena\Core\System\Organization\Aggregate\OrganizationUnit\OrganizationUnitEntity;
use Contena\Core\System\Organization\OrganizationCollection;
use Contena\Core\System\Organization\OrganizationEntity;
use Contena\Core\System\Position\PositionCollection;
use Contena\Core\System\Position\PositionEntity;
use Contena\Core\System\User\UserCollection;
use Contena\Core\System\User\UserEntity;

/**
 * @internal
 */
class TenantOwnedIdentityStructureTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var array<string, Context>
     */
    private array $contexts;

    private string $tenantA;

    private string $tenantB;

    protected function setUp(): void
    {
        $this->tenantA = $this->createTenant('Identity structure tenant A')->id;
        $this->tenantB = $this->createTenant('Identity structure tenant B')->id;
        $this->contexts = [
            'platform' => Context::createDefaultContext(),
            'tenant-a' => Context::createTenantContext($this->tenantA),
            'tenant-b' => Context::createTenantContext($this->tenantB),
            'global' => Context::createGlobalContext(),
        ];
    }

    public function testReadAndWriteMatrix(): void
    {
        $ids = [];
        foreach ($this->contexts as $scope => $context) {
            $ids[$scope] = $this->createIdentityStructure($scope, $context);
        }

        $expectedCounts = [
            'platform' => 2,
            'tenant-a' => 1,
            'tenant-b' => 1,
            'global' => 4,
        ];
        $entityNames = [
            'acl_role' => 'role',
            'position' => 'position',
            'organization_unit' => 'unit',
            'organization' => 'organization',
            'user' => 'user',
        ];

        foreach ($this->contexts as $scope => $context) {
            foreach ($entityNames as $entityName => $idKey) {
                $criteria = new Criteria(array_column($ids, $idKey));
                static::assertCount(
                    $expectedCounts[$scope],
                    $this->repository($entityName)->searchIds($criteria, $context)->getIds(),
                    'Unexpected ' . $entityName . ' rows for ' . $scope,
                );
            }

            foreach (['position_translation' => 'positionId', 'organization_unit_translation' => 'organizationUnitId', 'organization_translation' => 'organizationId'] as $entityName => $parentProperty) {
                $idKey = match ($entityName) {
                    'position_translation' => 'position',
                    'organization_unit_translation' => 'unit',
                    default => 'organization',
                };
                $criteria = new Criteria();
                $criteria->addFilter(new EqualsAnyFilter($parentProperty, array_column($ids, $idKey)));
                static::assertCount(
                    $expectedCounts[$scope],
                    $this->repository($entityName)->searchIds($criteria, $context)->getIds(),
                    'Unexpected ' . $entityName . ' rows for ' . $scope,
                );
            }

            $mappingCriteria = new Criteria();
            $mappingCriteria->addFilter(new EqualsAnyFilter('userId', array_column($ids, 'user')));
            foreach (['acl_user_role', 'user_position'] as $entityName) {
                static::assertCount(
                    $expectedCounts[$scope],
                    $this->repository($entityName)->searchIds($mappingCriteria, $context)->getIds(),
                    'Unexpected ' . $entityName . ' rows for ' . $scope,
                );
            }
        }

        $expectedTenants = [
            'platform' => null,
            'tenant-a' => $this->tenantA,
            'tenant-b' => $this->tenantB,
            'global' => null,
        ];
        foreach ($ids as $scope => $scopeIds) {
            $role = $this->roleRepository()->search(new Criteria([$scopeIds['role']]), Context::createGlobalContext())->getEntities()->first();
            static::assertInstanceOf(AclRoleEntity::class, $role);
            static::assertSame($expectedTenants[$scope], $role->getTenantId());

            $position = $this->positionRepository()->search(
                new Criteria([$scopeIds['position']])->addAssociation('translations'),
                Context::createGlobalContext(),
            )->getEntities()->first();
            static::assertInstanceOf(PositionEntity::class, $position);
            static::assertSame($expectedTenants[$scope], $position->getTenantId());
            static::assertSame($expectedTenants[$scope], $position->getTranslations()?->first()?->getTenantId());

            $unit = $this->organizationUnitRepository()->search(
                new Criteria([$scopeIds['unit']])->addAssociation('translations'),
                Context::createGlobalContext(),
            )->getEntities()->first();
            static::assertInstanceOf(OrganizationUnitEntity::class, $unit);
            static::assertSame($expectedTenants[$scope], $unit->getTenantId());
            static::assertSame($expectedTenants[$scope], $unit->getTranslations()?->first()?->getTenantId());

            $organization = $this->organizationRepository()->search(
                new Criteria([$scopeIds['organization']])->addAssociation('translations'),
                Context::createGlobalContext(),
            )->getEntities()->first();
            static::assertInstanceOf(OrganizationEntity::class, $organization);
            static::assertSame($expectedTenants[$scope], $organization->getTenantId());
            static::assertSame($expectedTenants[$scope], $organization->getTranslations()?->first()?->getTenantId());

            $user = $this->userRepository()->search(new Criteria([$scopeIds['user']]), Context::createGlobalContext())->getEntities()->first();
            static::assertInstanceOf(UserEntity::class, $user);
            static::assertSame($expectedTenants[$scope], $user->getTenantId());

            foreach (['acl_user_role', 'user_position'] as $mappingTable) {
                $tenantId = static::getContainer()->get(Connection::class)->fetchOne(
                    \sprintf('SELECT LOWER(HEX(`tenant_id`)) FROM `%s` WHERE `user_id` = :userId', $mappingTable),
                    ['userId' => Uuid::fromHexToBytes($scopeIds['user'])],
                );
                static::assertSame($expectedTenants[$scope], $tenantId === false ? null : $tenantId);
            }
        }

        $this->assertTenantAWritesSucceed($ids['tenant-a']);
        foreach (['platform', 'tenant-b', 'global'] as $scope) {
            $this->assertTenantAWritesFail($ids['tenant-a'], $this->contexts[$scope], $scope);
        }
    }

    /**
     * @return array{role: string, position: string, unit: string, organization: string, user: string}
     */
    private function createIdentityStructure(string $scope, Context $context): array
    {
        $roleId = Uuid::randomHex();
        $positionId = Uuid::randomHex();
        $unitId = Uuid::randomHex();
        $organizationId = Uuid::randomHex();
        $userId = Uuid::randomHex();
        $businessScope = \str_starts_with($scope, 'tenant-') ? 'tenant' : $scope;

        $this->roleRepository()->create([[
            'id' => $roleId,
            'code' => 'tenant-role-' . $businessScope,
            'name' => 'Tenant role ' . $scope,
            'privileges' => [],
        ]], $context);
        $this->positionRepository()->create([[
            'id' => $positionId,
            'code' => 'tenant-position-' . $businessScope,
            'name' => 'Tenant position ' . $scope,
        ]], $context);
        $this->organizationUnitRepository()->create([[
            'id' => $unitId,
            'technicalName' => 'tenant-unit-' . $businessScope,
            'name' => 'Tenant unit ' . $scope,
        ]], $context);
        $this->organizationRepository()->create([[
            'id' => $organizationId,
            'organizationUnitId' => $unitId,
            'code' => 'tenant-organization-' . $businessScope,
            'name' => 'Tenant organization ' . $scope,
        ]], $context);

        $localeId = static::getContainer()->get(Connection::class)->fetchOne('SELECT LOWER(HEX(`id`)) FROM `locale` LIMIT 1');
        static::assertIsString($localeId);
        $this->userRepository()->create([[
            'id' => $userId,
            'localeId' => $localeId,
            'username' => 'tenant-identity-' . $userId,
            'password' => 'integration-test-password',
            'name' => 'Tenant identity ' . $scope,
            'email' => $userId . '@example.invalid',
            'aclRoles' => [['id' => $roleId]],
            'positions' => [['id' => $positionId]],
        ]], $context);

        return [
            'role' => $roleId,
            'position' => $positionId,
            'unit' => $unitId,
            'organization' => $organizationId,
            'user' => $userId,
        ];
    }

    /**
     * @param array{role: string, position: string, unit: string, organization: string, user: string} $ids
     */
    private function assertTenantAWritesSucceed(array $ids): void
    {
        $context = $this->contexts['tenant-a'];
        $this->roleRepository()->update([['id' => $ids['role'], 'name' => 'Updated tenant role']], $context);
        $this->positionRepository()->update([['id' => $ids['position'], 'position' => 20]], $context);
        $this->organizationUnitRepository()->update([['id' => $ids['unit'], 'position' => 20]], $context);
        $this->organizationRepository()->update([['id' => $ids['organization'], 'position' => 20]], $context);
        $this->userRepository()->update([['id' => $ids['user'], 'name' => 'Updated tenant user']], $context);
        $this->repository('position_translation')->update([[
            'positionId' => $ids['position'],
            'languageId' => Defaults::LANGUAGE_SYSTEM,
            'name' => 'Updated tenant position',
        ]], $context);
        $this->repository('organization_unit_translation')->update([[
            'organizationUnitId' => $ids['unit'],
            'languageId' => Defaults::LANGUAGE_SYSTEM,
            'name' => 'Updated tenant unit',
        ]], $context);
        $this->repository('organization_translation')->update([[
            'organizationId' => $ids['organization'],
            'languageId' => Defaults::LANGUAGE_SYSTEM,
            'name' => 'Updated tenant organization',
        ]], $context);
    }

    /**
     * @param array{role: string, position: string, unit: string, organization: string, user: string} $ids
     */
    private function assertTenantAWritesFail(array $ids, Context $context, string $scope): void
    {
        $updates = [
            ['acl_role', ['id' => $ids['role'], 'name' => 'Invalid role update']],
            ['position', ['id' => $ids['position'], 'position' => 30]],
            ['organization_unit', ['id' => $ids['unit'], 'position' => 30]],
            ['organization', ['id' => $ids['organization'], 'position' => 30]],
            ['user', ['id' => $ids['user'], 'name' => 'Invalid user update']],
        ];
        foreach ($updates as [$entityName, $payload]) {
            $this->assertWriteRejected(
                fn () => $this->repository($entityName)->update([$payload], $context),
                'Expected ' . $entityName . ' write protection for ' . $scope,
            );
        }

        $translationUpdates = [
            ['position_translation', ['positionId' => $ids['position'], 'languageId' => Defaults::LANGUAGE_SYSTEM, 'name' => 'Invalid position translation']],
            ['organization_unit_translation', ['organizationUnitId' => $ids['unit'], 'languageId' => Defaults::LANGUAGE_SYSTEM, 'name' => 'Invalid unit translation']],
            ['organization_translation', ['organizationId' => $ids['organization'], 'languageId' => Defaults::LANGUAGE_SYSTEM, 'name' => 'Invalid organization translation']],
        ];
        foreach ($translationUpdates as [$entityName, $payload]) {
            $this->assertWriteRejected(
                fn () => $this->repository($entityName)->update([$payload], $context),
                'Expected ' . $entityName . ' write protection for ' . $scope,
            );
        }

        $this->assertWriteRejected(
            fn () => $this->repository('acl_user_role')->delete([[
                'userId' => $ids['user'],
                'aclRoleId' => $ids['role'],
            ]], $context),
            'Expected acl_user_role write protection for ' . $scope,
        );
        $this->assertWriteRejected(
            fn () => $this->repository('user_position')->delete([[
                'userId' => $ids['user'],
                'positionId' => $ids['position'],
            ]], $context),
            'Expected user_position write protection for ' . $scope,
        );
    }

    private function assertWriteRejected(\Closure $write, string $message): void
    {
        try {
            $write();
            static::fail($message);
        } catch (WriteException) {
        }
    }

    /**
     * @return EntityRepository<EntityCollection<Entity>>
     */
    private function repository(string $entityName): EntityRepository
    {
        $repository = static::getContainer()->get($entityName . '.repository');
        static::assertInstanceOf(EntityRepository::class, $repository);

        return $repository;
    }

    /**
     * @return EntityRepository<AclRoleCollection>
     */
    private function roleRepository(): EntityRepository
    {
        return static::getContainer()->get('acl_role.repository');
    }

    /**
     * @return EntityRepository<PositionCollection>
     */
    private function positionRepository(): EntityRepository
    {
        return static::getContainer()->get('position.repository');
    }

    /**
     * @return EntityRepository<OrganizationUnitCollection>
     */
    private function organizationUnitRepository(): EntityRepository
    {
        return static::getContainer()->get('organization_unit.repository');
    }

    /**
     * @return EntityRepository<OrganizationCollection>
     */
    private function organizationRepository(): EntityRepository
    {
        return static::getContainer()->get('organization.repository');
    }

    /**
     * @return EntityRepository<UserCollection>
     */
    private function userRepository(): EntityRepository
    {
        return static::getContainer()->get('user.repository');
    }
}
