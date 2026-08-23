<?php declare(strict_types=1);

namespace Contena\Core\Framework\Test\TestCaseBase;

use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Tenant\TenantEntity;

/**
 * Provides isolated tenant fixtures and contexts for integration tests.
 *
 * @internal
 */
trait TenantTestBehaviour
{
    /**
     * @param array<string, mixed> $overrides
     */
    protected function createTenant(
        string $name = 'Test tenant',
        ?string $code = null,
        array $overrides = [],
    ): TenantEntity {
        $id = Uuid::randomHex();
        $code ??= 'test-' . \bin2hex(\random_bytes(8));

        $this->tenantRepository()->create([[
            'id' => $id,
            'name' => $name,
            'code' => $code,
            'status' => true,
            ...$overrides,
        ]], Context::createDefaultContext());

        $tenant = $this->tenantRepository()
            ->search(new Criteria([$id]), Context::createDefaultContext())
            ->getEntities()
            ->first();

        TestCase::assertInstanceOf(TenantEntity::class, $tenant);

        return $tenant;
    }

    protected function createTenantContext(TenantEntity|string $tenant): Context
    {
        return Context::createTenantContext($tenant instanceof TenantEntity ? $tenant->id : $tenant);
    }

    protected function createGlobalTenantContext(): Context
    {
        return Context::createGlobalContext();
    }

    /**
     * @return array{memberGroupId: string, navigationCategoryId: string}
     */
    protected function createTenantChannelReferences(
        Context $context,
        string $name = 'Test tenant channel',
        ?string $memberGroupId = null,
        ?string $navigationCategoryId = null,
    ): array {
        if ($context->getTenantId() === null) {
            throw new \InvalidArgumentException('Tenant channel references require a tenant context.');
        }

        if ($memberGroupId === null) {
            $memberGroupId = Uuid::randomHex();
            $this->tenantFixtureRepository('member_group')->create([[
                'id' => $memberGroupId,
                'name' => $name . ' member group',
            ]], $context);
        }

        if ($navigationCategoryId === null) {
            $navigationCategoryId = Uuid::randomHex();
            $this->tenantFixtureRepository('category')->create([[
                'id' => $navigationCategoryId,
                'name' => $name . ' navigation',
            ]], $context);
        }

        return [
            'memberGroupId' => $memberGroupId,
            'navigationCategoryId' => $navigationCategoryId,
        ];
    }

    /**
     * @return EntityRepository<EntityCollection<TenantEntity>>
     */
    private function tenantRepository(): EntityRepository
    {
        /** @var EntityRepository<EntityCollection<TenantEntity>> $repository */
        $repository = static::getContainer()->get('tenant.repository');

        return $repository;
    }

    /**
     * @return EntityRepository<EntityCollection<Entity>>
     */
    private function tenantFixtureRepository(string $entityName): EntityRepository
    {
        /** @var EntityRepository<EntityCollection<Entity>> $repository */
        $repository = static::getContainer()->get($entityName . '.repository');

        return $repository;
    }
}
