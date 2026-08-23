<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Flow\Dispatching;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Contena\Core\Content\Flow\Dispatching\FlowExecutor;
use Contena\Core\Content\Flow\Dispatching\FlowState;
use Contena\Core\Content\Flow\Dispatching\StorableFlow;
use Contena\Core\Content\Flow\Dispatching\Struct\IfSequence;
use Contena\Core\Content\Flow\Dispatching\Struct\Sequence;
use Contena\Core\Content\Flow\Telemetry\FlowMetricsInstrumentor;
use Contena\Core\Content\Flow\Telemetry\TriggerGroupResolver;
use Contena\Core\Content\Rule\AbstractRuleLoader;
use Contena\Core\Content\Rule\RuleCollection;
use Contena\Core\Content\Rule\RuleEntity;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\RuleAreas;
use Contena\Core\Framework\Event\ChannelContextAware;
use Contena\Core\Framework\Event\MemberAware;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\Rule\Rule;
use Contena\Core\Framework\Telemetry\Metrics\Meter;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Member\MemberEntity;
use Contena\Core\System\Member\Rule\MemberRequestedGroupRule;
use Contena\Core\Test\Generator;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[CoversClass(FlowExecutor::class)]
class FlowExecutorTest extends TestCase
{
    #[DataProvider('channelContextMemberDataProvider')]
    public function testExecuteIfWithMemberRuleScopeEvaluation(?MemberEntity $contextMember): void
    {
        $trueCaseSequence = new Sequence();
        $trueCaseSequence->assign(['sequenceId' => 'foobar']);
        $ruleId = Uuid::randomHex();
        $ifSequence = new IfSequence();
        $ifSequence->assign(['ruleId' => $ruleId, 'trueCase' => $trueCaseSequence]);

        $groupId = Uuid::randomHex();
        $member = new MemberEntity();
        $member->setRequestedGroupId($groupId);
        $contextMember?->setRequestedGroupId($groupId);

        $context = Context::createDefaultContext();
        $context->setRuleIds([$ruleId]);

        $flow = new StorableFlow('bar', $context);
        $flow->setFlowState(new FlowState());
        $flow->setData(MemberAware::MEMBER, $member);

        $flow->setData(
            ChannelContextAware::CHANNEL_CONTEXT,
            Generator::generateChannelContext(member: $contextMember),
        );

        $rule = new MemberRequestedGroupRule(Rule::OPERATOR_EQ, [$groupId]);
        $ruleEntity = new RuleEntity();
        $ruleEntity->setId($ruleId);
        $ruleEntity->setPayload($rule);
        $ruleEntity->setAreas([RuleAreas::FLOW_AREA]);

        $ruleLoader = $this->createMock(AbstractRuleLoader::class);
        $ruleLoader->expects($this->exactly($contextMember === null ? 1 : 0))
            ->method('load')
            ->willReturn(new RuleCollection([$ruleEntity]));

        $flowExecutor = new FlowExecutor(
            $ruleLoader,
            static::createStub(Connection::class),
            new ExtensionDispatcher(new EventDispatcher()),
            static::createStub(LoggerInterface::class),
            [],
            new FlowMetricsInstrumentor(static::createStub(Meter::class), new TriggerGroupResolver()),
        );

        $flowExecutor->executeSequence($ifSequence, $flow);

        static::assertSame($trueCaseSequence, $flow->getFlowState()->currentSequence);
    }

    public static function channelContextMemberDataProvider(): \Generator
    {
        yield 'no member in channel context from frontend' => [null];
        yield 'member in channel context from frontend' => [new MemberEntity()];
    }
}
