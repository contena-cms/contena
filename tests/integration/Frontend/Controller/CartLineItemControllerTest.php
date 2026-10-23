<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Frontend\Controller;

use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\Channel\CartService;
use Contena\Core\Content\Blog\Aggregate\BlogVisibility\BlogVisibilityDefinition;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\Seo\FrontendChannelTestHelper;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Util\HtmlSanitizer;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Contena\Core\System\Channel\Context\ChannelContextService;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\Test\TestDefaults;
use Contena\Frontend\Controller\CartLineItemController;
use Contena\Frontend\Test\Controller\FrontendControllerTestBehaviour;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;

/**
 * @internal
 */
class CartLineItemControllerTest extends TestCase
{
    use IntegrationTestBehaviour;
    use FrontendControllerTestBehaviour;
    use FrontendChannelTestHelper;

    #[Before]
    #[After]
    public function clearFlashBag(): void
    {
        $this->getFlashBag()->clear();
    }

    #[DataProvider('blogNumbers')]
    public function testAddAndDeleteBlogByNumber(string $blogId, string $blogNumber, bool $available = true): void
    {
        $contextToken = Uuid::randomHex();

        $cartService = static::getContainer()->get(CartService::class);
        if ($blogId && $available) {
            $this->createBlog($blogId, $blogNumber);
        }
        $request = $this->createRequest(['number' => $blogNumber]);

        $channelContext = $this->createChannelContext($contextToken);
        $response = static::getContainer()->get(CartLineItemController::class)->addBlogByNumber($request, $channelContext);

        $cart = $cartService->getCart($contextToken, $channelContext);

        $cartLineItem = $cart->getLineItems()->get($blogId);

        $flashBagEntries = $this->getFlashBag()->all();

        if ($blogId && $available) {
            static::assertArrayHasKey('success', $flashBagEntries);
            static::assertNotNull($cartLineItem);
        } else {
            static::assertArrayHasKey('danger', $flashBagEntries);
            static::assertSame(static::getContainer()->get('translator')->trans('error.blogNotFound', ['%number%' => static::getContainer()->get(HtmlSanitizer::class)->sanitize($blogNumber, null, true)]), $flashBagEntries['danger'][0]);
            static::assertNull($cartLineItem);
        }
        static::assertSame(200, $response->getStatusCode());

        // Delete
        if ($blogId === '') {
            return;
        }

        $response = static::getContainer()->get(CartLineItemController::class)->deleteLineItem($cart, $blogId, $request, $channelContext);

        $cartLineItem = $cartService->getCart($contextToken, $channelContext)->getLineItems()->get($blogId);

        $flashBagEntries = $this->getFlashBag()->all();

        if ($available) {
            static::assertArrayHasKey('success', $flashBagEntries);
        } else {
            static::assertArrayHasKey('danger', $flashBagEntries);
        }
        static::assertNull($cartLineItem);

        static::assertSame(200, $response->getStatusCode());
    }

    #[DataProvider('blogVariations')]
    public function testAddVariationBlogByNumber(string $blogId, string $blogNumber, bool $containerBlogHasChildren, bool $expected): void
    {
        $contextToken = Uuid::randomHex();
        $channelContext = $this->createChannelContext($contextToken);

        $request = $this->createRequest(['number' => $blogNumber]);
        $cartService = static::getContainer()->get(CartService::class);
        $this->createBlog($blogId, 'blogContainer', $containerBlogHasChildren);

        /** @var CartLineItemController $controller */
        $controller = static::getContainer()->get(CartLineItemController::class);
        $controller->setContainer(static::getContainer());

        $response = $controller->addBlogByNumber($request, $channelContext);

        $cart = $cartService->getCart($contextToken, $channelContext);

        $cartLineItem = $cart->getLineItems()->first();

        $flashBag = $this->getFlashBag();

        if ($expected) {
            static::assertNotCount(0, $flashBag->get('success'));
            static::assertNotNull($cartLineItem);
        } else {
            $flashes = $flashBag->get('danger');
            static::assertNotCount(0, $flashes);
            static::assertSame(static::getContainer()->get('translator')->trans('error.blogNotFound', ['%number%' => static::getContainer()->get(HtmlSanitizer::class)->sanitize($blogNumber, null, true)]), $flashes[0]);
            static::assertNull($cartLineItem);
        }
        static::assertSame(200, $response->getStatusCode());
    }

    public static function blogVariations(): \Generator
    {
        yield 'container blog with children' => [
            Uuid::randomHex(),
            'blogContainer',
            true,
            false,
        ];

        yield 'blog without children' => [
            Uuid::randomHex(),
            'blogContainer',
            false,
            true,
        ];

        yield 'existing blog variation' => [
            Uuid::randomHex(),
            'child42',
            true,
            true,
        ];

        yield 'not existing blog variation' => [
            Uuid::randomHex(),
            'child42',
            false,
            false,
        ];
    }

    /**
     * @return list<array{0: string, 1: string, 2?: bool}>
     */
    public static function blogNumbers(): array
    {
        return [
            [Uuid::randomHex(), 'test.123'],
            [Uuid::randomHex(), 'test 123'],
            [Uuid::randomHex(), 'test-123'],
            [Uuid::randomHex(), 'test_123'],
            [Uuid::randomHex(), 'testäüö123'],
            [Uuid::randomHex(), 'test/123'],
            [Uuid::randomHex(), 'test/unavailableBlog', false],
            ['', 'nonExisting'],
            ['', 'with<br>HTML'],
        ];
    }

