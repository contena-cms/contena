<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\System\User\Channel;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Contena\Core\System\User\Event\WishlistBlogAddedEvent;
use Contena\Core\Content\Blog\Aggregate\BlogVisibility\BlogVisibilityDefinition;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Routing\RoutingException;
use Contena\Core\Framework\Util\Random;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\PlatformRequest;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Core\Test\Integration\EventDispatcher\EventHookDispatcher;
use Contena\Core\Test\Integration\Traits\UserTestTrait;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * @internal
 */
#[Group('store-api')]
class AddWishlistBlogRouteTest extends TestCase
{
    use UserTestTrait;

    private KernelBrowser $browser;

    private Context $context;

    private string $userId;

    private SystemConfigService $systemConfigService;

    protected function setUp(): void
    {
        $this->context = Context::createDefaultContext();
        $ids = new IdsCollection();

        $this->browser = $this->createCustomChannelBrowser([
            'id' => $ids->create('sales-channel'),
        ]);
        $this->assignChannelContext($this->browser);

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

        $contextToken = $response->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN) ?? '';
        static::assertNotSame('', $contextToken);

        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $contextToken);
    }

    public function testAddBlogShouldReturnSuccess(): void
    {
        $blogData = $this->createBlog($this->context);
        $addedEvent = null;

        EventHookDispatcher::fromContainer(static::getContainer())->on(WishlistBlogAddedEvent::class, static function (WishlistBlogAddedEvent $event) use (&$addedEvent): void {
            $addedEvent = $event;
        });

        $this->browser
            ->request(
                'POST',
                '/store-api/user/wishlist/add/' . $blogData[0]
            );
        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame(200, $this->browser->getResponse()->getStatusCode());
        static::assertTrue($response['success']);
        static::assertInstanceOf(WishlistBlogAddedEvent::class, $addedEvent);
        static::assertSame($blogData[0], $addedEvent->getBlogId());
    }

    public function testAddBlogShouldThrowUserWishlistNotActivatedException(): void
    {
        $blogData = $this->createBlog($this->context);
        $this->systemConfigService->set('core.cart.wishlistEnabled', false);

        $this->browser
            ->request(
                'POST',
                '/store-api/user/wishlist/add/' . $blogData[0]
            );
        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $errors = $response['errors'][0];
        static::assertSame(403, $this->browser->getResponse()->getStatusCode());
        static::assertSame('CHECKOUT__WISHLIST_IS_NOT_ACTIVATED', $errors['code']);
        static::assertSame('Forbidden', $errors['title']);
        static::assertSame('Wishlist is not activated!', $errors['detail']);
    }

    public function testAddBlogShouldThrowUserNotLoggedInException(): void
    {
        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', Random::getAlphanumericString(12));

        $blogId = Uuid::randomHex();
        $this->browser
            ->request(
                'POST',
                '/store-api/user/wishlist/add/' . $blogId
            );
        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $errors = $response['errors'][0];
        static::assertSame(403, $this->browser->getResponse()->getStatusCode());
        static::assertSame(RoutingException::CUSTOMER_NOT_LOGGED_IN_CODE, $response['errors'][0]['code']);
        static::assertSame('Forbidden', $errors['title']);
        static::assertSame('User is not logged in.', $errors['detail']);
    }

    public function testAddBlogShouldThrowDuplicateWishlistBlogException(): void
    {
        $blogData = $this->createBlog($this->context);
        $this->createUserWishlist($this->context, $this->userId, $blogData[0]);
        $this->browser
            ->request(
                'POST',
                '/store-api/user/wishlist/add/' . $blogData[0]
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        $errors = $response['errors'][0];
        unset($errors['meta']);

        static::assertSame(400, $this->browser->getResponse()->getStatusCode(), print_r($errors, true));
        static::assertSame('CHECKOUT__DUPLICATE_WISHLIST_BLOG', $errors['code']);
    }

    public function testAddBlogShouldThrowBlogNotFoundException(): void
    {
        $blogId = Uuid::randomHex();
        $this->browser
            ->request(
                'POST',
                '/store-api/user/wishlist/add/' . $blogId
            );
        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $errors = $response['errors'][0];

        if (Feature::isActive('v6.8.0.0')) {
            static::assertSame(400, $this->browser->getResponse()->getStatusCode());
            static::assertSame('CONTENT__MISSING_REQUEST_PARAMETER_CODE', $errors['code']);
            static::assertSame('Blog for id ' . $blogId . ' not found.', $errors['detail']);

            return;
        }

        static::assertSame(404, $this->browser->getResponse()->getStatusCode());
        static::assertSame('CONTENT__BLOG_NOT_FOUND', $errors['code']);
        static::assertSame('Not Found', $errors['title']);
        static::assertSame(\sprintf('Could not find blog with id "%s"', $blogId), $errors['detail']);
    }

    /**
     * @return array<int, string>
     */
    private function createBlog(Context $context): array
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
                ['channelId' => $this->getChannelApiChannelId(), 'visibility' => BlogVisibilityDefinition::VISIBILITY_ALL],
            ],
        ];

        static::getContainer()->get('blog.repository')->create([$data], $context);

        return [$blogId, $blogNumber];
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
