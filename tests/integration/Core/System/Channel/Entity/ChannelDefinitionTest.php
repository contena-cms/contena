<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\System\Channel\Entity;

use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Category\CategoryDefinition;
use Contena\Core\Content\Category\Channel\ChannelCategoryDefinition;
use Contena\Core\Content\Blog\Aggregate\BlogVisibility\BlogVisibilityDefinition;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Blog\Channel\ChannelBlogCollection;
use Contena\Core\Content\Blog\Channel\ChannelBlogDefinition;
use Contena\Core\Content\Blog\Channel\ChannelBlogEntity;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Test\TestCaseHelper\CallableClass;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\Context\AbstractChannelContextFactory;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Contena\Core\System\Channel\Entity\ChannelDefinitionInstanceRegistry;
use Contena\Core\System\Channel\Entity\ChannelRepository;
use Contena\Core\System\Channel\ChannelCollection;
use Contena\Core\System\Channel\ChannelEntity;
use Contena\Core\Test\Integration\EventDispatcher\EventHookDispatcher;
use Contena\Core\Test\TestDefaults;

/**
 * @internal
 */
class ChannelDefinitionTest extends TestCase
{
    use IntegrationTestBehaviour;

    private ChannelDefinitionInstanceRegistry $registry;

    /**
     * @var EntityRepository<BlogCollection>
     */
    private EntityRepository $apiRepository;

    /**
     * @var ChannelRepository<ChannelBlogCollection>
     */
    private ChannelRepository $channelBlogRepository;

    private AbstractChannelContextFactory $factory;

    protected function setUp(): void
    {
        $this->registry = static::getContainer()->get(ChannelDefinitionInstanceRegistry::class);
        $this->apiRepository = static::getContainer()->get('blog.repository');
        $this->channelBlogRepository = static::getContainer()->get('channel.blog.repository');
        $this->factory = static::getContainer()->get(ChannelContextFactory::class);
    }

    public function testAssociationReplacement(): void
    {
        $fields = static::getContainer()->get(ChannelBlogDefinition::class)->getFields();

        $categories = $fields->get('categories');

        /** @var ManyToManyAssociationField $categories */
        static::assertSame(
            static::getContainer()->get(ChannelCategoryDefinition::class)->getClass(),
            $categories->getToManyReferenceDefinition()->getClass()
        );

        static::assertSame(
            static::getContainer()->get(ChannelCategoryDefinition::class),
            $categories->getToManyReferenceDefinition()
        );

        $fields = static::getContainer()->get(BlogDefinition::class)->getFields();
        $categories = $fields->get('categories');

        /** @var ManyToManyAssociationField $categories */
        static::assertSame(
            static::getContainer()->get(CategoryDefinition::class),
            $categories->getToManyReferenceDefinition()
        );
    }

    public function testDefinitionRegistry(): void
    {
        static::assertSame(
            static::getContainer()->get(ChannelBlogDefinition::class),
            $this->registry->getByEntityName('blog')
        );
    }

    public function testRepositoryCompilerPass(): void
    {
        static::assertInstanceOf(
            ChannelRepository::class,
            static::getContainer()->get('channel.blog.repository')
        );
    }

    public function testLoadEntities(): void
    {
        $id = Uuid::randomHex();

        $data = [
            'id' => $id,
            'blogNumber' => 'test',
            'stock' => 10,
            'active' => true,
            'name' => 'test',
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
            'manufacturer' => ['name' => 'test'],
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'categories' => [
                ['id' => $id, 'name' => 'asd'],
            ],
            'visibilities' => [
                [
                    'channelId' => TestDefaults::CHANNEL,
                    'visibility' => BlogVisibilityDefinition::VISIBILITY_ALL,
                ],
            ],
        ];

        $this->apiRepository->create([$data], Context::createDefaultContext());

        $listener = $this->createMock(CallableClass::class);
        $listener->expects($this->once())->method('__invoke');
        EventHookDispatcher::fromContainer(static::getContainer())->on('channel.blog.loaded', $listener);

        $context = $this->factory->create(Uuid::randomHex(), TestDefaults::CHANNEL);

        $criteria = new Criteria([$id]);
        $criteria->addAssociation('categories');

        $blogs = $this->channelBlogRepository->search($criteria, $context)->getEntities();

        static::assertCount(1, $blogs);

        /** @var ChannelBlogEntity $blog */
        $blog = $blogs->first();
        static::assertInstanceOf(ChannelBlogEntity::class, $blog);

        $categories = $blog->getCategories();

        static::assertNotNull($categories);
        static::assertCount(1, $categories);
    }

    public function testBusinessTimeZoneCanBeWrittenAndCleared(): void
    {
        $repository = static::getContainer()->get('channel.repository');
        $context = Context::createDefaultContext();
        $originalTimeZone = $this->getBusinessTimeZone($repository, $context);

        try {
            $repository->update([[
                'id' => TestDefaults::CHANNEL,
                'businessTimeZone' => 'Europe/Berlin',
            ]], $context);

            static::assertSame('Europe/Berlin', $this->getBusinessTimeZone($repository, $context));

            $repository->update([[
                'id' => TestDefaults::CHANNEL,
                'businessTimeZone' => null,
            ]], $context);

            static::assertNull($this->getBusinessTimeZone($repository, $context));
        } finally {
            $repository->update([[
                'id' => TestDefaults::CHANNEL,
                'businessTimeZone' => $originalTimeZone,
            ]], $context);
        }
    }

    /**
     * @param EntityRepository<ChannelCollection> $repository
     */
    private function getBusinessTimeZone(EntityRepository $repository, Context $context): ?string
    {
        $channel = $repository->search(new Criteria([TestDefaults::CHANNEL]), $context)->getEntities()->first();
        static::assertInstanceOf(ChannelEntity::class, $channel);

        return $channel->getBusinessTimeZone();
    }
}
