<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Channel\Context;

use Contena\Core\Defaults;
use Contena\Core\Framework\Api\Context\SystemSource;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\ChannelException;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Contena\Core\System\Channel\Context\ChannelContextPersister;
use Contena\Core\System\Channel\Context\ChannelContextService;
use Contena\Core\System\Channel\Context\ChannelContextServiceParameters;
use Contena\Core\System\Channel\Context\ChannelRuleLoader;
use Contena\Core\System\Channel\Event\ChannelContextCreatedEvent;
use Contena\Core\Test\Generator;
use Contena\Core\Test\TestDefaults;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;

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

        $factory = $this->createMock(ChannelContextFactory::class);
        $factory->expects($this->once())
            ->method('create')
            ->with(
                static::logicalNot(static::equalTo($expiredToken)),
                TestDefaults::CHANNEL,
                [
                    ChannelContextService::LANGUAGE_ID => Defaults::LANGUAGE_SYSTEM,
                    'expired' => true,
                ],
            )
            ->willReturn($context);

        $service = new ChannelContextService(
            $factory,
            static::createStub(ChannelRuleLoader::class),
            $persister,
            static::createStub(EventDispatcherInterface::class),
            $this->requestStack,
        );

        $service->get(new ChannelContextServiceParameters(TestDefaults::CHANNEL, $expiredToken, Defaults::LANGUAGE_SYSTEM));
    }

    public function testTokenNotExpired(): void
    {
        $memberId = Uuid::randomHex();
        $noneExpiringToken = Uuid::randomHex();

        $persister = static::createStub(ChannelContextPersister::class);
        $persister->method('load')->willReturn(['expired' => false, ChannelContextService::MEMBER_ID => $memberId]);

        $context = Generator::generateChannelContext();

        $factory = $this->createMock(ChannelContextFactory::class);
        $factory->expects($this->once())
            ->method('create')
            ->with(
                $noneExpiringToken,
                TestDefaults::CHANNEL,
                [
                    ChannelContextService::LANGUAGE_ID => Defaults::LANGUAGE_SYSTEM,
                    ChannelContextService::MEMBER_ID => $memberId,
                    'expired' => false,
                ],
            )
            ->willReturn($context);

        $service = new ChannelContextService(
            $factory,
            static::createStub(ChannelRuleLoader::class),
            $persister,
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
        $memberId = Uuid::randomHex();
        $context = Generator::generateChannelContext();
        $session = [
            $option => $staleId,
            ChannelContextService::MEMBER_ID => $memberId,
        ];
        $call = 0;

        $persister = $this->createMock(ChannelContextPersister::class);
        $persister->expects($this->once())
            ->method('load')
            ->with($token, TestDefaults::CHANNEL)
            ->willReturn($session);
        $persister->expects($this->once())
            ->method('save')
            ->with($token, [$option => null], TestDefaults::CHANNEL, $memberId);

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

        $service = $this->createContextService($factory, $persister);

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

        $service = $this->createContextService($factory, $persister);

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

        $service = $this->createContextService($factory, $persister);

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

        $service = new ChannelContextService(
            $factory,
            static::createStub(ChannelRuleLoader::class),
            $persister,
            $eventDispatcher,
            $this->requestStack,
        );

        $service->get(new ChannelContextServiceParameters(TestDefaults::CHANNEL, $token));
    }

    public function testAddStatesFromOriginalContext(): void
    {
        $token = 'test-token';
        $originalContext = new Context(new SystemSource());
        $originalContext->addState(Context::ELASTICSEARCH_EXPLAIN_MODE);

        $context = Generator::generateChannelContext();

        $session = [
            'foo' => 'bar',
            ChannelContextService::LANGUAGE_ID => Defaults::LANGUAGE_SYSTEM,
            ChannelContextService::ORIGINAL_CONTEXT => $originalContext,
        ];

        $persister = static::createStub(ChannelContextPersister::class);
        $persister->method('load')->willReturn(['foo' => 'bar']);

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
            static::createStub(ChannelRuleLoader::class),
            $persister,
            $dispatcher,
            $this->requestStack,
        );

        $result = $service->get(new ChannelContextServiceParameters(
            TestDefaults::CHANNEL,
            $token,
            Defaults::LANGUAGE_SYSTEM,
            originalContext: $originalContext,
        ));

        static::assertTrue($result->hasState(Context::ELASTICSEARCH_EXPLAIN_MODE));
    }

    public function testCopiesRulesFromSession(): void
    {
        $ruleIds = ['rule-1', 'rule-2', 'rule-3'];
        $areaRuleIds = ['area' => ['rule-2']];

        $persister = static::createStub(ChannelContextPersister::class);
        $persister->method('load')->willReturn([
            ChannelContextService::RULE_IDS => $ruleIds,
            ChannelContextService::AREA_RULE_IDS => $areaRuleIds,
        ]);

        $context = Generator::generateChannelContext();

        $factory = $this->createMock(ChannelContextFactory::class);
        $factory->expects($this->once())->method('create')->willReturn($context);

        $service = new ChannelContextService(
            $factory,
            static::createStub(ChannelRuleLoader::class),
            $persister,
            static::createStub(EventDispatcherInterface::class),
            $this->requestStack,
        );

        $result = $service->get(new ChannelContextServiceParameters(TestDefaults::CHANNEL, Uuid::randomHex()));

        static::assertSame($ruleIds, $result->getRuleIds());
        static::assertSame($areaRuleIds, $result->getAreaRuleIds());
    }

    public function testLoadsRulesForCreatedContext(): void
    {
        $context = Generator::generateChannelContext();

        $factory = $this->createMock(ChannelContextFactory::class);
        $factory->expects($this->once())->method('create')->willReturn($context);

        $ruleLoader = $this->createMock(ChannelRuleLoader::class);
        $ruleLoader->expects($this->once())->method('load')->with($context);

        $service = new ChannelContextService(
            $factory,
            $ruleLoader,
            static::createStub(ChannelContextPersister::class),
            static::createStub(EventDispatcherInterface::class),
            $this->requestStack,
        );

        $service->get(new ChannelContextServiceParameters(TestDefaults::CHANNEL, Uuid::randomHex()));
    }

    private function createContextService(ChannelContextFactory $factory, ChannelContextPersister $persister): ChannelContextService
    {
        return new ChannelContextService(
            $factory,
            static::createStub(ChannelRuleLoader::class),
            $persister,
            static::createStub(EventDispatcherInterface::class),
            $this->requestStack,
        );
    }
}
