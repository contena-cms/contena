<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Channel\Context;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\Cart;
use Contena\Core\Checkout\Cart\CartCalculator;
use Contena\Core\Checkout\Cart\Channel\CartService;
use Contena\Core\Defaults;
use Contena\Core\Framework\Api\Context\SystemSource;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\RuleAreas;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\ChannelException;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Contena\Core\System\Channel\Context\ChannelContextPersister;
use Contena\Core\System\Channel\Context\ChannelContextService;
use Contena\Core\System\Channel\Context\ChannelContextServiceParameters;
use Contena\Core\System\Channel\Event\ChannelContextCreatedEvent;
use Contena\Core\Test\Generator;
use Contena\Core\Test\TestDefaults;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * @internal
 */
#[CoversClass(ChannelContextService::class)]
class ChannelContextServiceTest extends TestCase
{
    private RequestStack $requestStack;

    protected function setUp(): void
    {
        $this->requestStack = new RequestStack();
    }

    public function testTokenExpired(): void
    {
        $persister = static::createStub(ChannelContextPersister::class);
        $persister->method('load')->willReturn(['expired' => true]);

        $expiredToken = Uuid::randomHex();

        $context = Generator::generateChannelContext();
        $context->setRuleIds(['rule-1', 'rule-2']);
        $context->setAreaRuleIds([RuleAreas::BLOG_AREA => ['rule-1'], RuleAreas::PROMOTION_AREA => ['rule-2']]);

        $factory = $this->createMock(ChannelContextFactory::class);
        $factory->expects($this->once())
            ->method('create')
            ->with(
                static::logicalNot(static::equalTo($expiredToken)),
                TestDefaults::CHANNEL,
                [
                    ChannelContextService::LANGUAGE_ID => Defaults::LANGUAGE_SYSTEM,
                    'expired' => true,
                ]
            )
            ->willReturn($context);

        $cart = new Cart($expiredToken);
        $cart->setRuleIds(['rule-1', 'rule-2']);

        $cartCalculator = $this->createMock(CartCalculator::class);
        $cartCalculator
            ->expects($this->once())
            ->method('calculateByToken')
            ->with(static::logicalNot(static::equalTo($expiredToken)), $context)
            ->willReturn($cart);

        $cartService = $this->createMock(CartService::class);
        $cartService
            ->expects($this->once())
            ->method('setCart')
            ->with($cart);

        $request = $this->setupSessionAndRequest();

        $service = new ChannelContextService(
            $factory,
            $cartCalculator,
            $persister,
            $cartService,
            static::createStub(EventDispatcherInterface::class),
            $this->requestStack,
        );

        $service->get(new ChannelContextServiceParameters(TestDefaults::CHANNEL, $expiredToken, Defaults::LANGUAGE_SYSTEM));

        $session = $request->getSession();

        static::assertSame($context->getRuleIds(), $session->get(ChannelContextService::RULE_IDS));
        static::assertSame($context->getAreaRuleIds(), $session->get(ChannelContextService::AREA_RULE_IDS));
    }

    public function testTokenNotExpired(): void
    {
        $userId = Uuid::randomHex();
        $noneExpiringToken = Uuid::randomHex();

        $persister = static::createStub(ChannelContextPersister::class);
        $persister->method('load')->willReturn(['expired' => false, ChannelContextService::CUSTOMER_ID => $userId]);

        $context = Generator::generateChannelContext();

        $factory = $this->createMock(ChannelContextFactory::class);
        $factory->expects($this->once())
            ->method('create')
            ->with(
                $noneExpiringToken,
                TestDefaults::CHANNEL,
                [
                    ChannelContextService::LANGUAGE_ID => Defaults::LANGUAGE_SYSTEM,
                    ChannelContextService::CUSTOMER_ID => $userId,
                    'expired' => false,
                ]
            )
            ->willReturn($context);

        $cart = new Cart($noneExpiringToken);
        $cart->setRuleIds(['rule-3', 'rule-4']);

        $cartCalculator = $this->createMock(CartCalculator::class);
        $cartCalculator
            ->expects($this->once())
            ->method('calculateByToken')
            ->with($noneExpiringToken, $context)
            ->willReturn($cart);

        $cartService = $this->createMock(CartService::class);
        $cartService
            ->expects($this->once())
            ->method('setCart')
            ->with($cart);

        $this->setupSessionAndRequest();

        $service = new ChannelContextService(
            $factory,
            $cartCalculator,
            $persister,
            $cartService,
            static::createStub(EventDispatcherInterface::class),
            $this->requestStack,
        );

        $service->get(new ChannelContextServiceParameters(TestDefaults::CHANNEL, $noneExpiringToken, Defaults::LANGUAGE_SYSTEM));
    }

