<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\System\Integration;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Api\Util\AccessKeyHelper;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Contena\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Integration\IntegrationCollection;
use Contena\Core\System\Integration\IntegrationEntity;

/**
 * @internal
 */
class IntegrationRepositoryTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepository<IntegrationCollection>
     */
    private EntityRepository $repository;

    /**
     * @var EntityRepository<EntityCollection<Entity>>
     */
    private EntityRepository $integrationRoleRepository;

    protected function setUp(): void
    {
        $this->repository = static::getContainer()->get('integration.repository');
        $this->integrationRoleRepository = static::getContainer()->get('integration_role.repository');
    }

    public function testCreationWithAccessKeys(): void
    {
        $id = Uuid::randomHex();

        $records = [
            [
                'id' => $id,
                'label' => 'My app',
                'accessKey' => AccessKeyHelper::generateAccessKey('integration'),
                'secretAccessKey' => AccessKeyHelper::generateSecretAccessKey(),
            ],
        ];

        $context = Context::createDefaultContext();

        $this->repository->create($records, $context);

        $entities = $this->repository->search(new Criteria([$id]), $context);
        $entity = $entities
            ->getEntities()
            ->first();

        static::assertNotNull($entity);
        static::assertCount(1, $entities->getEntities());
        static::assertSame('My app', $entity->getLabel());
    }

    public function testCreationAdminDefaultsToFalse(): void
    {
        $id = Uuid::randomHex();

        $records = [
            [
                'id' => $id,
                'label' => 'My app',
                'accessKey' => AccessKeyHelper::generateAccessKey('integration'),
                'secretAccessKey' => AccessKeyHelper::generateSecretAccessKey(),
            ],
        ];

        $context = Context::createDefaultContext();

        $this->repository->create($records, $context);

        $entities = $this->repository->search(new Criteria([$id]), $context);
        $entity = $entities
            ->getEntities()
            ->first();

        static::assertNotNull($entity);
        static::assertCount(1, $entities->getEntities());
        static::assertSame('My app', $entity->getLabel());
        static::assertFalse($entity->getAdmin());
    }

    public function testCreationWithAdminRole(): void
    {
        $id = Uuid::randomHex();

        $records = [
            [
                'id' => $id,
                'label' => 'My app',
                'accessKey' => AccessKeyHelper::generateAccessKey('integration'),
                'secretAccessKey' => AccessKeyHelper::generateSecretAccessKey(),
                'admin' => true,
            ],
        ];

        $context = Context::createDefaultContext();

        $this->repository->create($records, $context);

        $entities = $this->repository->search(new Criteria([$id]), $context);
        $entity = $entities
            ->getEntities()
            ->first();

        static::assertNotNull($entity);
        static::assertCount(1, $entities->getEntities());
        static::assertSame('My app', $entity->getLabel());
        static::assertTrue($entity->getAdmin());
    }

    public function testReadAndWriteMatrix(): void
    {
        $tenantA = $this->createTenant('Integration tenant A')->id;
        $tenantB = $this->createTenant('Integration tenant B')->id;
        $contexts = [
            'platform' => Context::createDefaultContext(),
            'tenant-a' => Context::createTenantContext($tenantA),
            'tenant-b' => Context::createTenantContext($tenantB),
            'global' => Context::createGlobalContext(),
        ];
        $aclRoleIds = [];
        $ids = [];

        foreach ($contexts as $scope => $context) {
            $aclRoleIds[$scope] = $this->createAclRole($scope, $context);
            $ids[$scope] = $this->createIntegration($scope, $aclRoleIds[$scope], $context);
        }

        $expectedIds = [
            'platform' => [$ids['global'], $ids['platform']],
            'tenant-a' => [$ids['tenant-a']],
            'tenant-b' => [$ids['tenant-b']],
            'global' => array_values($ids),
        ];

        foreach ($contexts as $scope => $context) {
            $actualIds = $this->repository->searchIds(new Criteria(array_values($ids)), $context)->getIds();
            sort($actualIds);
            sort($expectedIds[$scope]);

            static::assertSame($expectedIds[$scope], $actualIds, 'Unexpected integrations for ' . $scope);

            $mappingCriteria = new Criteria();
            $mappingCriteria->addFilter(new EqualsAnyFilter('integrationId', array_values($ids)));

            static::assertCount(
                \count($expectedIds[$scope]),
                $this->integrationRoleRepository->searchIds($mappingCriteria, $context)->getIds(),
                'Unexpected integration role assignments for ' . $scope,
            );
        }

        $globalEntities = $this->repository->search(new Criteria(array_values($ids)), $contexts['global'])->getEntities();
        static::assertNull($globalEntities->get($ids['platform'])?->getTenantId());
        static::assertSame($tenantA, $globalEntities->get($ids['tenant-a'])?->getTenantId());
        static::assertSame($tenantB, $globalEntities->get($ids['tenant-b'])?->getTenantId());
        static::assertNull($globalEntities->get($ids['global'])?->getTenantId());

        $mappingTenants = static::getContainer()->get(Connection::class)->fetchAllKeyValue(
            'SELECT LOWER(HEX(`integration_id`)), LOWER(HEX(`tenant_id`)) FROM `integration_role` WHERE `integration_id` IN (:ids)',
            ['ids' => array_map(Uuid::fromHexToBytes(...), array_values($ids))],
            ['ids' => ArrayParameterType::BINARY],
        );
        static::assertNull($mappingTenants[$ids['platform']]);
        static::assertSame($tenantA, $mappingTenants[$ids['tenant-a']]);
        static::assertSame($tenantB, $mappingTenants[$ids['tenant-b']]);
        static::assertNull($mappingTenants[$ids['global']]);

        $this->repository->update([[
            'id' => $ids['tenant-a'],
            'label' => 'Updated by tenant A',
        ]], $contexts['tenant-a']);

        $updated = $this->repository->search(new Criteria([$ids['tenant-a']]), $contexts['tenant-a'])->getEntities()->first();
        static::assertInstanceOf(IntegrationEntity::class, $updated);
        static::assertSame('Updated by tenant A', $updated->getLabel());

        foreach (['platform', 'tenant-b', 'global'] as $scope) {
            try {
                $this->repository->update([[
                    'id' => $ids['tenant-a'],
                    'label' => 'Invalid update from ' . $scope,
                ]], $contexts[$scope]);
                static::fail('Expected tenant write protection for ' . $scope);
            } catch (WriteException) {
            }

            try {
                $this->integrationRoleRepository->delete([[
                    'integrationId' => $ids['tenant-a'],
                    'aclRoleId' => $aclRoleIds['tenant-a'],
                ]], $contexts[$scope]);
                static::fail('Expected tenant role write protection for ' . $scope);
            } catch (WriteException) {
            }
        }
    }

    private function createIntegration(string $label, string $aclRoleId, Context $context): string
    {
        $id = Uuid::randomHex();
        $this->repository->create([[
            'id' => $id,
            'label' => $label,
            'accessKey' => AccessKeyHelper::generateAccessKey('integration'),
            'secretAccessKey' => AccessKeyHelper::generateSecretAccessKey(),
            'aclRoles' => [['id' => $aclRoleId]],
        ]], $context);

        return $id;
    }

    private function createAclRole(string $scope, Context $context): string
    {
        $id = Uuid::randomHex();
        static::getContainer()->get('acl_role.repository')->create([[
            'id' => $id,
            'code' => 'integration-tenant-matrix-' . $scope,
            'name' => 'Integration tenant matrix ' . $scope,
            'privileges' => [],
        ]], $context);

        return $id;
    }
}
