<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Frontend\ContentSystem;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Test\Stub\ContentSystem\TestElementTypeLoader;

/**
 * @internal
 */
class TenantOwnedContentLayoutTest extends TestCase
{
    use IntegrationTestBehaviour;

    private const array ASSIGNMENTS = [
        'blog_content_layout' => 'blog',
        'category_content_layout' => 'category',
        'landing_page_content_layout' => 'landing_page',
        'header_content_layout' => 'header',
        'footer_content_layout' => 'footer',
    ];

    /**
     * @var array<string, Context>
     */
    private array $contexts;

    private string $tenantA;

    private string $tenantB;

    protected function setUp(): void
    {
        $this->tenantA = $this->createTenant('Content layout tenant A')->id;
        $this->tenantB = $this->createTenant('Content layout tenant B')->id;
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
            $ids[$scope] = $this->createLayoutsAndAssignments($scope, $context);
        }

        $expectedLayoutCounts = [
            'platform' => 10,
            'tenant-a' => 5,
            'tenant-b' => 5,
            'global' => 20,
        ];
        $expectedAssignmentCounts = [
            'platform' => 2,
            'tenant-a' => 1,
            'tenant-b' => 1,
            'global' => 4,
        ];

        foreach ($this->contexts as $scope => $context) {
            $layoutIds = array_merge(...array_values(array_map(
                static fn (array $scopeIds): array => array_values($scopeIds['layouts']),
                $ids,
            )));
            static::assertCount(
                $expectedLayoutCounts[$scope],
                $this->repository('content_layout')->searchIds(new Criteria($layoutIds), $context)->getIds(),
                'Unexpected content_layout rows for ' . $scope,
            );

            foreach (self::ASSIGNMENTS as $entityName => $rootSource) {
                $assignmentIds = array_map(
                    static fn (array $scopeIds): string => $scopeIds['assignments'][$rootSource],
                    $ids,
                );
                static::assertCount(
                    $expectedAssignmentCounts[$scope],
                    $this->repository($entityName)->searchIds(new Criteria($assignmentIds), $context)->getIds(),
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
            foreach ($scopeIds['layouts'] as $layoutId) {
                $this->assertStoredTenant('content_layout', $layoutId, $expectedTenants[$scope]);
            }
            foreach (self::ASSIGNMENTS as $entityName => $rootSource) {
                $this->assertStoredTenant($entityName, $scopeIds['assignments'][$rootSource], $expectedTenants[$scope]);
            }
        }

        $this->repository('content_layout')->update([[
            'id' => $ids['tenant-a']['layouts']['blog'],
            'name' => 'Updated tenant A blog layout',
        ]], $this->contexts['tenant-a']);

        foreach (['platform', 'tenant-b', 'global'] as $scope) {
            $this->assertWriteRejected(
                fn () => $this->repository('content_layout')->update([[
                    'id' => $ids['tenant-a']['layouts']['blog'],
                    'name' => 'Invalid cross-scope layout update',
                ]], $this->contexts[$scope]),
                'Expected content_layout write protection for ' . $scope,
            );

            foreach (self::ASSIGNMENTS as $entityName => $rootSource) {
                $this->assertWriteRejected(
                    fn () => $this->repository($entityName)->delete([[
                        'id' => $ids['tenant-a']['assignments'][$rootSource],
                    ]], $this->contexts[$scope]),
                    'Expected ' . $entityName . ' write protection for ' . $scope,
                );
            }
        }

        $this->assertWriteRejected(
            fn () => $this->createAssignment(
                'header_content_layout',
                'header',
                Uuid::randomHex(),
                $ids['tenant-b']['layouts']['header'],
                $this->contexts['tenant-a'],
            ),
            'Expected a tenant header assignment referencing another tenant layout to be rejected',
        );
        $this->assertWriteRejected(
            fn () => $this->createAssignment(
                'footer_content_layout',
                'footer',
                Uuid::randomHex(),
                $ids['platform']['layouts']['footer'],
                $this->contexts['tenant-a'],
            ),
            'Expected a tenant footer assignment referencing a platform layout to be rejected',
        );
    }

    /**
     * @return array{layouts: array<string, string>, assignments: array<string, string>}
     */
    private function createLayoutsAndAssignments(string $scope, Context $context): array
    {
        $layoutIds = [];
        $assignmentIds = [];

        foreach (self::ASSIGNMENTS as $entityName => $rootSource) {
            $layoutId = Uuid::randomHex();
            $assignmentId = Uuid::randomHex();
            $this->repository('content_layout')->create([[
                'id' => $layoutId,
                'name' => 'Content layout ' . $scope . ' ' . $rootSource,
                'version' => '1.0.0',
                'rootSource' => $rootSource,
                'layout' => [[
                    'id' => Uuid::randomHex(),
                    'component' => TestElementTypeLoader::RESOLVABLE,
                    'properties' => [],
                ]],
            ]], $context);
            $this->createAssignment($entityName, $rootSource, $assignmentId, $layoutId, $context);

            $layoutIds[$rootSource] = $layoutId;
            $assignmentIds[$rootSource] = $assignmentId;
        }

        return ['layouts' => $layoutIds, 'assignments' => $assignmentIds];
    }

    private function createAssignment(
        string $entityName,
        string $rootSource,
        string $assignmentId,
        string $layoutId,
        Context $context,
    ): void {
        $payload = [
            'id' => $assignmentId,
            'contentLayoutId' => $layoutId,
        ];
        if (\in_array($rootSource, ['blog', 'category', 'landing_page'], true)) {
            $entityIdProperty = match ($rootSource) {
                'landing_page' => 'landingPageId',
                default => $rootSource . 'Id',
            };
            $payload[$entityIdProperty] = Uuid::randomHex();
        }

        $this->repository($entityName)->create([$payload], $context);
    }

    private function assertStoredTenant(string $table, string $id, ?string $expectedTenantId): void
    {
        $tenantId = static::getContainer()->get(Connection::class)->fetchOne(
            'SELECT LOWER(HEX(`tenant_id`)) FROM `' . $table . '` WHERE `id` = :id',
            ['id' => Uuid::fromHexToBytes($id)],
        );

        static::assertSame($expectedTenantId, $tenantId === false ? null : $tenantId);
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
}