    #[DataProvider('stalePersistedOptionProvider')]
    public function testFallsBackWhenPersistedOptionIsNoLongerAvailable(string $option, ChannelException $exception, ?string $fallbackCurrencyId): void
    {
        $token = Uuid::randomHex();
        $staleId = Uuid::randomHex();
        $userId = Uuid::randomHex();
        $context = Generator::generateChannelContext();
        $session = [
            $option => $staleId,
            ChannelContextService::CUSTOMER_ID => $userId,
        ];
        $call = 0;

        $persister = $this->createMock(ChannelContextPersister::class);
        $persister->expects($this->once())
            ->method('load')
            ->with($token, TestDefaults::CHANNEL)
            ->willReturn($session);
        $persister->expects($this->once())
            ->method('save')
            ->with($token, [$option => null], TestDefaults::CHANNEL, $userId);

        $factory = $this->createMock(ChannelContextFactory::class);
        $factory->expects($this->exactly(2))
            ->method('create')
            ->willReturnCallback(function (string $actualToken, string $channelId, array $options) use ($token, $option, $staleId, $exception, $context, $fallbackCurrencyId, &$call): ChannelContext {
                static::assertSame($token, $actualToken);
                static::assertSame(TestDefaults::CHANNEL, $channelId);

                if ($call++ === 0) {
                    static::assertSame($staleId, $options[$option]);

                    throw $exception;
                }

                if ($fallbackCurrencyId !== null) {
                    static::assertSame($fallbackCurrencyId, $options[ChannelContextService::CURRENCY_ID]);
                } else {
                    static::assertArrayNotHasKey($option, $options);
                }

                return $context;
            });

        $service = $this->createContextService($factory, $persister, $token);

        static::assertSame($context, $service->get(new ChannelContextServiceParameters(
            channelId: TestDefaults::CHANNEL,
            token: $token,
            currencyId: $fallbackCurrencyId,
        )));
    }

    public static function stalePersistedOptionProvider(): \Generator
    {
        $unavailableLanguageId = Uuid::randomHex();
        yield 'language' => [
            ChannelContextService::LANGUAGE_ID,
            ChannelException::providedLanguageNotAvailable($unavailableLanguageId, [Defaults::LANGUAGE_SYSTEM]),
            null,
        ];

        yield 'currency' => [
            ChannelContextService::CURRENCY_ID,
            ChannelException::currencyNotFound(Uuid::randomHex()),
            null,
        ];

        yield 'currency uses domain fallback' => [
            ChannelContextService::CURRENCY_ID,
            ChannelException::currencyNotFound(Uuid::randomHex()),
            Uuid::randomHex(),
        ];
    }

    public function testFallsBackWhenBothPersistedOptionsAreNoLongerAvailable(): void
    {
        $token = Uuid::randomHex();
        $context = Generator::generateChannelContext();
        $session = [
            ChannelContextService::LANGUAGE_ID => Uuid::randomHex(),
            ChannelContextService::CURRENCY_ID => Uuid::randomHex(),
        ];
        $persistedOptions = [];

        $persister = $this->createMock(ChannelContextPersister::class);
        $persister->method('load')->willReturn($session);
        $persister->expects($this->exactly(2))
            ->method('save')
            ->willReturnCallback(function (string $actualToken, array $options, string $channelId) use (&$persistedOptions, $token): void {
                static::assertSame($token, $actualToken);
                static::assertSame(TestDefaults::CHANNEL, $channelId);
                $persistedOptions[] = $options;
            });

        $factory = $this->createMock(ChannelContextFactory::class);
        $factory->expects($this->exactly(3))
            ->method('create')
            ->willReturnCallback(function (string $actualToken, string $channelId, array $options) use ($token, $session, $context): ChannelContext {
                static $call = 0;

                static::assertSame($token, $actualToken);
                static::assertSame(TestDefaults::CHANNEL, $channelId);

                if ($call++ === 0) {
                    static::assertSame($session, $options);

                    throw ChannelException::providedLanguageNotAvailable($session[ChannelContextService::LANGUAGE_ID], [Defaults::LANGUAGE_SYSTEM]);
                }

                if ($call === 2) {
                    static::assertArrayNotHasKey(ChannelContextService::LANGUAGE_ID, $options);
                    static::assertSame($session[ChannelContextService::CURRENCY_ID], $options[ChannelContextService::CURRENCY_ID]);

                    throw ChannelException::currencyNotFound($session[ChannelContextService::CURRENCY_ID]);
                }

                static::assertArrayNotHasKey(ChannelContextService::LANGUAGE_ID, $options);
                static::assertArrayNotHasKey(ChannelContextService::CURRENCY_ID, $options);

                return $context;
            });

        $service = $this->createContextService($factory, $persister, $token);

        static::assertSame($context, $service->get(new ChannelContextServiceParameters(TestDefaults::CHANNEL, $token)));
        static::assertSame([
            [ChannelContextService::LANGUAGE_ID => null],
            [ChannelContextService::CURRENCY_ID => null],
        ], $persistedOptions);
    }

