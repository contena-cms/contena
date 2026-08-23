<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Channel\Context;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Defaults;
use Contena\Core\Framework\Api\Context\SystemSource;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Contena\Core\System\Channel\Context\ChannelContextPersister;
use Contena\Core\System\Channel\Context\ChannelContextService;
use Contena\Core\System\Channel\Context\ChannelContextServiceParameters;
use Contena\Core\System\Channel\Context\ChannelRuleLoader;
use Contena\Core\System\Channel\Event\ChannelContextCreatedEvent;
use Contena\Core\Test\Generator;
use Contena\Core\Test\TestDefaults;
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
}
