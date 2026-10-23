<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\Mcp\Scenario;

use Doctrine\DBAL\Connection;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Blog\BlogEntity;
use Contena\Core\Content\Test\Blog\BlogBuilder;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Mcp\Context\McpContextProvider;
use Contena\Core\Framework\Mcp\Tool\EntityDeleteTool;
use Contena\Core\Test\Stub\Framework\IdsCollection;

/**
 * Removing a category or a property option from a blog means deleting the row of the
 * mapping entity, not the category or option itself (#20136).
 *
 * @internal
 */
class BlogAssociationRemovalScenarioTest extends McpScenarioTestCase
{
    private EntityDeleteTool $entityDeleteTool;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        parent::setUp();

        $contextProvider = static::createStub(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn(Context::createDefaultContext());

        $this->entityDeleteTool = new EntityDeleteTool(
            static::getContainer()->get(DefinitionInstanceRegistry::class),
            $contextProvider,
            static::getContainer()->get(Connection::class),
        );

        $this->ids = new IdsCollection();
        $blog = (new BlogBuilder($this->ids, 'p1'))
            ->price(10.00)
            ->categories(['keep', 'remove'])
            ->property('red', 'color')
            ->property('blue', 'color')
            ->build();

        static::getContainer()->get('blog.repository')->create([$blog], Context::createDefaultContext());
    }

    public function testSchemaNamesTheMappingEntityOfManyToManyAssociations(): void
    {
        $data = $this->decodeToolOutput(($this->entitySchemaTool)('blog'));
        $associations = array_column($data['data']['associations'], null, 'name');

        static::assertSame('category', $associations['categories']['entity']);
        static::assertSame('blog_category', $associations['categories']['mappingEntity']);
        static::assertSame('property_group_option', $associations['properties']['entity']);
        static::assertSame('blog_property', $associations['properties']['mappingEntity']);
        static::assertArrayNotHasKey('mappingEntity', $associations['manufacturer']);
    }

    public function testRemovesCategoryFromBlogWithoutDeletingTheCategory(): void
    {
        $ids = json_encode([[
            'blogId' => $this->ids->get('p1'),
            'categoryId' => $this->ids->get('remove'),
        ]], \JSON_THROW_ON_ERROR);

        $this->decodeToolOutput(($this->entityDeleteTool)('blog_category', $ids));
        static::assertEqualsCanonicalizing(
            [$this->ids->get('keep'), $this->ids->get('remove')],
            array_values($this->loadBlog()->getCategoryIds() ?? []),
            'A dry run must not remove the link',
        );

        $output = $this->decodeToolOutput(($this->entityDeleteTool)('blog_category', $ids, false));

        static::assertSame([$this->ids->get('keep')], $this->loadBlog()->getCategoryIds());

        // The DAL also reports the blog and the category, whose association changed. The result must
        // not call them deleted: a client reading it, or its dry-run preview, would believe they are gone.
        static::assertIsArray($output['data']);
        $deletedEntities = [];
        foreach ($output['data'] as $row) {
            static::assertIsArray($row);
            if (($row['operation'] ?? null) === 'delete') {
                $deletedEntities[] = $row['entity'] ?? null;
            }
        }
        static::assertSame(['blog_category'], $deletedEntities);
        static::assertSame(
            1,
            static::getContainer()->get('category.repository')->searchIds(new Criteria([$this->ids->get('remove')]), Context::createDefaultContext())->getTotal(),
            'The category itself must still exist',
        );
    }

    public function testRemovesPropertyOptionFromBlog(): void
    {
        $ids = json_encode([[
            'blogId' => $this->ids->get('p1'),
            'optionId' => $this->ids->get('blue'),
        ]], \JSON_THROW_ON_ERROR);

        $this->decodeToolOutput(($this->entityDeleteTool)('blog_property', $ids, false));

        static::assertSame([$this->ids->get('red')], $this->loadBlog()->getPropertyIds());
    }

    public function testRemovingALinkThatDoesNotExistIsReportedAsAFailure(): void
    {
        // #20136: an agent must not be told that a link was removed when there was none.
        $ids = json_encode([[
            'blogId' => $this->ids->get('p1'),
            'categoryId' => $this->ids->get('unrelated'),
        ]], \JSON_THROW_ON_ERROR);

        $error = $this->decodeToolError(($this->entityDeleteTool)('blog_category', $ids, false));

        static::assertStringContainsString('none of these rows exist', $error['error']);
        static::assertCount(2, $this->loadBlog()->getCategoryIds() ?? []);
    }

    public function testExplainsCompositeKeyWhenPlainIdsArePassed(): void
    {
        $error = $this->decodeToolError(($this->entityDeleteTool)('blog_category', json_encode([$this->ids->get('remove')], \JSON_THROW_ON_ERROR), false));

        static::assertStringContainsString('blogId and categoryId', $error['error']);
        static::assertCount(2, $this->loadBlog()->getCategoryIds() ?? []);
    }

    private function loadBlog(): BlogEntity
    {
        /** @var EntityRepository<BlogCollection> $repository */
        $repository = static::getContainer()->get('blog.repository');
        $blog = $repository->search(new Criteria([$this->ids->get('p1')]), Context::createDefaultContext())->getEntities()->first();
        static::assertInstanceOf(BlogEntity::class, $blog);

        return $blog;
    }
}
