<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Adapter\Cache\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\Cart;
use Contena\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Contena\Core\Checkout\Cart\LineItem\LineItem;
use Contena\Core\System\User\UserEntity;
use Contena\Core\Checkout\Payment\PaymentMethodEntity;
use Contena\Core\Checkout\Shipping\ShippingMethodEntity;
use Contena\Core\Defaults;
use Contena\Core\Framework\Adapter\Cache\Event\HttpCacheCookieEvent;
use Contena\Core\Framework\Adapter\Cache\Http\CacheHeadersService;
use Contena\Core\Framework\Adapter\Cache\Http\CacheRelevantRulesResolver;
use Contena\Core\Framework\Adapter\Cache\Http\HttpCacheKeyGenerator;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\RuleAreas;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Routing\StoreApiRouteScope;
use Contena\Core\Framework\Test\TestCaseBase\EventDispatcherBehaviour;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Country\CountryEntity;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\ChannelEntity;
use Contena\Frontend\Framework\Routing\FrontendRouteScope;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

/**
 * @internal
 */
#[CoversClass(CacheHeadersService::class)]
class CacheHeadersServiceTest extends TestCase
{
    use EventDispatcherBehaviour;

    /**
     * @var array<string, string>
     */
    private static array $hashes = [];

    private CacheHeadersService $cacheHeadersService;

    private EventDispatcher $eventDispatcher;

    protected function setUp(): void
    {
        $this->eventDispatcher = new EventDispatcher();
        $extensionDispatcher = new ExtensionDispatcher($this->eventDispatcher);

        $this->cacheHeadersService = new CacheHeadersService(
            $extensionDispatcher,
            new CacheRelevantRulesResolver($extensionDispatcher),
            [],
            $this->eventDispatcher,
        );
    }

    #[DataProvider('cashHashProvider')]
    public function testGenerateCashHashWithItemsInCart(?UserEntity $user, Cart $cart, bool $hasCookie, ?string $hashName = null): void
    {
        $channelContext = $this->createMock(ChannelContext::class);
        $channelContext->expects($this->atLeastOnce())->method('getUser')->willReturn($user);
        if ($user !== null) {
            $channelContext->expects($this->once())
                ->method('getRuleIdsByAreas')
                ->with([RuleAreas::BLOG_AREA])
                ->willReturn(['matched-rule']);
        }

        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT, $channelContext);

        if ($hasCookie === false) {
            $request->cookies->set(HttpCacheKeyGenerator::CONTEXT_CACHE_COOKIE, 'foo');
        }

        $response = new Response();

        $this->cacheHeadersService->applyCacheHash($request, $channelContext, $cart, $response);

