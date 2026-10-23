<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\System\User\Channel;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Aggregate\BlogVisibility\BlogVisibilityDefinition;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
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
class LoadWishlistRouteTest extends TestCase
{
    use UserTestTrait;
    use IntegrationTestBehaviour;

    private KernelBrowser $browser;

    private IdsCollection $ids;

    private Context $context;

    private string $userId;

    private SystemConfigService $systemConfigService;

    protected function setUp(): void
    {
        $this->context = Context::createDefaultContext();
        $this->ids = new IdsCollection();

        $this->browser = $this->createCustomChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
        ]);
        $this->assignChannelContext($this->browser);

        $this->systemConfigService = static::getContainer()->get(SystemConfigService::class);
        $this->systemConfigService->set('core.cart.wishlistEnabled', true);

        $email = Uuid::randomHex() . '@example.com';
        $this->userId = $this->createUser($email);

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

    public function testLoadShouldReturnSuccess(): void
    {
        $blogId = $this->createBlog($this->context);
        $userWishlistId = $this->createUserWishlist($this->context, $this->userId, $blogId);

        $this->browser
            ->request(
                'POST',
                '/store-api/user/wishlist'
            );
        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $wishlist = $response['wishlist'];
        $blogs = $response['blogs'];

        static::assertNotCount(0, $response);
        static::assertSame($userWishlistId, $wishlist['id']);
        static::assertSame(1, $blogs['total']);
        static::assertNotNull($blogs['elements']);
    }

    public function testDeleteBlogShouldThrowUserWishlistNotActivatedException(): void
    {
        $this->systemConfigService->set('core.cart.wishlistEnabled', false);

        $this->browser
            ->request(
                'POST',
                '/store-api/user/wishlist'
            );
        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $errors = $response['errors'][0];
        static::assertSame(403, $this->browser->getResponse()->getStatusCode());
        static::assertSame('CHECKOUT__WISHLIST_IS_NOT_ACTIVATED', $errors['code']);
        static::assertSame('Forbidden', $errors['title']);
        static::assertSame('Wishlist is not activated!', $errors['detail']);
    }

    public function testLoadShouldThrowUserNotLoggedInException(): void
    {
        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', Random::getAlphanumericString(12));

        $this->browser
            ->request(
                'POST',
                '/store-api/user/wishlist'
            );
        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $errors = $response['errors'][0];
        static::assertSame(403, $this->browser->getResponse()->getStatusCode());
        static::assertSame(RoutingException::CUSTOMER_NOT_LOGGED_IN_CODE, $errors['code']);
        static::assertSame('Forbidden', $errors['title']);
        static::assertSame('User is not logged in.', $errors['detail']);
    }

    public function testLoadShouldThrowUserWishlistNotFoundException(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/user/wishlist'
            );
        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $errors = $response['errors'][0];
        static::assertSame(404, $this->browser->getResponse()->getStatusCode());
        static::assertSame('CHECKOUT__WISHLIST_NOT_FOUND', $errors['code']);
        static::assertSame('Not Found', $errors['title']);
        static::assertSame('Wishlist for this user was not found.', $errors['detail']);
    }

    public function testLoadWithHideCloseoutBlogsWhenOutOfStockEnabled(): void
    {
        // enable hideCloseoutBlogsWhenOutOfStock filter
        static::getContainer()->get(SystemConfigService::class)
            ->set('core.listing.hideCloseoutBlogsWhenOutOfStock', true);

        $blogId = $this->createBlog($this->context, ['stock' => 0, 'isCloseout' => true]);
        $this->createUserWishlist($this->context, $this->userId, $blogId);

        $this->browser
            ->request(
                'POST',
                '/store-api/user/wishlist'
            );
        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $blogs = $response['blogs'];

        static::assertSame(0, $blogs['total']);
    }

    public function testLoadWithHideCloseoutBlogsWhenOutOfStockDisabled(): void
    {
        // disabled hideCloseoutBlogsWhenOutOfStock filter
        static::getContainer()->get(SystemConfigService::class)
            ->set('core.listing.hideCloseoutBlogsWhenOutOfStock', false);

        $blogId = $this->createBlog($this->context, ['stock' => 0, 'isCloseout' => true]);
        $this->createUserWishlist($this->context, $this->userId, $blogId);

        $this->browser
            ->request(
                'POST',
                '/store-api/user/wishlist'
            );
        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $blogs = $response['blogs'];

        static::assertSame(1, $blogs['total']);
        static::assertNotNull($blogs['elements']);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function createBlog(Context $context, array $attributes = []): string
    {
        $blogId = Uuid::randomHex();

        $blogNumber = Uuid::randomHex();
        $data = [
            'id' => $blogId,
            'blogNumber' => $blogNumber,
            'stock' => 1,
            'name' => 'Test Blog',
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10.99, 'net' => 11.99, 'linked' => false]],
            'manufacturer' => ['name' => 'create'],
            'taxId' => $this->getValidTaxId(),
            'active' => true,
            'visibilities' => [
                [
                    'channelId' => $this->getChannelApiChannelId(),
                    'visibility' => BlogVisibilityDefinition::VISIBILITY_ALL,
                ],
            ],
        ];

        static::getContainer()->get('blog.repository')->create([array_merge($data, $attributes)], $context);

        return $blogId;
    }

    private function createUserWishlist(Context $context, string $userId, string $blogId): string
    {
        $userWishlistId = Uuid::randomHex();
        $userWishlistRepository = static::getContainer()->get('user_wishlist.repository');

        $userWishlistRepository->create([
            [
                'id' => $userWishlistId,
                'userId' => $userId,
                'channelId' => $this->getChannelApiChannelId(),
                'blogs' => [
                    [
                        'blogId' => $blogId,
                    ],
                ],
            ],
        ], $context);

        return $userWishlistId;
    }
}
