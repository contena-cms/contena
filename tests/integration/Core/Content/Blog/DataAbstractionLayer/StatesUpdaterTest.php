<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\Blog\DataAbstractionLayer;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Blog\BlogEntity;
use Contena\Core\Content\Blog\DataAbstractionLayer\BlogIndexer;
use Contena\Core\Content\Blog\DataAbstractionLayer\StatesUpdater;
use Contena\Core\Content\Blog\State;
use Contena\Core\Content\Test\Blog\BlogBuilder;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexerRegistry;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Struct\ArrayEntity;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
class StatesUpdaterTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepository<BlogCollection>
     */
    private EntityRepository $blogRepository;

    private StatesUpdater $statesUpdater;

    private Connection $connection;

    protected function setUp(): void
    {
        Feature::skipTestIfActive('v6.8.0.0', $this);

        $this->blogRepository = static::getContainer()->get('blog.repository');
        $this->statesUpdater = static::getContainer()->get(StatesUpdater::class);
        $this->connection = static::getContainer()->get(Connection::class);
    }

    public function testUpdateBlogStates(): void
    {
        $ids = new IdsCollection();
        $this->prepareBlogs($ids);

        $blogIds = $ids->prefixed('blog-');
        $this->statesUpdater->update($blogIds, Context::createDefaultContext());

        $blogs = $this->blogRepository
            ->search(new Criteria($blogIds), Context::createDefaultContext())
            ->getEntities();

        $blog1 = $blogs->get($ids->get('blog-1'));
        $blog2 = $blogs->get($ids->get('blog-2'));
        $blog3 = $blogs->get($ids->get('blog-3'));

        static::assertInstanceOf(BlogEntity::class, $blog1);
        static::assertInstanceOf(BlogEntity::class, $blog2);
        static::assertInstanceOf(BlogEntity::class, $blog3);
        static::assertSame([State::IS_DOWNLOAD], $blog1->getStates());
        static::assertSame([State::IS_PHYSICAL], $blog2->getStates());
        static::assertSame([State::IS_PHYSICAL], $blog3->getStates());

        static::assertSame(BlogDefinition::TYPE_DIGITAL, $blog1->getType());
        static::assertSame(BlogDefinition::TYPE_PHYSICAL, $blog2->getType());
        static::assertSame(BlogDefinition::TYPE_PHYSICAL, $blog3->getType());
    }

    public function prepareBlogs(IdsCollection $ids): void
    {
        $blogs = [
            (new BlogBuilder($ids, 'blog-1'))
                ->price(1.0)
                ->add('downloads', [
                    [
                        'media' => [
                            'fileName' => 'foo',
                            'fileExtension' => 'png',
                            'private' => true,
                        ],
                    ],
                ])
                ->build(),
            (new BlogBuilder($ids, 'blog-2'))
                ->price(1.0)
                ->build(),
            (new BlogBuilder($ids, 'blog-3'))
                ->price(1.0)
                ->build(),
        ];

        $context = Context::createDefaultContext();
        $context->addExtension(EntityIndexerRegistry::EXTENSION_INDEXER_SKIP, new ArrayEntity(['skips' => [BlogIndexer::STATES_UPDATER]]));

        $this->blogRepository->create($blogs, $context);

        $this->connection->executeStatement(
            'UPDATE `blog` SET `states` = :states WHERE `id` IN (:ids)',
            [
                'states' => json_encode([State::IS_PHYSICAL]),
                'ids' => Uuid::fromHexToBytesList([$ids->get('blog-1'), $ids->get('blog-2')]),
            ],
            ['ids' => ArrayParameterType::BINARY]
        );
    }
}