        if ($hasCookie) {
            static::assertTrue($response->headers->has('set-cookie'));

            $cookies = array_filter($response->headers->getCookies(), static fn (Cookie $cookie) => $cookie->getName() === HttpCacheKeyGenerator::CONTEXT_CACHE_COOKIE);

            static::assertCount(1, $cookies);
            $cookie = array_shift($cookies);

            static::assertNotNull($cookie->getValue());
            if ($hashName) {
                if (!isset(self::$hashes[$hashName])) {
                    self::$hashes[$hashName] = $cookie->getValue();
                }

                foreach (self::$hashes as $name => $value) {
                    if ($hashName === $name) {
                        static::assertSame(
                            $value,
                            $cookie->getValue(),
                            \sprintf('Hashes for state "%s" did not match, got "%s", but expected "%s"', $hashName, $cookie->getValue(), $value)
                        );
                    } else {
                        static::assertNotSame(
                            $value,
                            $cookie->getValue(),
                            \sprintf('Hashes for state "%s" and state "%s" should not match, but did match.', $hashName, $name)
                        );
                    }
                }
            }

            static::assertSame($cookie->getValue(), $response->headers->get(HttpCacheKeyGenerator::CONTEXT_CACHE_COOKIE));
        } else {
            $cookies = $response->headers->getCookies();
            static::assertNotCount(0, $cookies, 'the client cookie should be cleared');

            foreach ($cookies as $cookie) {
                static::assertSame(1, $cookie->getExpiresTime(), 'cookie should expire');
            }

            static::assertNull($response->headers->get(HttpCacheKeyGenerator::CONTEXT_CACHE_COOKIE));
        }
    }

    /**
     * @return iterable<string, array<int, UserEntity|Cart|bool|string|null>>
     */
    public static function cashHashProvider(): iterable
    {
        $emptyCart = new Cart('empty');
        $user = new UserEntity();

        $filledCart = new Cart('filled');
        $filledCart->add(new LineItem('test', 'test', 'test'));

        yield 'Test with no logged in user' => [null, $emptyCart, false];
        yield 'Test with filled cart' => [null, $filledCart, true, 'not-logged-in'];
        // all logged in user should share the same cache hash if no rules match
        yield 'Test with logged in user' => [$user, $emptyCart, true, 'logged-in'];
        yield 'Test with filled cart and logged in user' => [$user, $filledCart, true, 'logged-in'];
    }

    public function testTheSameContextHashesTheSameInEveryScope(): void
    {
        $frontend = $this->cacheHeadersService->applyCacheHash(
            new Request(attributes: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [FrontendRouteScope::ID]]),
            $this->createCacheHashContext('language-a'),
            $this->createFilledCart(),
            new Response()
        );

        $storeApi = $this->cacheHeadersService->applyCacheHash(
            new Request(attributes: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]]),
            $this->createCacheHashContext('language-a'),
            $this->createFilledCart(),
            new Response()
        );

        static::assertInstanceOf(HttpCacheCookieEvent::class, $frontend);
        static::assertInstanceOf(HttpCacheCookieEvent::class, $storeApi);
        static::assertSame('language-a', $frontend->get(HttpCacheCookieEvent::LANGUAGE_ID));
        static::assertSame('language-a', $storeApi->get(HttpCacheCookieEvent::LANGUAGE_ID));
        static::assertSame($frontend->getHash(), $storeApi->getHash());
    }

    public function testStoreNonDefaultLanguageRequiresCacheHash(): void
    {
        $request = new Request(attributes: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]]);

        $event = $this->cacheHeadersService->applyCacheHash(
            $request,
            $this->createCacheHashContext('language-a'),
            new Cart('empty'),
            new Response()
        );

        static::assertInstanceOf(HttpCacheCookieEvent::class, $event);
        static::assertSame('language-a', $event->get(HttpCacheCookieEvent::LANGUAGE_ID));
    }

    public function testStoreApiHeaderProvidedLanguageDoesNotRequireCacheHash(): void
    {
        $request = new Request(attributes: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]]);
        // the header carries the effective language, it is part of the cache key instead of the hash
        $request->headers->set(PlatformRequest::HEADER_LANGUAGE_ID, 'language-a');

        $event = $this->cacheHeadersService->applyCacheHash(
            $request,
            $this->createCacheHashContext('language-a'),
            new Cart('empty'),
            new Response()
        );

        static::assertNull($event);
    }

    public function testStoreApiHeaderWithDifferentCasingRequiresCacheHash(): void
    {
        $request = new Request(attributes: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]]);
        // language ids are matched byte-exact; a differently cased header is a different value,
        // so it does not represents the correct language and the hash is required
        $request->headers->set(PlatformRequest::HEADER_LANGUAGE_ID, 'LANGUAGE-A');

        $event = $this->cacheHeadersService->applyCacheHash(
            $request,
            $this->createCacheHashContext('language-a'),
            new Cart('empty'),
            new Response()
        );

        static::assertInstanceOf(HttpCacheCookieEvent::class, $event);
        static::assertSame('language-a', $event->get(HttpCacheCookieEvent::LANGUAGE_ID));
    }

    public function testFrontendNonDefaultLanguageDoesNotRequireCacheHash(): void
    {
        $request = new Request(attributes: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [FrontendRouteScope::ID]]);
        $request->headers->set(PlatformRequest::HEADER_LANGUAGE_ID, 'language-a');

        $event = $this->cacheHeadersService->applyCacheHash(
            $request,
            $this->createCacheHashContext('language-a'),
            new Cart('empty'),
            new Response()
        );

        // frontend language is carried by the domain URL, so custom language should not trigger hash generation
        static::assertNull($event);
    }

    public function testNonDefaultShippingCountryRequiresCacheHash(): void
    {
        // an anonymous visitor can persist a shipping country via the context switch route;
        // it is invisible to the cache key (any route scope), so it must trigger the cache hash
        $event = $this->cacheHeadersService->applyCacheHash(
            new Request(),
            $this->createCacheHashContext(Defaults::LANGUAGE_SYSTEM, 'country-tax-free'),
            new Cart('empty'),
            new Response()
        );

        static::assertInstanceOf(HttpCacheCookieEvent::class, $event);
    }

    public function testDefaultShippingCountryDoesNotRequireCacheHash(): void
    {
        $event = $this->cacheHeadersService->applyCacheHash(
            new Request(),
            $this->createCacheHashContext(Defaults::LANGUAGE_SYSTEM),
            new Cart('empty'),
            new Response()
        );

        static::assertNull($event);
    }

    public function testNonDefaultPaymentMethodRequiresCacheHash(): void
    {
        // switchable anonymously and rules (e.g. price rules) can condition on the payment method
        $event = $this->cacheHeadersService->applyCacheHash(
            new Request(),
            $this->createCacheHashContext(Defaults::LANGUAGE_SYSTEM, paymentMethodId: 'payment-invoice'),
            new Cart('empty'),
            new Response()
        );

        static::assertInstanceOf(HttpCacheCookieEvent::class, $event);
    }

    public function testNonDefaultShippingMethodRequiresCacheHash(): void
    {
        // switchable anonymously and rules (e.g. price rules) can condition on the shipping method
        $event = $this->cacheHeadersService->applyCacheHash(
            new Request(),
            $this->createCacheHashContext(Defaults::LANGUAGE_SYSTEM, shippingMethodId: 'shipping-express'),
            new Cart('empty'),
            new Response()
        );

        static::assertInstanceOf(HttpCacheCookieEvent::class, $event);
    }

    public function testCurrencyChangeLeadsToDifferentCacheHash(): void
    {
        $request = new Request();
        $channelContextMock = static::createStub(ChannelContext::class);
        $channelContextMock->method('getChannel')->willReturn((new ChannelEntity())->assign([
            'currencyId' => Defaults::CURRENCY,
            'languageId' => Defaults::LANGUAGE_SYSTEM,
            'countryId' => 'country-default',
            'paymentMethodId' => 'payment-default',
            'shippingMethodId' => 'shipping-default',
        ]));
        $channelContextMock->method('getCurrencyId')->willReturn(Defaults::CURRENCY);
        $channelContextMock->method('getShippingLocation')->willReturn(self::createShippingLocation());
        $channelContextMock->method('getPaymentMethod')->willReturn((new PaymentMethodEntity())->assign(['id' => 'payment-default']));
        $channelContextMock->method('getShippingMethod')->willReturn((new ShippingMethodEntity())->assign(['id' => 'shipping-default']));
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT, $channelContextMock);

        $response = new Response();

        $this->cacheHeadersService->applyCacheHash($request, $channelContextMock, new Cart('cart'), $response);

        $cookies = $response->headers->getCookies();
        static::assertCount(0, $cookies);

        $channelContextMock = static::createStub(ChannelContext::class);
        $channelContextMock->method('getChannel')->willReturn((new ChannelEntity())->assign(['currencyId' => Defaults::CURRENCY, 'languageId' => Defaults::LANGUAGE_SYSTEM]));
        $channelContextMock->method('getCurrencyId')->willReturn('foo');
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT, $channelContextMock);

        $this->cacheHeadersService->applyCacheHash($request, $channelContextMock, new Cart('cart'), $response);

        $cookies = $response->headers->getCookies();
        static::assertNotCount(0, $cookies);
        // assert cache hash exist when currency is set to different value then the sales channel default
        static::assertSame(HttpCacheKeyGenerator::CONTEXT_CACHE_COOKIE, $cookies[0]->getName());
        $firstHash = $cookies[0]->getValue();

        $channelContextMock = static::createStub(ChannelContext::class);
        $channelContextMock->method('getChannel')->willReturn((new ChannelEntity())->assign(['currencyId' => Defaults::CURRENCY, 'languageId' => Defaults::LANGUAGE_SYSTEM]));
        $channelContextMock->method('getCurrencyId')->willReturn('bar');
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT, $channelContextMock);

        $this->cacheHeadersService->applyCacheHash($request, $channelContextMock, new Cart('cart'), $response);

        $cookies = $response->headers->getCookies();
        static::assertNotCount(0, $cookies);
        static::assertSame(HttpCacheKeyGenerator::CONTEXT_CACHE_COOKIE, $cookies[0]->getName());
        $secondHash = $cookies[0]->getValue();
        // assert cache hash is different when currency id is different
        static::assertNotSame($firstHash, $secondHash);
    }

    public function testCacheCookieStaysTheSameIfEventPartsAreSortedDifferently(): void
    {
        $user = new UserEntity();
        $channelContext = static::createStub(ChannelContext::class);
        $channelContext->method('getUser')->willReturn($user);

        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT, $channelContext);

        $firstResponse = new Response();
        $this->cacheHeadersService->applyCacheHash($request, $channelContext, new Cart('cart'), $firstResponse);

        $firstCacheCookie = $firstResponse->headers->getCookies(ResponseHeaderBag::COOKIES_ARRAY)['']['/'][HttpCacheKeyGenerator::CONTEXT_CACHE_COOKIE];
        static::assertInstanceOf(Cookie::class, $firstCacheCookie);

        $this->addEventListener($this->eventDispatcher, HttpCacheCookieEvent::class, static function (HttpCacheCookieEvent $event): void {
            $ruleIds = $event->get('rule-ids');
            self::assertIsArray($ruleIds);
            $event->remove('rule-ids');
            $event->add('rule-ids', $ruleIds);
        });

        $secondResponse = new Response();
        $this->cacheHeadersService->applyCacheHash($request, $channelContext, new Cart('cart'), $secondResponse);

        $secondCacheCookie = $secondResponse->headers->getCookies(ResponseHeaderBag::COOKIES_ARRAY)['']['/'][HttpCacheKeyGenerator::CONTEXT_CACHE_COOKIE];
        static::assertInstanceOf(Cookie::class, $secondCacheCookie);

        static::assertSame($firstCacheCookie->getValue(), $secondCacheCookie->getValue());
    }

    public function testCacheCookieHasNoCacheValueIfSetInEvent(): void
    {
        $user = new UserEntity();
        $channelContext = static::createStub(ChannelContext::class);
        $channelContext->method('getUser')->willReturn($user);

        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT, $channelContext);

        $firstResponse = new Response();
        $this->cacheHeadersService->applyCacheHash($request, $channelContext, new Cart('cart'), $firstResponse);

        $firstCacheCookie = $firstResponse->headers->getCookies(ResponseHeaderBag::COOKIES_ARRAY)['']['/'][HttpCacheKeyGenerator::CONTEXT_CACHE_COOKIE];
        static::assertInstanceOf(Cookie::class, $firstCacheCookie);

        $this->addEventListener($this->eventDispatcher, HttpCacheCookieEvent::class, static function (HttpCacheCookieEvent $event): void {
            $event->isCacheable = false;
        });

        $secondResponse = new Response();
        $result = $this->cacheHeadersService->applyCacheHash($request, $channelContext, new Cart('cart'), $secondResponse);
        static::assertInstanceOf(HttpCacheCookieEvent::class, $result);

        $secondCacheCookie = $secondResponse->headers->getCookies(ResponseHeaderBag::COOKIES_ARRAY)['']['/'][HttpCacheKeyGenerator::CONTEXT_CACHE_COOKIE];
        static::assertInstanceOf(Cookie::class, $secondCacheCookie);

        static::assertNotSame($firstCacheCookie->getValue(), $secondCacheCookie->getValue());
        static::assertSame(HttpCacheCookieEvent::NOT_CACHEABLE, $secondCacheCookie->getValue());
        static::assertSame(HttpCacheCookieEvent::NOT_CACHEABLE, $result->getHash());
    }

    public function testSetLanguageCurrencyHeaders(): void
    {
        $response = new Response();

        $context = $this->createMock(ChannelContext::class);
        $context->expects($this->once())->method('getLanguageId')->willReturn('language-id');
        $context->expects($this->once())->method('getCurrencyId')->willReturn('currency-id');

        $this->cacheHeadersService->applyCacheHeaders($context, $response);

        static::assertSame('language-id', $response->headers->get(PlatformRequest::HEADER_LANGUAGE_ID));
        static::assertSame('currency-id', $response->headers->get(PlatformRequest::HEADER_CURRENCY_ID));

        static::assertTrue($response->headers->has(PlatformRequest::HEADER_LANGUAGE_ID), 'Vary header should always be set');
        $vary = $response->headers->all('vary');
        static::assertCount(5, $vary);
        static::assertContains(PlatformRequest::HEADER_ACCESS_KEY, $vary);
        static::assertContains(PlatformRequest::HEADER_LANGUAGE_ID, $vary);
        static::assertContains(PlatformRequest::HEADER_CURRENCY_ID, $vary);
        static::assertContains(PlatformRequest::HEADER_INCLUDE_SEO_URLS, $vary);
        static::assertContains(HttpCacheKeyGenerator::CONTEXT_CACHE_COOKIE, $vary);
    }

    public function testCustomCacheRelevantCookiesInfluenceTheStateCookie(): void
    {
        $extensionDispatcher = new ExtensionDispatcher($this->eventDispatcher);
        $cacheHeadersService = new CacheHeadersService(
            $extensionDispatcher,
            new CacheRelevantRulesResolver($extensionDispatcher),
            ['my-custom-cookie'],
            $this->eventDispatcher,
        );

        $request = new Request();
        $channelContextMock = static::createStub(ChannelContext::class);
        $channelContextMock->method('getChannel')->willReturn((new ChannelEntity())->assign([
            'currencyId' => Defaults::CURRENCY,
            'languageId' => Defaults::LANGUAGE_SYSTEM,
            'countryId' => 'country-default',
            'paymentMethodId' => 'payment-default',
            'shippingMethodId' => 'shipping-default',
        ]));
        $channelContextMock->method('getCurrencyId')->willReturn(Defaults::CURRENCY);
        $channelContextMock->method('getShippingLocation')->willReturn(self::createShippingLocation());
        $channelContextMock->method('getPaymentMethod')->willReturn((new PaymentMethodEntity())->assign(['id' => 'payment-default']));
        $channelContextMock->method('getShippingMethod')->willReturn((new ShippingMethodEntity())->assign(['id' => 'shipping-default']));
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT, $channelContextMock);

        $response = new Response();

        $cacheHeadersService->applyCacheHash($request, $channelContextMock, new Cart('cart'), $response);

        $cookies = $response->headers->getCookies();
        static::assertCount(0, $cookies);

        $request->cookies->set('my-custom-cookie', 'foo');

        $cacheHeadersService->applyCacheHash($request, $channelContextMock, new Cart('cart'), $response);

        $cookies = $response->headers->getCookies();
        static::assertNotCount(0, $cookies);
        // assert cache hash exist when customCookie is set
        static::assertSame(HttpCacheKeyGenerator::CONTEXT_CACHE_COOKIE, $cookies[0]->getName());
        $firstHash = $cookies[0]->getValue();

        $request->cookies->set('my-custom-cookie', 'bar');

        $cacheHeadersService->applyCacheHash($request, $channelContextMock, new Cart('cart'), $response);

        $cookies = $response->headers->getCookies();
        static::assertNotCount(0, $cookies);
        static::assertSame(HttpCacheKeyGenerator::CONTEXT_CACHE_COOKIE, $cookies[0]->getName());
        $secondHash = $cookies[0]->getValue();
        // assert cache hash is different when custom cookie is different
        static::assertNotSame($firstHash, $secondHash);
    }

    private function createCacheHashContext(
        string $languageId,
        string $shippingCountryId = 'country-default',
        string $paymentMethodId = 'payment-default',
        string $shippingMethodId = 'shipping-default'
    ): ChannelContext {
        $channelContext = static::createStub(ChannelContext::class);
        $channelContext->method('getUser')->willReturn(null);
        $channelContext->method('getRuleIds')->willReturn([]);
        $channelContext->method('getRuleIdsByAreas')->willReturn([]);
        $channelContext->method('getVersionId')->willReturn(Defaults::LIVE_VERSION);
        $channelContext->method('getCurrencyId')->willReturn(Defaults::CURRENCY);
        $channelContext->method('getLanguageId')->willReturn($languageId);
        $channelContext->method('getTaxState')->willReturn('gross');
        $channelContext->method('getShippingLocation')->willReturn(self::createShippingLocation($shippingCountryId));
        $channelContext->method('getPaymentMethod')->willReturn((new PaymentMethodEntity())->assign(['id' => $paymentMethodId]));
        $channelContext->method('getShippingMethod')->willReturn((new ShippingMethodEntity())->assign(['id' => $shippingMethodId]));
        $channelContext->method('getChannel')->willReturn(
            (new ChannelEntity())->assign([
                'currencyId' => Defaults::CURRENCY,
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'countryId' => 'country-default',
                'paymentMethodId' => 'payment-default',
                'shippingMethodId' => 'shipping-default',
            ])
        );

        return $channelContext;
    }

    private static function createShippingLocation(string $countryId = 'country-default'): ShippingLocation
    {
        return ShippingLocation::createFromCountry(
            (new CountryEntity())->assign(['id' => $countryId])
        );
    }

    private function createFilledCart(): Cart
    {
        $cart = new Cart('filled');
        $cart->add(new LineItem('test', 'test', 'test'));

        return $cart;
    }
}