    public function testDoesNotFallbackForExplicitLanguage(): void
    {
        $token = Uuid::randomHex();
        $languageId = Uuid::randomHex();
        $exception = ChannelException::providedLanguageNotAvailable($languageId, [Defaults::LANGUAGE_SYSTEM]);

        $persister = static::createStub(ChannelContextPersister::class);
        $persister->method('load')->willReturn([ChannelContextService::LANGUAGE_ID => Uuid::randomHex()]);

        $factory = $this->createMock(ChannelContextFactory::class);
        $factory->expects($this->once())
            ->method('create')
            ->with($token, TestDefaults::CHANNEL, [ChannelContextService::LANGUAGE_ID => $languageId])
            ->willThrowException($exception);

        $service = $this->createContextService($factory, $persister, $token);

        $this->expectExceptionObject($exception);
        $service->get(new ChannelContextServiceParameters(TestDefaults::CHANNEL, $token, $languageId));
    }

    public function testDispatchesChannelContextCreatedEvent(): void
    {
        $token = 'test-token';
        $context = Generator::generateChannelContext();
        $session = ['foo' => 'bar'];

        $persister = static::createStub(ChannelContextPersister::class);
        $persister->method('load')->willReturn($session);

        $factory = $this->createMock(ChannelContextFactory::class);
        $factory->expects($this->once())
            ->method('create')
            ->with($token, TestDefaults::CHANNEL, $session)
            ->willReturn($context);

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with(new ChannelContextCreatedEvent($context, $token, $session));

        $this->setupSessionAndRequest();

        $service = new ChannelContextService(
            $factory,
            static::createStub(CartCalculator::class),
            $persister,
            static::createStub(CartService::class),
            $eventDispatcher,
            $this->requestStack,
        );

        $service->get(new ChannelContextServiceParameters(TestDefaults::CHANNEL, $token));
    }

    #[DataProvider('skipCartCalculationIfAlreadyDoneAndESISubrequestProvider')]
    public function testSkipCartCalculationIfAlreadyDoneAndESISubrequest(Request $request, bool $hasCart, bool $expectCalculation): void
    {
        $userId = Uuid::randomHex();
        $token = Uuid::randomHex();
        $cart = new Cart($token);

        $persister = static::createStub(ChannelContextPersister::class);
        $persister->method('load')->willReturn(['expired' => false, ChannelContextService::CUSTOMER_ID => $userId]);

        $context = Generator::generateChannelContext();

        $factory = $this->createMock(ChannelContextFactory::class);
        $factory
            ->expects($this->once())
            ->method('create')
            ->willReturn($context);

        $cartService = $this->createMock(CartService::class);
        $cartService
            ->expects($this->once())
            ->method('hasCart')
            ->with($token)
            ->willReturn($hasCart);

        $cartCalculator = $this->createMock(CartCalculator::class);

        if ($expectCalculation) {
            $cartCalculator
                ->expects($this->once())
                ->method('calculateByToken')
                ->with($token, $context)
                ->willReturn($cart);

            $cartService
                ->expects($this->once())
                ->method('setCart')
                ->with($cart);
        } else {
            $cartCalculator
                ->expects($this->never())
                ->method(static::anything());

            $cartService
                ->expects($this->never())
                ->method('setCart');
        }

        $session = new Session(new MockArraySessionStorage());
        $session->set(ChannelContextService::RULE_IDS, ['rule-1', 'rule-2']);
        $session->set(ChannelContextService::AREA_RULE_IDS, [RuleAreas::BLOG_AREA => ['rule-1'], RuleAreas::PROMOTION_AREA => ['rule-2']]);

        $request->setSession($session);
        $this->requestStack->push($request);

        $service = new ChannelContextService(
            $factory,
            $cartCalculator,
            $persister,
            $cartService,
            static::createStub(EventDispatcherInterface::class),
            $this->requestStack,
        );

        $context = $service->get(new ChannelContextServiceParameters(TestDefaults::CHANNEL, $token, Defaults::LANGUAGE_SYSTEM));

        static::assertSame($session->get(ChannelContextService::RULE_IDS), $context->getRuleIds());
        static::assertSame($session->get(ChannelContextService::AREA_RULE_IDS), $context->getAreaRuleIds());
    }

