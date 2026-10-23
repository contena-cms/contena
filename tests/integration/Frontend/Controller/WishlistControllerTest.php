<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Frontend\Controller;

use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Aggregate\BlogVisibility\BlogVisibilityDefinition;
use Contena\Core\Defaults;
use Contena\Core\DevOps\Environment\EnvironmentHelper;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\Script\Debugging\ScriptTraces;
use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Core\System\User\UserEntity;
use Contena\Core\Test\Integration\EventDispatcher\EventHookDispatcher;
use Contena\Core\Test\TestDefaults;
use Contena\Frontend\Event\FrontendRenderEvent;
use Contena\Frontend\Page\Wishlist\GuestWishlistPageLoadedHook;
use Contena\Frontend\Page\Wishlist\WishlistPageLoadedHook;
use Contena\Frontend\Page\Wishlist\WishlistWidgetLoadedHook;
use Contena\Frontend\Pagelet\Wishlist\GuestWishlistPageletLoadedHook;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\HttpFoundation\Session\Session;
use Contena\Core\Framework\Test\TestCaseBase\ChannelApiTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Frontend\Test\Controller\FrontendControllerTestBehaviour;

/**
 * @internal
 */
class WishlistControllerTest extends TestCase
{
    use IntegrationTestBehaviour;
    use ChannelApiTestBehaviour;
    use FrontendControllerTestBehaviour;

