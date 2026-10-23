<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\System\User\Subscriber;

use PHPUnit\Framework\TestCase;
use Contena\Core\System\User\UserCollection;
use Contena\Core\System\User\UserEntity;
use Contena\Core\Content\Blog\Aggregate\BlogReview\BlogReviewCollection;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Test\Blog\BlogBuilder;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Test\Integration\Builder\User\UserBuilder;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Contena\Core\Test\TestDefaults;

/**
 * @internal
 */
class BlogReviewSubscriberTest extends TestCase
{
    use IntegrationTestBehaviour;

    private IdsCollection $ids;

    /**
     * @var EntityRepository<BlogReviewCollection>
     */
    private EntityRepository $blogReviewRepository;

    /**
     * @var EntityRepository<UserCollection>
     */
    private EntityRepository $userRepository;

    /**
     * @var EntityRepository<BlogCollection>
     */
    private EntityRepository $blogRepository;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();

        /** @var EntityRepository<BlogReviewCollection> $blogReviewRepository */
        $blogReviewRepository = static::getContainer()->get('blog_review.repository');
        $this->blogReviewRepository = $blogReviewRepository;

        /** @var EntityRepository<UserCollection> $userRepository */
        $userRepository = static::getContainer()->get('user.repository');
        $this->userRepository = $userRepository;

        /** @var EntityRepository<BlogCollection> $blogRepository */
        $blogRepository = static::getContainer()->get('blog.repository');
        $this->blogRepository = $blogRepository;

        $this->createUser();
        $this->createBlog();
    }

    public function testCreatingNewReview(): void
    {
        $this->createReviews();

        $user = $this->userRepository->search(
            new Criteria([$this->ids->get('user')]),
            Context::createDefaultContext()
        )->getEntities()->first();
        static::assertInstanceOf(UserEntity::class, $user);
        static::assertSame(1, $user->getReviewCount());
    }

    public function testDeletingNewReview(): void
    {
        $this->createReviews();

        $user = $this->userRepository->search(
            new Criteria([$this->ids->get('user')]),
            Context::createDefaultContext()
        )->getEntities()->first();
        static::assertInstanceOf(UserEntity::class, $user);
        static::assertSame(1, $user->getReviewCount());

        $this->blogReviewRepository->delete([['id' => $this->ids->get('review')], ['id' => $this->ids->get('review-2')]], Context::createDefaultContext());

        $user = $this->userRepository->search(
            new Criteria([$this->ids->get('user')]),
            Context::createDefaultContext()
        )->getEntities()->first();
        static::assertInstanceOf(UserEntity::class, $user);
        static::assertSame(0, $user->getReviewCount());
    }

    public function testUpdateReviews(): void
    {
        $this->createReviews();

        $user = $this->userRepository->search(
            new Criteria([$this->ids->get('user')]),
            Context::createDefaultContext()
        )->getEntities()->first();
        static::assertInstanceOf(UserEntity::class, $user);
        static::assertSame(1, $user->getReviewCount());

        $this->blogReviewRepository->update([
            [
                'id' => $this->ids->get('review'),
                'content' => 'foo',
            ],
        ], Context::createDefaultContext());

        $user = $this->userRepository->search(
            new Criteria([$this->ids->get('user')]),
            Context::createDefaultContext()
        )->getEntities()->first();
        static::assertInstanceOf(UserEntity::class, $user);
        static::assertSame(1, $user->getReviewCount());

        $this->blogReviewRepository->update([
            [
                'id' => $this->ids->get('review-2'),
                'status' => true,
            ],
        ], Context::createDefaultContext());

        $user = $this->userRepository->search(
            new Criteria([$this->ids->get('user')]),
            Context::createDefaultContext()
        )->getEntities()->first();
        static::assertInstanceOf(UserEntity::class, $user);
        static::assertSame(2, $user->getReviewCount());
    }

    private function createBlog(): void
    {
        $builder = new BlogBuilder($this->ids, 'blog');
        $builder->price(10);

        $this->blogRepository->create([$builder->build()], Context::createDefaultContext());
    }

    private function createUser(): void
    {
        $builder = new UserBuilder($this->ids, 'user');

        $this->userRepository->create([$builder->build()], Context::createDefaultContext());
    }

    private function createReviews(): void
    {
        $this->blogReviewRepository->create([
            [
                'id' => $this->ids->create('review'),
                'blogId' => $this->ids->get('blog'),
                'userId' => $this->ids->get('user'),
                'channelId' => TestDefaults::CHANNEL,
                'title' => 'fooo',
                'content' => 'baar',
                'points' => 5,
                'externalUser' => 'Test',
                'status' => true,
            ],
            [
                'id' => $this->ids->create('review-2'),
                'blogId' => $this->ids->get('blog'),
                'userId' => $this->ids->get('user'),
                'channelId' => TestDefaults::CHANNEL,
                'title' => 'fooo',
                'content' => 'baar',
                'points' => 5,
                'externalUser' => 'Test',
                'status' => false,
            ],
        ], Context::createDefaultContext());
    }
}
