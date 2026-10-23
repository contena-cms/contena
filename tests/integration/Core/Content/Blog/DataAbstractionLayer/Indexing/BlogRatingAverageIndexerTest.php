<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\Blog\DataAbstractionLayer\Indexing;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Aggregate\BlogVisibility\BlogVisibilityDefinition;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Blog\BlogEntity;
use Contena\Core\Content\Blog\DataAbstractionLayer\BlogIndexer;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexer;
use Contena\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexingMessage;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Contena\Core\System\User\UserCollection;
use Contena\Core\Test\TestDefaults;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

/**
 * @internal
 */
class BlogRatingAverageIndexerTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepository<BlogReviewCollection>
     */
    private EntityRepository $reviewRepository;

    /**
     * @var EntityRepository<BlogCollection>
     */
    private EntityRepository $blogRepository;

    private ChannelContext $channel;

    /**
     * @var EntityRepository<UserCollection>
     */
    private EntityRepository $userRepository;

    private Connection $connection;

    private EntityIndexer $blogIndexer;

    protected function setUp(): void
    {
        $this->reviewRepository = static::getContainer()->get('blog_review.repository');
        $this->blogRepository = static::getContainer()->get('blog.repository');
        $this->userRepository = static::getContainer()->get('user.repository');
        $this->channel = static::getContainer()->get(ChannelContextFactory::class)->create(Uuid::randomHex(), TestDefaults::CHANNEL);
        $this->connection = static::getContainer()->get(Connection::class);
        $this->blogIndexer = static::getContainer()->get(BlogIndexer::class);
    }

    /**
     * tests that a update of promotion exclusions is written in excluded promotions too
     */
    #[Group('reviews')]
    public function testUpsertReviewIndexerLogic(): void
    {
        $blogId = Uuid::randomHex();
        $reviewAId = Uuid::randomHex();
        $reviewBId = Uuid::randomHex();

        $this->createBlog($blogId);

        $pointsOnAReview = 5.0;
        $pointsOnBReview = 1.0;

        $this->createReview($reviewAId, $pointsOnAReview, $blogId, true);

        $blogs = $this->blogRepository->search(new Criteria([$blogId]), $this->channel->getContext());

        static::assertInstanceOf(BlogEntity::class, $blog = $blogs->getEntities()->get($blogId));
        static::assertSame($pointsOnAReview, $blog->getRatingAverage());

        $expected = ($pointsOnAReview + $pointsOnBReview) / 2;
        $this->createReview($reviewBId, $pointsOnBReview, $blogId, true);
        $blogs = $this->blogRepository->search(new Criteria([$blogId]), $this->channel->getContext());

        static::assertInstanceOf(BlogEntity::class, $blog = $blogs->getEntities()->get($blogId));
        static::assertSame($expected, $blog->getRatingAverage());
    }

    /**
     * tests that a deactivated review is not considered for calculation
     * rating would be 3, but because the reviewA is deactivated only reviewB points will
     * be taken for calculation
     */
    #[Group('reviews')]
    public function testThatDeactivatedReviewsAreNotCalculated(): void
    {
        $blogId = Uuid::randomHex();
        $reviewAId = Uuid::randomHex();
        $reviewBId = Uuid::randomHex();

        $this->createBlog($blogId);

        $pointsOnAReview = 5.0;
        $pointsOnBReview = 1.0;

        $this->createReview($reviewAId, $pointsOnAReview, $blogId, false);
        $this->createReview($reviewBId, $pointsOnBReview, $blogId, true);

        $blogs = $this->blogRepository->search(new Criteria([$blogId]), $this->channel->getContext());

        static::assertInstanceOf(BlogEntity::class, $blog = $blogs->getEntities()->get($blogId));
        static::assertSame($pointsOnBReview, $blog->getRatingAverage());
    }

    #[Group('reviews')]
    public function testReviewsWithoutRatingAreNotCalculated(): void
    {
        $blogId = Uuid::randomHex();

        $this->createBlog($blogId);

        $this->createReviewWithoutRating($blogId);
        $this->createReview(Uuid::randomHex(), 4.0, $blogId, true);

        $blogs = $this->blogRepository->search(new Criteria([$blogId]), $this->channel->getContext());

        static::assertInstanceOf(BlogEntity::class, $blog = $blogs->getEntities()->get($blogId));
        static::assertSame(4.0, $blog->getRatingAverage());
    }

    #[Group('reviews')]
    public function testBlogWithOnlyReviewsWithoutRatingHasNoAverage(): void
    {
        $blogId = Uuid::randomHex();

        $this->createBlog($blogId);

        $this->createReviewWithoutRating($blogId);

        $blogs = $this->blogRepository->search(new Criteria([$blogId]), $this->channel->getContext());

        static::assertInstanceOf(BlogEntity::class, $blog = $blogs->getEntities()->get($blogId));
        static::assertNull($blog->getRatingAverage());
    }

    /**
     * tests that a deactivating/activating reviews are considered correctly
     */
    #[Group('reviews')]
    public function testThatUpdatingReviewsTriggerCalculationProcessCorrectly(): void
    {
        $blogId = Uuid::randomHex();
        $reviewAId = Uuid::randomHex();
        $reviewBId = Uuid::randomHex();

        $this->createBlog($blogId);

        $pointsOnAReview = 5.0;
        $pointsOnBReview = 1.0;

        $this->createReview($reviewAId, $pointsOnAReview, $blogId, false);
        $this->createReview($reviewBId, $pointsOnBReview, $blogId, true);

        $blogs = $this->blogRepository->search(new Criteria([$blogId]), $this->channel->getContext());

        static::assertInstanceOf(BlogEntity::class, $blog = $blogs->getEntities()->get($blogId));
        static::assertSame($pointsOnBReview, $blog->getRatingAverage());

        $this->updateReview([['id' => $reviewAId, 'status' => true]]);

        $blogs = $this->blogRepository->search(new Criteria([$blogId]), $this->channel->getContext());

        $expected = ($pointsOnAReview + $pointsOnBReview) / 2;

        static::assertInstanceOf(BlogEntity::class, $blog = $blogs->getEntities()->get($blogId));
        static::assertSame($expected, $blog->getRatingAverage());
    }

    /**
     * tests that a multi save reviews are considered correctly
     */
    #[Group('reviews')]
    public function testMultiReviewsSaveProcess(): void
    {
        $blogAId = Uuid::randomHex();
        $blogBId = Uuid::randomHex();
        $reviewAId = Uuid::randomHex();
        $reviewBId = Uuid::randomHex();
        $reviewCId = Uuid::randomHex();

        $this->createBlog($blogAId);
        $this->createBlog($blogBId);

        $pointsOnAReview = 5.0;
        $pointsOnBReview = 5.0;
        $pointsOnCReview = 2.0;

        $this->createReview($reviewAId, $pointsOnAReview, $blogAId, false);
        $this->createReview($reviewBId, $pointsOnBReview, $blogAId, false);
        $this->createReview($reviewCId, $pointsOnCReview, $blogAId, true);

        $blogs = $this->blogRepository->search(new Criteria([$blogAId, $blogBId]), $this->channel->getContext());

        static::assertInstanceOf(BlogEntity::class, $blogA = $blogs->getEntities()->get($blogAId));
        static::assertInstanceOf(BlogEntity::class, $blogB = $blogs->getEntities()->get($blogBId));

        static::assertSame(2.0, $blogA->getRatingAverage());
        static::assertNull($blogB->getRatingAverage());

        $this->updateReview([['id' => $reviewAId, 'status' => true], ['id' => $reviewBId, 'status' => true], ['id' => $reviewCId, 'blogId' => $blogBId, 'status' => true]]);
        $blogs = $this->blogRepository->search(new Criteria([$blogAId, $blogBId]), $this->channel->getContext());

        static::assertInstanceOf(BlogEntity::class, $blogA = $blogs->getEntities()->get($blogAId));
        static::assertInstanceOf(BlogEntity::class, $blogB = $blogs->getEntities()->get($blogBId));
        static::assertSame(5.0, $blogA->getRatingAverage());
        static::assertSame(2.0, $blogB->getRatingAverage());
    }

    /**
     * tests that deactivating blog reviews result in correct review score, even if no review is active (=>0)
     */
    #[Group('reviews')]
    public function testCalculateWhenSwitchingReviewStatus(): void
    {
        $blogAId = Uuid::randomHex();
        $reviewAId = Uuid::randomHex();
        $reviewBId = Uuid::randomHex();

        $this->createBlog($blogAId);

        $pointsOnAReview = 5.0;
        $pointsOnBReview = 2.0;

        $this->createReview($reviewAId, $pointsOnAReview, $blogAId, true);
        $this->createReview($reviewBId, $pointsOnBReview, $blogAId, true);

        $blogs = $this->blogRepository->search(new Criteria([$blogAId]), $this->channel->getContext());

        static::assertInstanceOf(BlogEntity::class, $blogA = $blogs->getEntities()->get($blogAId));
        static::assertSame(3.5, $blogA->getRatingAverage());

        $this->updateReview([['id' => $reviewAId, 'status' => false]]);
        $blogs = $this->blogRepository->search(new Criteria([$blogAId]), $this->channel->getContext());
        static::assertInstanceOf(BlogEntity::class, $blogA = $blogs->getEntities()->get($blogAId));
        static::assertSame(2.0, $blogA->getRatingAverage());

        $this->updateReview([['id' => $reviewBId, 'status' => false]]);
        $blogs = $this->blogRepository->search(new Criteria([$blogAId]), $this->channel->getContext());
        static::assertInstanceOf(BlogEntity::class, $blogA = $blogs->getEntities()->get($blogAId));
        static::assertNull($blogA->getRatingAverage());
    }

    /**
     * tests that deactivating blog reviews result in correct review score, even if no review is active (=>0)
     */
    #[Group('reviews')]
    public function testCalculateWhenDeletingReviews(): void
    {
        $blogAId = Uuid::randomHex();
        $reviewAId = Uuid::randomHex();
        $reviewBId = Uuid::randomHex();

        $this->createBlog($blogAId);

        $pointsOnAReview = 5.0;
        $pointsOnBReview = 2.0;

        $this->createReview($reviewAId, $pointsOnAReview, $blogAId, true);
        $this->createReview($reviewBId, $pointsOnBReview, $blogAId, true);

        $blogs = $this->blogRepository->search(new Criteria([$blogAId]), $this->channel->getContext());

        static::assertInstanceOf(BlogEntity::class, $blogA = $blogs->getEntities()->get($blogAId));
        static::assertSame(3.5, $blogA->getRatingAverage());

        $this->deleteReview([['id' => $reviewAId]]);
        $blogs = $this->blogRepository->search(new Criteria([$blogAId]), $this->channel->getContext());

        static::assertInstanceOf(BlogEntity::class, $blogA = $blogs->getEntities()->get($blogAId));
        static::assertSame(2.0, $blogA->getRatingAverage());
    }

    /**
     * tests that reviews on both parent and variant blog are averaged correctly
     */
    #[Group('reviews')]
    public function testRatingAverageIsCorrectWhenReviewsExistOnParentAndVariant(): void
    {
        $parentId = Uuid::randomHex();
        $variantId = Uuid::randomHex();

        $this->createBlog($parentId);

        $this->blogRepository->create(
            [
                [
                    'id' => $variantId,
                    'blogNumber' => $variantId,
                    'stock' => 1,
                    'active' => true,
                    'type' => BlogDefinition::TYPE_PHYSICAL,
                    'parentId' => $parentId,
                ],
            ],
            $this->channel->getContext()
        );

        $this->createReview(Uuid::randomHex(), 5.0, $parentId, true);
        $this->createReview(Uuid::randomHex(), 4.0, $variantId, true);

        $blogs = $this->blogRepository->search(new Criteria([$parentId]), $this->channel->getContext());

        static::assertInstanceOf(BlogEntity::class, $blog = $blogs->getEntities()->get($parentId));
        static::assertSame(4.5, $blog->getRatingAverage());
    }

    /**
     * tests that the full index works
     */
    #[Group('reviews')]
    public function testFullIndex(): void
    {
        $blogId = Uuid::randomHex();
        $reviewAId = Uuid::randomHex();
        $reviewBId = Uuid::randomHex();

        $this->createBlog($blogId);

        $pointsOnAReview = 5.0;
        $pointsOnBReview = 1.0;

        $this->createReview($reviewAId, $pointsOnAReview, $blogId, true);
        $this->createReview($reviewBId, $pointsOnBReview, $blogId, true);

        $sql = <<<'SQL'
            UPDATE blog SET blog.rating_average = 0;
SQL;
        $this->connection->executeStatement($sql);

        $blogs = $this->blogRepository->search(new Criteria([$blogId]), $this->channel->getContext());
        static::assertInstanceOf(BlogEntity::class, $blog = $blogs->getEntities()->get($blogId));
        static::assertSame(0.0, $blog->getRatingAverage());

        $this->blogIndexer->handle(new EntityIndexingMessage([$blogId]));
        $blogs = $this->blogRepository->search(new Criteria([$blogId]), $this->channel->getContext());

        static::assertInstanceOf(BlogEntity::class, $blog = $blogs->getEntities()->get($blogId));
        static::assertSame(3.0, $blog->getRatingAverage());
    }

    /**
     * update data in review repository
     *
     * @param array<int, array<string, mixed>> $data
     */
    private function updateReview(array $data): void
    {
        $this->reviewRepository->upsert($data, $this->channel->getContext());
    }

    /**
     * delete data in review repository
     *
     * @param array<int, array<string, mixed>> $data
     */
    private function deleteReview(array $data): void
    {
        $this->reviewRepository->delete($data, $this->channel->getContext());
    }

    /**
     * creates a review in database
     */
    private function createReview(string $id, float $points, string $blogId, bool $active): void
    {
        $userId = Uuid::randomHex();
        $this->createUser($userId);
        $channelId = $this->channel->getChannelId();
        $languageId = Defaults::LANGUAGE_SYSTEM;
        $title = 'foo';

        $data = [
            'id' => $id,
            'blogId' => $blogId,
            'userId' => $userId,
            'channelId' => $channelId,
            'languageId' => $languageId,
            'status' => $active,
            'points' => $points,
            'content' => 'Lorem',
            'title' => $title,
            'externalUser' => 'Test',
        ];

        $this->reviewRepository->upsert([$data], $this->channel->getContext());
    }

    /**
     * creates a review in database that has no rating
     */
    private function createReviewWithoutRating(string $blogId): void
    {
        $id = Uuid::randomHex();
        $this->createReview($id, 1.0, $blogId, true);

        $this->connection->executeStatement(
            'UPDATE blog_review SET points = 0 WHERE id = :id',
            ['id' => Uuid::fromHexToBytes($id)]
        );

        $this->blogIndexer->handle(new EntityIndexingMessage([$blogId]));
    }

    /**
     * Creates a new blog in the database.
     */
    private function createBlog(string $blogId): void
    {
        $this->blogRepository->create(
            [
                [
                    'id' => $blogId,
                    'blogNumber' => $blogId,
                    'stock' => 1,
                    'name' => 'Test',
                    'active' => true,
                    'price' => [
                        [
                            'currencyId' => Defaults::CURRENCY,
                            'gross' => 100,
                            'net' => 9, 'linked' => false,
                        ],
                    ],
                    'manufacturer' => ['name' => 'test'],
                    'tax' => ['taxRate' => 19, 'name' => 'with id'],
                    'visibilities' => [
                        ['channelId' => $this->channel->getChannelId(), 'visibility' => BlogVisibilityDefinition::VISIBILITY_ALL],
                    ],
                    'categories' => [
                        ['id' => Uuid::randomHex(), 'name' => 'Clothing'],
                    ],
                ],
            ],
            $this->channel->getContext()
        );
    }

    private function createUser(string $userID): void
    {
        $addressId = Uuid::randomHex();

        $user = [
            'id' => $userID,
            'channelId' => TestDefaults::CHANNEL,
            'defaultShippingAddress' => [
                'id' => $addressId,
                'name' => 'Max',
                'lastName' => 'Mustermann',
                'street' => 'Musterstraße 1',
                'city' => 'Schoöppingen',
                'zipcode' => '12345',
                'salutationId' => $this->getValidSalutationId(),
                'countryId' => $this->getValidCountryId(),
            ],
            'defaultBillingAddressId' => $addressId,
            'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
            'email' => $userID . '@example.com',
            'password' => TestDefaults::HASHED_PASSWORD,
            'name' => 'Max',
            'lastName' => 'Mustermann',
            'salutationId' => $this->getValidSalutationId(),
            'userNumber' => $userID,
        ];

        $this->userRepository->create([$user], Context::createDefaultContext());
    }
}