    private string $userId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->userId = Uuid::randomHex();
        $systemConfig = static::getContainer()->get(SystemConfigService::class);
        $systemConfig->set('core.cart.wishlistEnabled', true);
    }

    #[Before]
    #[After]
    public function clearFlashBag(): void
    {
        $this->getFlashBag()->clear();
    }

    public function testWishlistIndex(): void
    {
        $browser = $this->login();

        $blogId = $this->createBlog($this->getChannelId());

        // add blog to wishlist
        $this->createUserWishlist($blogId, $this->getChannelId());

        $browser->request('GET', '/wishlist');
        $response = $browser->getResponse();

        static::assertSame(200, $response->getStatusCode(), $response->getContent() ?: '');
    }

    public function testWishlistGuestIndex(): void
    {
        $browser = $this->createCustomChannelBrowser();

        $browser->request('GET', '/wishlist');

        $response = $browser->getResponse();

        static::assertSame(200, $response->getStatusCode());
    }

    public function testWishlistGuestPageletShouldThrowExceptionWhenLoggedIn(): void
    {
        $browser = $this->login();

        $browser->request('GET', '/');

        $blogId = $this->createBlog(TestDefaults::CHANNEL);

        $browser->request('POST', '/wishlist/guest-pagelet', $this->tokenize('frontend.wishlist.guestPage.pagelet', ['blogIds' => [$blogId]]));

        static::assertSame(Response::HTTP_NOT_FOUND, $browser->getResponse()->getStatusCode());
    }

    public function testWishlistGuestPagelet(): void
    {
        $browser = $this->createCustomChannelBrowser();
        $channelId = $browser->getServerParameter('test-sales-channel-id');
        static::assertIsString($channelId);

        $blogId = $this->createBlog($channelId);

        $renderedParameters = [];
        EventHookDispatcher::fromContainer(static::getContainer())->on(FrontendRenderEvent::class, static function (FrontendRenderEvent $event) use (&$renderedParameters): void {
            $renderedParameters[] = $event->getParameters();
        });

        $browser->request('POST', '/wishlist/guest-pagelet', $this->tokenize('frontend.wishlist.guestPage.pagelet', ['blogIds' => [$blogId]]));

        $response = $browser->getResponse();

        static::assertSame(200, $response->getStatusCode());
        static::assertCount(1, $renderedParameters);
        static::assertInstanceOf(EntitySearchResult::class, $result = $renderedParameters[0]['searchResult']);
        $entities = $result->getEntities();
        static::assertCount(1, $entities);
        $first = $entities->first();
        static::assertInstanceOf(Entity::class, $first);
        static::assertSame($blogId, $first->get('id'));
    }

    public function testDeleteBlogInWishlistPage(): void
    {
        $browser = $this->login();

        $blogId = $this->createBlog($this->getChannelId());

        $browser->request('DELETE', '/wishlist/blog/delete/' . $blogId);

        static::assertSame(
            ['danger' => ['Unfortunately, something went wrong.']],
            $this->getFlashBag()->all()
        );

        $this->createUserWishlist($blogId, $this->getChannelId());

        $browser->request('DELETE', '/wishlist/blog/delete/' . $blogId);
        $response = $browser->getResponse();

        static::assertArrayHasKey('success', $this->getFlashBag()->all());
        static::assertSame(200, $response->getStatusCode());
    }

    public function testAjaxListWithoutCreatedWishlist(): void
    {
        $browser = $this->login();

        $browser->request('GET', '/wishlist/list');

        $response = $browser->getResponse();

        static::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        static::assertSame([], json_decode((string) $response->getContent(), false, 512, \JSON_THROW_ON_ERROR));
    }

    public function testAjaxListWithoutLoggedInUserReturnsForbidden(): void
    {
        $browser = $this->createCustomChannelBrowser();

        $browser->xmlHttpRequest('GET', '/wishlist/list');

        $response = $browser->getResponse();

        static::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode(), (string) $response->getContent());
    }

    public function testAjaxAdd(): void
    {
        $browser = $this->login();

        $blogId = $this->createBlog($this->getChannelId());

        $browser->request('POST', '/wishlist/add/' . $blogId, $this->tokenize('frontend.wishlist.blog.add', []));

        $response = $browser->getResponse();

        static::assertSame(200, $response->getStatusCode(), (string) $response->getContent());

        $content = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertIsArray($content);
        static::assertTrue($content['success']);
    }

    public function testAjaxList(): void
    {
        $browser = $this->login();

        $browser->request('GET', '/wishlist/list');

        $response = $browser->getResponse();

        static::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
    }

    public function testAjaxRemove(): void
    {
        $browser = $this->login();

        $blogId = $this->createBlog($this->getChannelId());

        $browser->request('POST', '/wishlist/add/' . $blogId, $this->tokenize('frontend.wishlist.blog.add', []));

        $response = $browser->getResponse();

        static::assertSame(200, $response->getStatusCode(), (string) $response->getContent());

        $browser->request('POST', '/wishlist/remove/' . $blogId, $this->tokenize('frontend.wishlist.blog.remove', []));

        $response = $browser->getResponse();

        static::assertSame(200, $response->getStatusCode(), (string) $response->getContent());

        $content = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertIsArray($content);
        static::assertTrue($content['success']);
    }

    public function testAddAfterLogin(): void
    {
        $browser = $this->login();

        $blogId = $this->createBlog($this->getChannelId());

        $browser->request('GET', '/wishlist/add-after-login/' . $blogId);

        /** @var RedirectResponse $response */
        $response = $browser->getResponse();

        static::assertSame(302, $response->getStatusCode());
        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertSame('/wishlist', $response->getTargetUrl());

        $session = $this->getSession();
        static::assertInstanceOf(Session::class, $session);
        $flashBag = $session->getFlashBag();

        static::assertNotCount(0, $successFlash = $flashBag->get('success'));
        static::assertSame('You have successfully added the blog to your wishlist.', $successFlash[0]);

        $browser->request('GET', '/wishlist/add-after-login/' . $blogId);

        static::assertNotCount(0, $warningFlash = $flashBag->get('warning'));
        static::assertSame('Blog has already been added to your wishlist.', $warningFlash[0]);
    }

    public function testWishlistPageLoadedHookScriptsAreExecuted(): void
    {
        $browser = $this->login();

        $browser->request('GET', '/wishlist');
        $response = $browser->getResponse();
        static::assertSame(200, $response->getStatusCode(), (string) $response->getContent());

        $traces = $browser->getContainer()->get(ScriptTraces::class)->getTraces();

        static::assertArrayHasKey(WishlistPageLoadedHook::HOOK_NAME, $traces);
    }

    public function testGuestWishlistPageLoadedHookScriptsAreExecuted(): void
    {
        $response = $this->request('GET', '/wishlist', []);
        static::assertSame(200, $response->getStatusCode());

        $traces = $this->getFrontendRequestContainer()->get(ScriptTraces::class)->getTraces();

        static::assertArrayHasKey(GuestWishlistPageLoadedHook::HOOK_NAME, $traces);
    }

    public function testGuestWishlistPageletLoadedHookScriptsAreExecuted(): void
    {
        $browser = $this->registerAsGuest();

        $browser->xmlHttpRequest(
            'POST',
            '/wishlist/guest-pagelet'
        );
        $response = $browser->getResponse();

        static::assertSame(200, $response->getStatusCode());

        $traces = $browser->getContainer()->get(ScriptTraces::class)->getTraces();

        static::assertArrayHasKey(GuestWishlistPageletLoadedHook::HOOK_NAME, $traces);
    }

    public function testWishlistPageLoadedHookScriptsAreExecutedForWidget(): void
    {
        $browser = $this->login();

        $browser->request('GET', '/widgets/wishlist');
        $response = $browser->getResponse();
        static::assertSame(200, $response->getStatusCode(), (string) $response->getContent());

        $traces = $browser->getContainer()->get(ScriptTraces::class)->getTraces();

        static::assertArrayHasKey(WishlistPageLoadedHook::HOOK_NAME, $traces);
    }

    public function testWishlistWidgetLoadedHookScriptsAreExecuted(): void
    {
        $browser = $this->login();

        $browser->request('GET', '/wishlist/merge/pagelet');
        $response = $browser->getResponse();
        static::assertSame(200, $response->getStatusCode());

        $traces = $browser->getContainer()->get(ScriptTraces::class)->getTraces();

        static::assertArrayHasKey(WishlistWidgetLoadedHook::HOOK_NAME, $traces);
    }

    private function createUser(): UserEntity
    {
        $addressId = Uuid::randomHex();

        $user = [
            'id' => $this->userId,
            'channelId' => TestDefaults::CHANNEL,
            'defaultShippingAddress' => [
                'id' => $addressId,
                'name' => 'Max',
                'lastName' => 'Mustermann',
                'street' => 'Musterstraße 1',
                'city' => 'Schöppingen',
                'zipcode' => '12345',
                'salutationId' => $this->getValidSalutationId(),
                'countryId' => $this->getValidCountryId(),
            ],
            'defaultBillingAddressId' => $addressId,
            'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
            'email' => $this->userId . '@example.com',
            'password' => TestDefaults::HASHED_PASSWORD,
            'name' => 'Max',
            'lastName' => 'Mustermann',
            'salutationId' => $this->getValidSalutationId(),
            'userNumber' => $this->userId,
        ];

        $repo = static::getContainer()->get('user.repository');

        $repo->create([$user], Context::createDefaultContext());

        $entity = $repo->search(new Criteria([$this->userId]), Context::createDefaultContext())->getEntities()->first();

        static::assertInstanceOf(UserEntity::class, $entity);

        return $entity;
    }

    private function login(): KernelBrowser
    {
        $user = $this->createUser();

        $browser = KernelLifecycleManager::createBrowser($this->getKernel());
        $browser->request(
            'POST',
            EnvironmentHelper::getVariable('APP_URL') . '/account/login',
            $this->tokenize('frontend.account.login', [
                'username' => $user->getEmail(),
                'password' => 'contena',
            ])
        );
        $response = $browser->getResponse();
        static::assertSame(200, $response->getStatusCode(), (string) $response->getContent());

        return $browser;
    }

    private function registerAsGuest(): KernelBrowser
    {
        $browser = KernelLifecycleManager::createBrowser($this->getKernel());
        $browser->request(
            'POST',
            $_SERVER['APP_URL'] . '/account/register',
            $this->tokenize('frontend.account.register.save', [
                'accountType' => UserEntity::ACCOUNT_TYPE_PRIVATE,
                'email' => 'max.mustermann@example.com',
                'emailConfirmation' => 'max.mustermann@example.com',
                'salutationId' => $this->getValidSalutationId(),
                'name' => 'Max',
                'lastName' => 'Mustermann',
                'frontendUrl' => 'http://localhost',
                'guest' => true,
                'billingAddress' => [
                    'countryId' => $this->getValidCountryId(),
                    'street' => 'Musterstrasse 13',
                    'zipcode' => '48599',
                    'city' => 'Epe',
                ],
            ])
        );
        $response = $browser->getResponse();
        static::assertSame(200, $response->getStatusCode(), (string) $response->getContent());

        return $browser;
    }

    private function createBlog(?string $channelId = null): string
    {
        $id = Uuid::randomHex();

        $blog = [
            'id' => $id,
            'blogNumber' => $id,
            'stock' => 5,
            'name' => 'Test',
            'isCloseout' => true,
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
            'tax' => ['id' => Uuid::randomHex(), 'name' => 'test', 'taxRate' => 19],
            'manufacturer' => ['name' => 'test'],
            'visibilities' => [
                [
                    'channelId' => $channelId ?? TestDefaults::CHANNEL,
                    'visibility' => BlogVisibilityDefinition::VISIBILITY_ALL,
                ],
            ],
        ];

        $repository = static::getContainer()->get('blog.repository');

        $repository->create([$blog], Context::createDefaultContext());

        return $id;
    }

    private function createUserWishlist(string $blogId, string $channelId): string
    {
        $userWishlistId = Uuid::randomHex();
        $userWishlistRepository = static::getContainer()->get('user_wishlist.repository');

        $userWishlistRepository->create([
            [
                'id' => $userWishlistId,
                'userId' => $this->userId,
                'channelId' => $channelId,
                'blogs' => [
                    [
                        'blogId' => $blogId,
                    ],
                ],
            ],
        ], Context::createDefaultContext());

        return $userWishlistId;
    }

    private function getFlashBag(): FlashBagInterface
    {
        $session = $this->getSession();

        if (!$session instanceof FlashBagAwareSessionInterface) {
            throw new \RuntimeException('session does not have flashbag');
        }

        return $session->getFlashBag();
    }
}