    public function testAddPromotion(): void
    {
        $contextToken = Uuid::randomHex();

        $cartService = static::getContainer()->get(CartService::class);
        $request = $this->createRequest(['code' => 'testCode']);

        $channelContext = $this->createChannelContext($contextToken);
        static::getContainer()->get(CartLineItemController::class)->addPromotion(
            $cartService->getCart($contextToken, $channelContext),
            $request,
            $channelContext
        );

        $flashBagEntries = $this->getFlashBag()->all();

        static::assertArrayHasKey('danger', $flashBagEntries);
        static::assertSame(static::getContainer()->get('translator')->trans('checkout.promotion-not-found', ['%code%' => 'testCode']), $flashBagEntries['danger'][0]);
        static::assertCount(0, $cartService->getCart($contextToken, $channelContext)->getLineItems());
    }

    public function testAddPromotionWithEmptyInputAddsValidationFlash(): void
    {
        $contextToken = Uuid::randomHex();

        $cartService = static::getContainer()->get(CartService::class);
        $request = $this->createRequest(['code' => '   ']);
        $channelContext = $this->createChannelContext($contextToken);

        $response = static::getContainer()->get(CartLineItemController::class)->addPromotion(
            $cartService->getCart($contextToken, $channelContext),
            $request,
            $channelContext
        );

        static::assertSame(200, $response->getStatusCode());

        $flashBagEntries = $this->getFlashBag()->all();

        static::assertArrayHasKey('danger', $flashBagEntries);
        static::assertSame(static::getContainer()->get('translator')->trans('error.VIOLATION::IS_BLANK_ERROR'), $flashBagEntries['danger'][0]);
        static::assertCount(0, $cartService->getCart($contextToken, $channelContext)->getLineItems());
    }

    public function testAddBlogByNumberTrimsInputBeforeLookup(): void
    {
        $contextToken = Uuid::randomHex();
        $blogId = Uuid::randomHex();

        $this->createBlog($blogId, ' test.123 ');

        $cartService = static::getContainer()->get(CartService::class);
        $request = $this->createRequest(['number' => ' test.123 ']);
        $channelContext = $this->createChannelContext($contextToken);

        $response = static::getContainer()->get(CartLineItemController::class)->addBlogByNumber($request, $channelContext);

        static::assertSame(200, $response->getStatusCode());
        static::assertArrayHasKey('success', $this->getFlashBag()->all());
        static::assertNotNull($cartService->getCart($contextToken, $channelContext)->getLineItems()->get($blogId));
    }

    public function testAddBlogByNumberWithEmptyInputAddsDangerFlash(): void
    {
        $contextToken = Uuid::randomHex();

        $cartService = static::getContainer()->get(CartService::class);
        $request = $this->createRequest(['number' => '   ']);
        $channelContext = $this->createChannelContext($contextToken);

        $response = static::getContainer()->get(CartLineItemController::class)->addBlogByNumber($request, $channelContext);

        static::assertSame(200, $response->getStatusCode());

        $flashBagEntries = $this->getFlashBag()->all();

        static::assertArrayHasKey('danger', $flashBagEntries);
        static::assertSame(static::getContainer()->get('translator')->trans('error.VIOLATION::IS_BLANK_ERROR'), $flashBagEntries['danger'][0]);
        static::assertCount(0, $cartService->getCart($contextToken, $channelContext)->getLineItems());
    }

    private function getFlashBag(): FlashBagInterface
    {
        $sessionBag = $this->getSession()->getBag('flashes');
        static::assertInstanceOf(FlashBagInterface::class, $sessionBag);

        return $sessionBag;
    }

    private function createBlog(string $blogId, string $blogNumber, bool $hasChildren = false): void
    {
        $context = Context::createDefaultContext();
        /** @var string $taxId */
        $taxId = static::getContainer()->get('tax.repository')->searchIds(new Criteria(), $context)->firstId();

        $blog = [
            'id' => $blogId,
            'name' => 'Test blog',
            'blogNumber' => $blogNumber,
            'stock' => 1,
            'price' => [
                ['currencyId' => Defaults::CURRENCY, 'gross' => 15.99, 'net' => 10, 'linked' => false],
            ],
            'taxId' => $taxId,
            'categories' => [
                ['id' => $blogId, 'name' => 'Test category'],
            ],
            'visibilities' => [
                [
                    'id' => $blogId,
                    'channelId' => TestDefaults::CHANNEL,
                    'visibility' => BlogVisibilityDefinition::VISIBILITY_ALL,
                ],
            ],
        ];
        if ($hasChildren) {
            $childId = Uuid::randomHex();
            $blog['children'] = [
                [
                    'id' => $childId,
                    'name' => 'Test blog',
                    'blogNumber' => 'child42',
                    'stock' => 1,
                    'price' => [
                        ['currencyId' => Defaults::CURRENCY, 'gross' => 15.99, 'net' => 10, 'linked' => false],
                    ],
                    'taxId' => $taxId,
                    'categories' => [
                        ['id' => $childId, 'name' => 'Test category'],
                    ],
                    'visibilities' => [
                        [
                            'id' => $childId,
                            'channelId' => TestDefaults::CHANNEL,
                            'visibility' => BlogVisibilityDefinition::VISIBILITY_ALL,
                        ],
                    ],
                ],
            ];
        }
        static::getContainer()->get('blog.repository')->create([$blog], $context);
    }

    private function createChannelContext(string $contextToken, ?string $paymentMethodId = null): ChannelContext
    {
        return static::getContainer()->get(ChannelContextFactory::class)->create(
            $contextToken,
            TestDefaults::CHANNEL,
            $paymentMethodId ? [ChannelContextService::PAYMENT_METHOD_ID => $paymentMethodId] : []
        );
    }

    /**
     * @param array<string, string> $request
     */
    private function createRequest(array $request = []): Request
    {
        $request = new Request([], $request);
        $request->setSession($this->getSession());

        $requestStack = static::getContainer()->get('request_stack');
        $requestStack->push($request);

        return $request;
    }
}
