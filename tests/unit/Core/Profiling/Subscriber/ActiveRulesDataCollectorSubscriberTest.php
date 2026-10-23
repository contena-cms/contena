<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Profiling\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Rule\RuleCollection;
use Contena\Core\Content\Rule\RuleEntity;
use Contena\Core\Framework\Api\Context\SystemSource;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\Routing\Event\ChannelContextResolvedEvent;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Profiling\Subscriber\ActiveRulesDataCollectorSubscriber;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[CoversClass(ActiveRulesDataCollectorSubscriber::class)]
class ActiveRulesDataCollectorSubscriberTest extends TestCase
{
    public function testEvents(): void
    {
        static::assertSame(
            [
                ChannelContextResolvedEvent::class => 'onContextResolved',
            ],
            ActiveRulesDataCollectorSubscriber::getSubscribedEvents()
        );
    }

    public function testDataCollection(): void
    {
        $ruleId = Uuid::randomHex();

        $channelContext = static::createStub(ChannelContext::class);
        $context = new Context(new SystemSource(), [$ruleId]);
        $channelContext->method('getContext')->willReturn($context);
        $event = new ChannelContextResolvedEvent($channelContext, Uuid::randomHex());

        $activeRule = new RuleEntity();
        $activeRule->setId($ruleId);
        $activeRule->setName('Demo rule');
        $activeRule->setPriority(100);

        $ruleRepository = static::createStub(EntityRepository::class);
        $ruleRepository
            ->method('search')
            ->willReturn(new EntitySearchResult(
                'rule',
                1,
                new RuleCollection([$activeRule]),
                null,
                new Criteria(),
                Context::createDefaultContext()
            ));

        $subscriber = new ActiveRulesDataCollectorSubscriber($ruleRepository);
        $subscriber->onContextResolved($event);
        $subscriber->collect(new Request(), new Response());

        $data = $subscriber->getData();

        static::assertSame(1, $subscriber->getMatchingRuleCount());
        static::assertArrayHasKey($ruleId, $data);

        $rule = $data[$ruleId];
        static::assertInstanceOf(RuleEntity::class, $rule);
        static::assertSame(100, $rule->getPriority());
        static::assertSame('Demo rule', $rule->getName());

        $subscriber->reset();

        static::assertSame(0, $subscriber->getMatchingRuleCount());
    }

    public function testEmptyRuleIds(): void
    {
        $channelContext = static::createStub(ChannelContext::class);
        $context = new Context(new SystemSource(), []);
        $channelContext->method('getContext')->willReturn($context);
        $event = new ChannelContextResolvedEvent($channelContext, Uuid::randomHex());

        $ruleRepository = $this->createMock(EntityRepository::class);
        $ruleRepository
            ->expects($this->never())
            ->method('search');

        $subscriber = new ActiveRulesDataCollectorSubscriber($ruleRepository);
        $subscriber->onContextResolved($event);
        $subscriber->collect(new Request(), new Response());
    }

    public function testTemplate(): void
    {
        static::assertSame('@Profiling/Collector/rules.html.twig', ActiveRulesDataCollectorSubscriber::getTemplate());
    }
}
