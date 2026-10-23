<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\System\User\Channel;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Contena\Core\System\User\Aggregate\UserWishlistBlog\UserWishlistBlogCollection;
use Contena\Core\Content\Blog\Aggregate\BlogVisibility\BlogVisibilityDefinition;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Routing\RoutingException;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Util\Random;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\PlatformRequest;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Core\Test\Integration\Traits\UserTestTrait;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * @internal
 */
#[Group('store-api')]
class MergeWishlistBlogRouteTest extends TestCase
{
    use UserTestTrait;
    use IntegrationTestBehaviour;

    private KernelBrowser $browser;

    private IdsCollection $ids;

    private Context $context;

    private string $userId;

    private SystemConfigService $systemConfigService;

    /**
     * @var EntityRepository<UserWishlistBlogCollection>
     */
    private EntityRepository $wishlistBlogRepository;

    protected function setUp(): void
    {
        $this->context = Context::createDefaultContext();
        $this->ids = new IdsCollection();

        $this->browser = $this->createCustomChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
        ]);
        $this->assignChannelContext($this->browser);

        $this->wishlistBlogRepository = static::getContainer()->get('user_wishlist_product.repository');

        $email = Uuid::randomHex() . '@example.com';
        $this->userId = $this->createUser($email);

        $this->systemConfigService = static::getContainer()->get(SystemConfigService::class);
        $this->systemConfigService->set('core.cart.wishlistEnabled', true);

        $this->browser
            ->request(
                'POST',
                '/store-api/account/login',
                [
                    'email' => $email,
                    'password' => 'contena',
                ]
            );

        $response = $this->browser->getResponse();

        // After login successfully, the context token will be set in the header
        $contextToken = $response->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN) ?? '';
        static::assertNotSame('', $contextToken);

        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $contextToken);
    }

    public function testMergeBlogShouldReturnSuccessNoWishlistExisted(): void
    {
        $blogData = $this->createBlog($this->context);

        $this->browser
            ->request(
                'POST',
                '/store-api/user/wishlist/merge',
                [
                    'blogIds' => [
                        'id' => $blogData,
                    ],
                ]
            );
        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame(200, $this->browser->getResponse()->getStatusCode());
        static::assertTrue($response['success']);

        $wishlistBlog = $this->wishlistBlogRepository->search(new Criteria(), $this->context)->getEntities();
        static::assertSame($blogData, $wishlistBlog->first()?->getBlogId());
    }

    public function testMergeTwoBlogShouldReturnSuccessNoWishlistExisted(): void
    {
        $blogDataOne = $this->createBlog($this->context);
        $blogDataTwo = $this->createBlog($this->context);

        $this->browser
            ->request(
                'POST',
                '/store-api/user/wishlist/merge',
                [
                    'blogIds' => [
                        $blogDataOne, $blogDataTwo,
                    ],
                ]
            );
        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(200, $this->browser->getResponse()->getStatusCode());
        static::assertTrue($response['success']);

        $wishlistBlog = $this->wishlistBlogRepository->search(new Criteria(), $this->context)->getEntities();
        static::assertCount(2, $wishlistBlog);
    }

    public function testMergeThreeBlogShouldReturnSuccessNoWishlistExisted(): void
    {
        $blogDataOne = $this->createBlog($this->context);
        $blogDataTwo = $this->createBlog($this->context);

        $this->browser
            ->request(
                'POST',
                '/store-api/user/wishlist/merge',
                [
                    'blogIds' => [
                        $blogDataOne,
                        $blogDataTwo,
                        Uuid::randomHex(),
                    ],
                ]
            );
        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame(200, $this->browser->getResponse()->getStatusCode());
        static::assertTrue($response['success']);

        $wishlistBlog = $this->wishlistBlogRepository->search(new Criteria(), $this->context)->getEntities();
        static::assertCount(2, $wishlistBlog);
    }

    public function testMergeBlogShouldThrowUserNotLoggedInException(): void
    {
        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', Random::getAlphanumericString(12));

        $this->browser
            ->request(
                'POST',
                '/store-api/user/wishlist/merge',
                [
                    'blogIds' => [
                        'id' => Uuid::randomHex(),
                    ],
                ]
            );
        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $errors = $response['errors'][0];
        static::assertSame(403, $this->browser->getResponse()->getStatusCode());
        static::assertSame(RoutingException::CUSTOMER_NOT_LOGGED_IN_CODE, $errors['code']);
        static::assertSame('Forbidden', $errors['title']);
        static::assertSame('User is not logged in.', $errors['detail']);

        $wishlistBlog = $this->wishlistBlogRepository->search(new Criteria(), $this->context)->getEntities();
        static::assertNull($wishlistBlog->first());
    }

    public function testMergeBlogShouldThrowUserWishlistNotActivatedException(): void
    {
        $this->systemConfigService->set('core.cart.wishlistEnabled', false);

        $this->browser
            ->request(
                'POST',
                '/store-api/user/wishlist/merge',
                [
                    'blogIds' => [
                        'id' => Uuid::randomHex(),
                    ],
                ]
            );
        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $errors = $response['errors'][0];
        static::assertSame(403, $this->browser->getResponse()->getStatusCode());
        static::assertSame('CHECKOUT__WISHLIST_IS_NOT_ACTIVATED', $errors['code']);
        static::assertSame('Forbidden', $errors['title']);
        static::assertSame('Wishlist is not activated!', $errors['detail']);

        $wishlistBlog = $this->wishlistBlogRepository->search(new Criteria(), $this->context)->getEntities();
        static::assertNull($wishlistBlog->first());
    }

    public function testMergeBlogShouldSuccessWithNoBlogInsert(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/user/wishlist/merge',
                [
                    'blogIds' => [
                        'id' => Uuid::randomHex(),
                    ],
                ]
            );
        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame(200, $this->browser->getResponse()->getStatusCode());
        static::assertTrue($response['success']);

        $wishlistBlog = $this->wishlistBlogRepository->search(new Criteria(), $this->context)->getEntities();
        static::assertNull($wishlistBlog->first());
    }

    public function testMergeBlogShouldReturnSuccessAlreadyWishlistExisted(): void
    {
        $blogData = $this->createBlog($this->context);
        $this->createUserWishlist($blogData);

        $this->browser
            ->request(
                'POST',
                '/store-api/user/wishlist/merge',
                [
                    'blogIds' => [
                        'id' => $blogData,
                    ],
                ]
            );
        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(200, $this->browser->getResponse()->getStatusCode());
        static::assertTrue($response['success']);

        $wishlistBlog = $this->wishlistBlogRepository->search(new Criteria(), $this->context)->getEntities();
        static::assertSame($blogData, $wishlistBlog->first()?->getBlogId());
    }

    public function testMergeBlogShouldReturnSuccessAlreadyBlogWishlistExisted(): void
    {
        $alreadyBlogData = $this->createBlog($this->context);
        $this->createUserWishlist($alreadyBlogData);
        $newBlogData = $this->createBlog($this->context);

        $this->browser
            ->request(
                'POST',
                '/store-api/user/wishlist/merge',
                [
                    'blogIds' => [
                        'id' => $newBlogData,
                    ],
                ]
            );
        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame(200, $this->browser->getResponse()->getStatusCode());
        static::assertTrue($response['success']);

        $wishlistBlog = $this->wishlistBlogRepository->search(new Criteria(), $this->context)->getEntities();
        static::assertCount(2, $wishlistBlog);
    }

    public function testMergeBlogShouldReturnSuccessSameBlogWishlistExisted(): void
    {
        $alreadyBlogData = $this->createBlog($this->context);
        $this->createUserWishlist($alreadyBlogData);

        $this->browser
            ->request(
                'POST',
                '/store-api/user/wishlist/merge',
                [
                    'blogIds' => [
                        'id' => $alreadyBlogData,
                    ],
                ]
            );
        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame(200, $this->browser->getResponse()->getStatusCode());
        static::assertTrue($response['success']);

        $wishlistBlog = $this->wishlistBlogRepository->search(new Criteria(), $this->context)->getEntities();
        static::assertCount(1, $wishlistBlog);
        static::assertSame($alreadyBlogData, $wishlistBlog->first()?->getBlogId());
    }

    public function testMergeBlogsWithEmptyWishlistAndEmptyMergeRequest(): void
    {
        $this->createUserWishlist();

        $this->browser
            ->request(
                'POST',
                '/store-api/user/wishlist/merge',
                [
                    'blogIds' => [],
                ]
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame(200, $this->browser->getResponse()->getStatusCode());
        static::assertTrue($response['success']);

        $wishlistBlog = $this->wishlistBlogRepository->search(new Criteria(), $this->context)->getEntities();
        static::assertCount(0, $wishlistBlog);
    }

    public function testMergeBlogsWithNonEmptyWishlistAndEmptyMergeRequest(): void
    {
        $blogData = $this->createBlog($this->context);
        $this->createUserWishlist($blogData);

        $this->browser
            ->request(
                'POST',
                '/store-api/user/wishlist/merge',
                [
                    'blogIds' => [],
                ]
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame(200, $this->browser->getResponse()->getStatusCode());
        static::assertTrue($response['success']);

        $wishlistBlog = $this->wishlistBlogRepository->search(new Criteria(), $this->context)->getEntities();
        static::assertCount(1, $wishlistBlog);
    }

    private function createBlog(Context $context): string
    {
        $blogId = Uuid::randomHex();
        $data = [
            'id' => $blogId,
            'blogNumber' => Uuid::randomHex(),
            'stock' => 1,
            'name' => 'Test Blog',
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10.99, 'net' => 11.99, 'linked' => false]],
            'manufacturer' => ['name' => 'create'],
            'taxId' => $this->getValidTaxId(),
            'active' => true,
            'visibilities' => [
                ['channelId' => $this->getChannelApiChannelId(), 'visibility' => BlogVisibilityDefinition::VISIBILITY_ALL],
            ],
        ];

        static::getContainer()->get('blog.repository')->create([$data], $context);

        return $blogId;
    }

    private function createUserWishlist(?string $blogId = null): string
    {
        $userWishlistId = Uuid::randomHex();
        $userWishlistRepository = static::getContainer()->get('user_wishlist.repository');

        $data = [
            'id' => $userWishlistId,
            'userId' => $this->userId,
            'channelId' => $this->getChannelApiChannelId(),
        ];

        if ($blogId !== null) {
            $data['blogs'] = [
                [
                    'blogId' => $blogId,
                ],
            ];
        }

        $userWishlistRepository->create([$data], $this->context);

        return $userWishlistId;
    }
}
