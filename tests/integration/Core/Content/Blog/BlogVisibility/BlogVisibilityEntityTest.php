<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\Blog\BlogVisibility;

use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Aggregate\BlogVisibility\BlogVisibilityCollection;
use Contena\Core\Content\Blog\Aggregate\BlogVisibility\BlogVisibilityDefinition;
use Contena\Core\Content\Blog\Aggregate\BlogVisibility\BlogVisibilityEntity;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Blog\BlogEntity;
use Contena\Core\Defaults;
use Contena\Core\Framework\Api\Util\AccessKeyHelper;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Test\TestDefaults;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

/**
 * @internal
 */
class BlogVisibilityEntityTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepository<BlogCollection>
     */
    protected EntityRepository $blogRepository;

    private string $channelId1;

    private string $channelId2;

    /**
     * @var EntityRepository<BlogVisibilityCollection>
     */
    private EntityRepository $visibilityRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->blogRepository = static::getContainer()->get('blog.repository');
        $this->visibilityRepository = static::getContainer()->get('blog_visibility.repository');

        $this->channelId1 = Uuid::randomHex();
        $this->channelId2 = Uuid::randomHex();

        $this->createChannel($this->channelId1);
        $this->createChannel($this->channelId2);
    }

    public function testVisibilityCRUD(): void
    {
        $id = Uuid::randomHex();

        $blog = $this->createBlog(
            $id,
            [
                $this->channelId1 => BlogVisibilityDefinition::VISIBILITY_SEARCH,
                $this->channelId2 => BlogVisibilityDefinition::VISIBILITY_LINK,
            ]
        );

        $context = Context::createDefaultContext();

        $container = $this->blogRepository->create([$blog], $context);

        $event = $container->getEventByEntityName(BlogVisibilityDefinition::ENTITY_NAME);

        // visibility created?
        static::assertInstanceOf(EntityWrittenEvent::class, $event);
        static::assertCount(2, $event->getWriteResults());

        $criteria = new Criteria([$id]);
        $criteria->addAssociation('visibilities');

        $blog = $this->blogRepository->search($criteria, $context)->getEntities()->first();

        // check visibilities can be loaded as association
        static::assertInstanceOf(BlogEntity::class, $blog);
        static::assertInstanceOf(BlogVisibilityCollection::class, $blog->getVisibilities());
        static::assertCount(2, $blog->getVisibilities());

        // check read for visibilities
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('blog_visibility.blogId', $id));

        $visibilities = $this->visibilityRepository->search($criteria, $context)->getEntities();
        static::assertCount(2, $visibilities);

        // test filter visibilities over blog
        $criteria = new Criteria([$id]);

        $criteria->addFilter(
            new MultiFilter(
                MultiFilter::CONNECTION_AND,
                [
                    new EqualsFilter('blog.visibilities.channelId', $this->channelId1),
                ]
            )
        );

        $blog = $this->blogRepository->search($criteria, $context)->getEntities()->first();

        // visibilities filtered and loaded?
        static::assertInstanceOf(BlogEntity::class, $blog);

        $ids = $visibilities->map(
            static fn (BlogVisibilityEntity $visibility) => ['id' => $visibility->getId()]
        );

        $container = $this->visibilityRepository->delete(array_values($ids), $context);

        $event = $container->getEventByEntityName(BlogVisibilityDefinition::ENTITY_NAME);
        static::assertInstanceOf(EntityWrittenEvent::class, $event);
        static::assertCount(2, $event->getWriteResults());
    }

    /**
     * @param array<string, int> $visibilities
     *
     * @return array<string, mixed>
     */
    private function createBlog(string $id, array $visibilities): array
    {
        $mapped = [];
        foreach ($visibilities as $channel => $visibility) {
            $mapped[] = ['channelId' => $channel, 'visibility' => $visibility];
        }

        return [
            'id' => $id,
            'blogNumber' => Uuid::randomHex(),
            'stock' => 1,
            'name' => 'test',
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
            'manufacturer' => ['name' => 'test'],
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'visibilities' => $mapped,
        ];
    }

    private function createChannel(string $id): void
    {
        $data = [
            'id' => $id,
            'accessKey' => AccessKeyHelper::generateAccessKey('sales-channel'),
            'typeId' => Defaults::CHANNEL_TYPE_API,
            'languageId' => Defaults::LANGUAGE_SYSTEM,
            'currencyId' => Defaults::CURRENCY,
            'currencyVersionId' => Defaults::LIVE_VERSION,
            'paymentMethodId' => $this->getValidPaymentMethodId(),
            'paymentMethodVersionId' => Defaults::LIVE_VERSION,
            'shippingMethodId' => $this->getValidShippingMethodId(),
            'shippingMethodVersionId' => Defaults::LIVE_VERSION,
            'navigationCategoryId' => $this->getValidCategoryId(),
            'navigationCategoryVersionId' => Defaults::LIVE_VERSION,
            'countryId' => $this->getValidCountryId(),
            'countryVersionId' => Defaults::LIVE_VERSION,
            'currencies' => [['id' => Defaults::CURRENCY]],
            'languages' => [['id' => Defaults::LANGUAGE_SYSTEM]],
            'shippingMethods' => [['id' => $this->getValidShippingMethodId()]],
            'paymentMethods' => [['id' => $this->getValidPaymentMethodId()]],
            'countries' => [['id' => $this->getValidCountryId()]],
            'name' => 'first sales-channel',
            'userGroupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
        ];

        static::getContainer()->get('channel.repository')->create([$data], Context::createDefaultContext());
    }
}
