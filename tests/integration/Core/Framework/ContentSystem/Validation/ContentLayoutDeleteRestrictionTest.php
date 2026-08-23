<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\ContentSystem\Validation;

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Api\Sync\SyncBehavior;
use Contena\Core\Framework\Api\Sync\SyncOperation;
use Contena\Core\Framework\Api\Sync\SyncService;
use Contena\Core\Framework\ContentSystem\Layout\Entity\ContentLayoutDefinition;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Write\Validation\RestrictDeleteViolationException;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Test\Stub\ContentSystem\TestElementTypeLoader;
use Contena\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
class ContentLayoutDeleteRestrictionTest extends TestCase
{
    use IntegrationTestBehaviour;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ids = new IdsCollection();
    }

    #[TestDox('rejects deleting a content layout that is bound to a category assignment')]
    public function testRejectsDeletingCategoryBoundLayout(): void
    {
        $context = Context::createDefaultContext();
        $layoutId = $this->createLayout($context);
        $this->bindCategory($layoutId, $context);

        try {
            $this->layoutRepository()->delete([['id' => $layoutId]], $context);
            static::fail('Expected the RestrictDelete flag to block deleting a bound layout.');
        } catch (RestrictDeleteViolationException $exception) {
            static::assertStringContainsString('category_content_layout', $exception->getMessage());
        }

        static::assertSame($layoutId, $this->layoutRepository()->searchIds(new Criteria([$layoutId]), $context)->firstId());
    }

    #[TestDox('allows deleting a content layout that is not bound to any source')]
    public function testAllowsDeletingUnboundLayout(): void
    {
        $context = Context::createDefaultContext();
        $layoutId = $this->createLayout($context);

        $this->layoutRepository()->delete([['id' => $layoutId]], $context);

        static::assertNull($this->layoutRepository()->searchIds(new Criteria([$layoutId]), $context)->firstId());
    }

    #[TestDox('allows deleting a layout once its binding has been removed')]
    public function testAllowsDeletingLayoutAfterUnbinding(): void
    {
        $context = Context::createDefaultContext();
        $layoutId = $this->createLayout($context);
        $assignmentId = $this->bindCategory($layoutId, $context);

        $this->assignmentRepository()->delete([['id' => $assignmentId]], $context);
        $this->layoutRepository()->delete([['id' => $layoutId]], $context);

        static::assertNull($this->layoutRepository()->searchIds(new Criteria([$layoutId]), $context)->firstId());
    }

    #[TestDox('rejects deleting a bound content layout via the Sync API')]
    public function testRejectsDeletingBoundLayoutViaSyncApi(): void
    {
        $context = Context::createDefaultContext();
        $layoutId = $this->createLayout($context);
        $this->bindCategory($layoutId, $context);

        $operations = [
            new SyncOperation('delete-layout', ContentLayoutDefinition::ENTITY_NAME, SyncOperation::ACTION_DELETE, [['id' => $layoutId]]),
        ];

        try {
            $this->syncService()->sync($operations, $context, new SyncBehavior());
            static::fail('Expected the RestrictDelete flag to block deleting a bound layout via the Sync API.');
        } catch (RestrictDeleteViolationException $exception) {
            static::assertStringContainsString('category_content_layout', $exception->getMessage());
        }

        static::assertSame($layoutId, $this->layoutRepository()->searchIds(new Criteria([$layoutId]), $context)->firstId());
    }

    private function createLayout(Context $context): string
    {
        $id = $this->ids->get('layout');
        $this->layoutRepository()->create([[
            'id' => $id,
            'name' => 'delete-restriction-layout',
            'version' => '1.0.0',
            'rootSource' => 'category',
            'layout' => [
                ['id' => $this->ids->get('element'), 'component' => TestElementTypeLoader::RESOLVABLE, 'properties' => []],
            ],
        ]], $context);

        return $id;
    }

    private function bindCategory(string $layoutId, Context $context): string
    {
        $categoryId = $this->ids->get('category');
        $categoryRepository = $this->getContainer()->get('category.repository');
        static::assertInstanceOf(EntityRepository::class, $categoryRepository);
        $categoryRepository->create([['id' => $categoryId, 'name' => 'delete-restriction-category']], $context);

        $assignmentId = $this->ids->get('assignment');
        $this->assignmentRepository()->create([['id' => $assignmentId, 'categoryId' => $categoryId, 'contentLayoutId' => $layoutId]], $context);

        return $assignmentId;
    }

    private function syncService(): SyncService
    {
        $service = $this->getContainer()->get(SyncService::class);
        static::assertInstanceOf(SyncService::class, $service);

        return $service;
    }

    /**
     * @return EntityRepository<EntityCollection<Entity>>
     */
    private function layoutRepository(): EntityRepository
    {
        $repository = $this->getContainer()->get('content_layout.repository');
        static::assertInstanceOf(EntityRepository::class, $repository);

        return $repository;
    }

    /**
     * @return EntityRepository<EntityCollection<Entity>>
     */
    private function assignmentRepository(): EntityRepository
    {
        $repository = $this->getContainer()->get('category_content_layout.repository');
        static::assertInstanceOf(EntityRepository::class, $repository);

        return $repository;
    }
}