    public static function skipCartCalculationIfAlreadyDoneAndESISubrequestProvider(): \Generator
    {
        yield 'esi request with cart => false' => [new Request(attributes: ['_esi' => true]), true, false];
        yield 'esi request without cart => true' => [new Request(attributes: ['_esi' => true]), false, true];
        yield 'no esi request but cart => true' => [new Request(), true, true];
        yield 'no esi request and no cart => true' => [new Request(), false, true];
    }

    public function testAddStatesFromOriginalContext(): void
    {
        $token = 'test-token';
        $originalContext = new Context(new SystemSource());
        $originalContext->addState(Context::ELASTICSEARCH_EXPLAIN_MODE);
        $context = $this->createMock(ChannelContext::class);
        $context->method('withPermissions')->willReturn(new Cart($token));
        $context->expects($this->once())
            ->method('addState')
            ->with(Context::ELASTICSEARCH_EXPLAIN_MODE);
        $session = [
            'foo' => 'bar',
            'languageId' => Defaults::LANGUAGE_SYSTEM,
            'originalContext' => $originalContext,
        ];

        $persister = static::createStub(ChannelContextPersister::class);
        $persister->method('load')->willReturn($session);

        $factory = $this->createMock(ChannelContextFactory::class);
        $factory->expects($this->once())
            ->method('create')
            ->with($token, TestDefaults::CHANNEL, $session)
            ->willReturn($context);

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects($this->once())
            ->method('dispatch')
            ->with(new ChannelContextCreatedEvent($context, $token, $session));

        $service = new ChannelContextService(
            $factory,
            static::createStub(CartCalculator::class),
            $persister,
            static::createStub(CartService::class),
            $dispatcher,
            $this->requestStack,
        );

        $this->setupSessionAndRequest();

        $service->get(new ChannelContextServiceParameters(
            TestDefaults::CHANNEL,
            $token,
            Defaults::LANGUAGE_SYSTEM,
            null,
            null,
            $originalContext,
        ));
    }

    public function testESIRequestsCopyRulesFromSession(): void
    {
        $token = Uuid::randomHex();
        $ruleIds = ['rule-1', 'rule-2', 'rule-3'];

        $persister = static::createStub(ChannelContextPersister::class);
        $persister->method('load')->willReturn(['expired' => false, ChannelContextService::CUSTOMER_ID => Uuid::randomHex()]);

        $context = $this->createMock(ChannelContext::class);
        $factory = $this->createMock(ChannelContextFactory::class);
        $factory
            ->expects($this->once())
            ->method('create')
            ->willReturn($context);

        $cartService = $this->createMock(CartService::class);
        $cartService
            ->expects($this->once())
            ->method('hasCart')
            ->with($token)
            ->willReturn(true);

        $context
            ->expects($this->once())
            ->method('setRuleIds')
            ->with($ruleIds);

        $cartCalculator = $this->createMock(CartCalculator::class);
        $cartCalculator
            ->expects($this->never())
            ->method('calculateByToken');
        $cartService
            ->expects($this->never())
            ->method('setCart');

        $this->setupSessionAndRequest([
            'sw-rule-ids' => $ruleIds,
        ], [
            '_esi' => true,
        ]);

        $service = new ChannelContextService(
            $factory,
            $cartCalculator,
            $persister,
            $cartService,
            static::createStub(EventDispatcherInterface::class),
            $this->requestStack,
        );

        $service->get(new ChannelContextServiceParameters(TestDefaults::CHANNEL, $token, Defaults::LANGUAGE_SYSTEM));
    }

    /**
     * @param array<string, mixed> $sessionData
     * @param array<string, mixed> $requestAttributes
     */
    private function setupSessionAndRequest(array $sessionData = [], array $requestAttributes = []): Request
    {
        $session = new Session(new MockArraySessionStorage());

        foreach ($sessionData as $key => $value) {
            $session->set($key, $value);
        }

        $request = new Request(attributes: $requestAttributes);
        $request->setSession($session);
        $this->requestStack->push($request);

        return $request;
    }

    private function createContextService(ChannelContextFactory $factory, ChannelContextPersister $persister, string $token): ChannelContextService
    {
        $cartCalculator = static::createStub(CartCalculator::class);
        $cartCalculator->method('calculateByToken')->willReturn(new Cart($token));

        return new ChannelContextService(
            $factory,
            $cartCalculator,
            $persister,
            static::createStub(CartService::class),
            static::createStub(EventDispatcherInterface::class),
            $this->requestStack,
        );
    }
}
