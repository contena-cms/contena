<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\DataAbstractionLayer;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Cms\Aggregate\CmsBlock\CmsBlockEntity;
use Contena\Core\Content\Test\Blog\BlogBuilder;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\VersionManager;
use Contena\Core\Framework\DataAbstractionLayer\Write\CloneBehavior;
use Contena\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Contena\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Contena\Core\Framework\Struct\ArrayEntity;
use Contena\Core\Framework\Test\DataAbstractionLayer\Field\DataAbstractionLayerFieldTestBehaviour;
use Contena\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\ManyToOneBlogDefinition;
use Contena\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\ToOneBlogExtension;
use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Test\Integration\EventDispatcher\EventHookDispatcher;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Contena\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

/**
 * @internal
 */
class VersionManagerTest extends TestCase
{
    use DataAbstractionLayerFieldTestBehaviour {
        tearDown as protected tearDownDefinitions;
    }
    use KernelTestBehaviour;

    private const BLOG_ID = 'blog-1';

    private Connection $connection;

    /**
     * @var EntityRepository<BlogCollection>
     */
    private EntityRepository $blogRepository;

    private VersionManager $versionManager;

    private Context $context;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);
        $this->versionManager = static::getContainer()->get(VersionManager::class);

        $this->blogRepository = static::getContainer()->get('blog.repository');
        $this->registerEntityDefinitionAndInitDatabase();
        $this->context = Context::createDefaultContext();
        $this->ids = new IdsCollection();
    }

    protected function tearDown(): void
    {
        $this->tearDownDefinitions();
        $this->connection->rollBack();
        $this->connection->executeStatement('
            ALTER TABLE `blog`
            DROP FOREIGN KEY `fk.blog.many_to_one_id`;
        ');
        $this->connection->executeStatement('DROP TABLE IF EXISTS `many_to_one_product`');
        $this->connection->executeStatement('
            ALTER TABLE `blog`
            DROP COLUMN `many_to_one_id`
        ');

        // reboot kernel to create a new container since we manipulated the original one
        KernelLifecycleManager::bootKernel();
    }

    public function testWhenAddAnExtensionWithFKIdThenFKIdShouldBeCloned(): void
    {
        $extendableId = Uuid::randomHex();
        $blog = (new BlogBuilder($this->ids, self::BLOG_ID))->stock(1)
            ->name('Test Blog')->price(1000)->build();

        $blog['manyToOne'] = [
            'id' => $extendableId,
        ];
        $blogId = $blog['id'];
        $this->blogRepository->create([$blog], $this->context);

        $criteria = (new Criteria([$blogId]))->addAssociation('manyToOne');

        $blog = $this->blogRepository->search($criteria, $this->context)->getEntities()->first();
        static::assertNotNull($blog);

        static::assertTrue($blog->hasExtension('manyToOne'));
        $extension = $blog->getExtension('manyToOne');

        static::assertInstanceOf(ArrayEntity::class, $extension);
        static::assertSame($extendableId, $extension->get('id'));

        $criteria = (new Criteria())->addFilter(new EqualsFilter('manyToOne.id', $extendableId));

        $blogs = $this->blogRepository->searchIds($criteria, $this->context);
        static::assertTrue($blogs->has($blogId));

        $clonedAffected = $this->getClone($blogId);

        $clonedBlog = $clonedAffected['blog'][0];
        static::assertInstanceOf(EntityWriteResult::class, $clonedBlog);

        $clonedBlogId = $clonedBlog->getPayload()['id'];
        $clonedManyToOneId = $clonedBlog->getPayload()['manyToOneId'];
        static::assertIsString($clonedBlogId);
        static::assertNotSame('', $clonedBlogId);
        static::assertSame($extendableId, $clonedManyToOneId);
    }

    public function testContextScopeAvailableDuringMerge(): void
    {
        $ids = new IdsCollection();

        $blog = (new BlogBuilder($ids, 'p1'))->price(100)->build();

        $context = Context::createDefaultContext();

        static::getContainer()->get('blog.repository')->create([$blog], $context);

        $versionId = static::getContainer()->get('blog.repository')
            ->createVersion($ids->get('p1'), $context);

        $versionContext = $context->createWithVersionId($versionId);

        static::getContainer()->get('blog.repository')
            ->update([['id' => $ids->get('p1'), 'name' => 'test']], $versionContext);

        // now ensure that we get a validate event for the merge request
        $mergeScopes = [];

        EventHookDispatcher::fromContainer(static::getContainer())->on(
            PreWriteValidationEvent::class,
            static function (PreWriteValidationEvent $event) use (&$mergeScopes): void {
                // we also get a validation event for the version tables
                if (!$event->getPrimaryKeys('blog')) {
                    return;
                }

                $mergeScopes[] = $event->getWriteContext()->hasState(VersionManager::MERGE_SCOPE);
            }
        );

        static::getContainer()->get('blog.repository')->merge($versionId, $context);

        static::assertNotSame([], $mergeScopes);
        // some validators depend on that to disable insert/update validation for merge requests
        static::assertNotContains(false, $mergeScopes);
    }

    public function testMergeKeepsInsertOperationForEntityCreatedAndUpdatedInVersion(): void
    {
        $context = Context::createDefaultContext();

        $pageRepository = static::getContainer()->get('cms_page.repository');
        $versionRepository = static::getContainer()->get('version.repository');

        $versionId = Uuid::randomHex();
        $pageId = Uuid::randomHex();

        $versionRepository->create([['id' => $versionId]], $context);
        $versionContext = $context->createWithVersionId($versionId);

        $pageRepository->create([[
            'id' => $pageId,
            'type' => 'landingpage',
        ]], $versionContext);

        $pageRepository->update([[
            'id' => $pageId,
            'cssClass' => 'updated',
        ]], $versionContext);

        $pageWriteResult = null;

        EventHookDispatcher::fromContainer(static::getContainer())->on(
            'cms_page.written',
            static function (EntityWrittenEvent $event) use (&$pageWriteResult, $pageId): void {
                foreach ($event->getWriteResults() as $writeResult) {
                    if ($writeResult->getPrimaryKey() !== $pageId) {
                        continue;
                    }

                    $pageWriteResult = $writeResult;
                }
            }
        );

        $pageRepository->merge($versionId, $context);

        static::assertInstanceOf(EntityWriteResult::class, $pageWriteResult);
        static::assertSame(EntityWriteResult::OPERATION_INSERT, $pageWriteResult->getOperation());
    }

    public function testWhenNotAddingFKThenItShouldNotBeAvailable(): void
    {
        $blog = (new BlogBuilder($this->ids, self::BLOG_ID))->stock(1)
            ->name('Test Blog')->price(1000)->build();

        $this->blogRepository->create([$blog], $this->context);
        $criteria = (new Criteria([$blog['id']]))->addAssociation('manyToOne');

        $blog = $this->blogRepository->search($criteria, $this->context)->getEntities()->first();
        static::assertNotNull($blog);

        $extension = $blog->getExtension('manyToOne');
        static::assertNull($extension);

        $clonedAffected = $this->getClone($blog->getId());

        $clonedBlog = $clonedAffected['blog'][0];
        static::assertInstanceOf(EntityWriteResult::class, $clonedBlog);
        $clonedManyToOne = $clonedBlog->getPayload();
        static::assertArrayNotHasKey('manyToOneId', $clonedManyToOne);
    }

    public function testMergeActionCleansUpSlotsReferencingDeletedBlocks(): void
    {
        $context = Context::createDefaultContext();
        $versionManager = static::getContainer()->get(VersionManager::class);

        $pageRepository = static::getContainer()->get('cms_page.repository');
        $sectionRepository = static::getContainer()->get('cms_section.repository');
        $blockRepository = static::getContainer()->get('cms_block.repository');
        $slotRepository = static::getContainer()->get('cms_slot.repository');

        $pageId = Uuid::randomHex();
        $sectionId = Uuid::randomHex();
        $blockId = Uuid::randomHex();
        $slotId = Uuid::randomHex();

        // Create CMS Page
        $pageRepository->upsert([[
            'id' => $pageId,
            'type' => 'landingpage',
        ]], $context);

        // Create a draft version for the CMS Page
        $pageDefinition = static::getContainer()
            ->get(DefinitionInstanceRegistry::class)
            ->getByEntityName('cms_page');
        $writeContext = WriteContext::createFromContext($context);

        $draftVersionId = $versionManager->createVersion($pageDefinition, $pageId, $writeContext);
        $draftContext = $context->createWithVersionId($draftVersionId);

        // Create a CMS Section in the draft version
        $sectionRepository->upsert([[
            'id' => $sectionId,
            'pageId' => $pageId,
            'cmsPageVersionId' => $draftVersionId,
            'type' => 'default',
            'position' => 1,
        ]], $draftContext);

        // Create a CMS Block in the draft version with a slot
        $blockRepository->upsert([[
            'id' => $blockId,
            'type' => 'default',
            'position' => 1,
            'sectionId' => $sectionId,
            'sectionVersionId' => $draftVersionId,
            'slots' => [[
                'id' => $slotId,
                'type' => 'text',
                'slot' => 'content',
                'position' => 1,
            ]],
        ]], $draftContext);

        // Verify draft version
        $criteria = (new Criteria([$blockId]))->addAssociation('slots');
        $draftBlock = $blockRepository->search($criteria, $draftContext)->getEntities()->first();

        static::assertInstanceOf(CmsBlockEntity::class, $draftBlock);
        static::assertNotNull($draftBlock->getSlots(), 'Block should have slots in draft version.');
        static::assertNotCount(0, $draftBlock->getSlots(), 'Block should have slots in draft version.');

        // Delete block to trigger cleanupSlotsReferencingDeletedBlocks()
        $blockRepository->delete([['id' => $blockId, 'versionId' => $draftVersionId]], $draftContext);

        $slotsInDraft = $slotRepository->search(new Criteria([$slotId]), $draftContext);
        static::assertCount(0, $slotsInDraft->getEntities(), 'Slots should be removed when block is deleted.');

        $versionManager->merge($draftVersionId, WriteContext::createFromContext($context));

        // Verify that deleted block is not in the live version
        $mergedBlock = $blockRepository->search(
            (new Criteria([$blockId]))->addAssociation('slots'),
            $context
        )->getEntities()->first();

        static::assertNull($mergedBlock, 'Deleted block should not exist in the live version.');

        $slotsInLive = $slotRepository->search(new Criteria([$slotId]), $context);
        static::assertCount(0, $slotsInLive->getEntities(), 'Deleted block’s slots should also be removed in live version.');
    }

    private function registerEntityDefinitionAndInitDatabase(): void
    {
        $this->registerDefinition(ManyToOneBlogDefinition::class);

        $this->registerDefinitionWithExtensions(
            BlogDefinition::class,
            ToOneBlogExtension::class
        );

        $this->connection->executeStatement('
            DROP TABLE IF EXISTS `many_to_one_product`;
            CREATE TABLE `many_to_one_product` (
                `id` BINARY(16) NOT NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`)
            )
        ');

        $this->connection->executeStatement('
            ALTER TABLE `blog`
                ADD COLUMN `many_to_one_id` binary(16) NULL,
                ADD CONSTRAINT `fk.blog.many_to_one_id` FOREIGN KEY (`many_to_one_id`)
                REFERENCES `many_to_one_product` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
        ');

        $this->connection->beginTransaction();
    }

    /**
     * @return array<string, array<EntityWriteResult>>
     */
    private function getClone(string $blogId): array
    {
        return $this->versionManager->clone(
            static::getContainer()->get(BlogDefinition::class),
            $blogId,
            Uuid::randomHex(),
            Uuid::randomHex(),
            WriteContext::createFromContext($this->context),
            new CloneBehavior()
        );
    }
}
