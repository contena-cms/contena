<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\System\User\Service;

use PHPUnit\Framework\TestCase;
use Contena\Core\System\User\UserCollection;
use Contena\Core\System\User\UserEntity;
use Contena\Core\System\User\Service\BlogReviewCountService;
use Contena\Core\Content\Test\Blog\BlogBuilder;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Test\Integration\Builder\User\UserBuilder;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Contena\Core\Test\TestDefaults;

/**
 * @internal
 */
class BlogReviewCountServiceTest extends TestCase
{
    use IntegrationTestBehaviour;

    private IdsCollection $ids;

    private BlogReviewCountService $reviewCountService;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();

        $this->reviewCountService = static::getContainer()->get(BlogReviewCountService::class);
    }

    public function testReviewCountIsUpdatedCorrectly(): void
    {
        $this->createBlog('p1');
        $this->createBlog('p2');

        $this->createUser('c1');
        $createdReviews = [];
        $createdReviews[] = $this->createReview('c1', 'p1', true);
        $createdReviews[] = $this->createReview('c1', 'p2', false);

        $this->createUser('c2');
        $createdReviews[] = $this->createReview('c2', 'p2', true);

        $this->reviewCountService->updateReviewCount($createdReviews);

        $userRepo = static::getContainer()->get('user.repository');
        /** @var UserCollection $users */
        $users = $userRepo->search(new Criteria([$this->ids->get('c1'), $this->ids->get('c2')]), Context::createDefaultContext())->getEntities();

        $firstUser = $users->get($this->ids->get('c1'));
        static::assertInstanceOf(UserEntity::class, $firstUser);
        static::assertSame(1, $firstUser->getReviewCount());

        $secondUser = $users->get($this->ids->get('c2'));
        static::assertInstanceOf(UserEntity::class, $secondUser);
        static::assertSame(1, $secondUser->getReviewCount());
    }

    private function createUser(string $userNumber): void
    {
        $user = (new UserBuilder(
            $this->ids,
            $userNumber
        ))->build();

        $userRepo = static::getContainer()->get('user.repository');
        $userRepo->create([$user], Context::createDefaultContext());
    }

    private function createBlog(string $blogNumber): void
    {
        $blog = new BlogBuilder(
            $this->ids,
            $blogNumber
        );
        $blog->price(100);

        $blogRepo = static::getContainer()->get('blog.repository');
        $blogRepo->create([$blog->build()], Context::createDefaultContext());
    }

    private function createReview(string $userNumber, string $blogNumber, bool $status): string
    {
        $blogReviewRepo = static::getContainer()->get('blog_review.repository');

        $id = Uuid::randomHex();

        $blogReviewRepo->create([
            [
                'id' => $id,
                'userId' => $this->ids->get($userNumber),
                'blogId' => $this->ids->get($blogNumber),
                'status' => $status,
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'channelId' => TestDefaults::CHANNEL,
                'title' => 'foo',
                'content' => 'bar',
                'externalUser' => 'Test',
                'points' => 3,
            ],
        ], Context::createDefaultContext());

        return $id;
    }
}
