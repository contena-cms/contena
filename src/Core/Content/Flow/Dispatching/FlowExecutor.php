<?php declare(strict_types=1);

namespace Contena\Core\Content\Flow\Dispatching;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Contena\Core\Content\Flow\Dispatching\Action\FlowAction;
use Contena\Core\Content\Flow\Dispatching\Struct\ActionSequence;
use Contena\Core\Content\Flow\Dispatching\Struct\Flow;
use Contena\Core\Content\Flow\Dispatching\Struct\IfSequence;
use Contena\Core\Content\Flow\Dispatching\Struct\Sequence;
use Contena\Core\Content\Flow\Extension\FlowExecutorExtension;
use Contena\Core\Content\Flow\Rule\MemberRuleScope;
use Contena\Core\Content\Flow\Telemetry\FlowMetricsInstrumentor;
use Contena\Core\Content\Rule\AbstractRuleLoader;
use Contena\Core\Framework\DataAbstractionLayer\Doctrine\RetryableTransaction;
use Contena\Core\Framework\Event\ChannelContextAware;
use Contena\Core\Framework\Event\MemberAware;
use Contena\Core\Framework\Event\UserAware;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\Rule\Rule;
use Contena\Core\Framework\Rule\RuleScope;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Member\MemberEntity;
use Contena\Core\System\User\Aggregate\UserRecovery\UserRecoveryEntity;
use Contena\Core\System\User\Rule\UserRuleScope;

/**
 * @internal
 */
class FlowExecutor
{
    /**
     * @var array<string, FlowAction>
     */
    private readonly array $actions;

    /**
     * @param iterable<string, FlowAction> $actions
     */
    public function __construct(
        private readonly AbstractRuleLoader $ruleLoader,
        private readonly Connection $connection,
        private readonly ExtensionDispatcher $extensions,
        private readonly LoggerInterface $logger,
        iterable $actions,
        private readonly FlowMetricsInstrumentor $flowMetrics,
    ) {
        $this->actions = $actions instanceof \Traversable ? iterator_to_array($actions) : $actions;
    }

    /**
     * @param list<array{id: string, name: string, payload: Flow}> $flowHolders
     */
    public function executeFlows(array $flowHolders, StorableFlow $event): void
    {
        foreach ($flowHolders as $holder) {
            try {
                $this->execute($holder['payload'], $event);
            } catch (\Throwable $exception) {
                $this->logger->error('Could not execute flow.', [
                    'flowId' => $holder['id'],
                    'flowName' => $holder['name'],
                    'exception' => $exception,
                ]);
            }
        }
    }

    public function execute(Flow $flow, StorableFlow $event): void
    {
        // Metric covers the extension too: an extension may stop propagation and replace execution entirely,
        // while every flow is still measured including extension pre/post overhead.
        $this->flowMetrics->measureExecution(
            $event,
            fn () => $this->extensions->publish(
                FlowExecutorExtension::NAME,
                new FlowExecutorExtension($flow, $event),
                fn (): null => $this->executeFlow($flow, $event),
            ),
        );
    }

    public function executeSequence(?Sequence $sequence, StorableFlow $event): void
    {
        if ($sequence === null || $event->getFlowState()->stop) {
            return;
        }

        $event->getFlowState()->currentSequence = $sequence;
        if ($sequence instanceof IfSequence) {
            $this->executeSequence($this->ruleMatches($event, $sequence->ruleId) ? $sequence->trueCase : $sequence->falseCase, $event);

            return;
        }

        if (!$sequence instanceof ActionSequence) {
            return;
        }

        $event->setConfig($sequence->config);
        $action = $this->actions[$sequence->action] ?? null;
        if ($action instanceof TransactionalAction) {
            RetryableTransaction::transactional($this->connection, static fn () => $action->handleFlow($event));
        } elseif ($action instanceof FlowAction) {
            $action->handleFlow($event);
        }

        $this->executeSequence($sequence->nextAction, $event);
    }

    private function executeFlow(Flow $flow, StorableFlow $event): null
    {
        $state = new FlowState();
        $state->flowId = $flow->getId();
        $event->setFlowState($state);

        foreach ($flow->getSequences() as $sequence) {
            $this->executeSequence($sequence, $event);
            if ($state->stop) {
                break;
            }
        }

        return null;
    }

    private function ruleMatches(StorableFlow $event, string $ruleId): bool
    {
        $baseContextEvaluation = \in_array($ruleId, $event->getContext()->getRuleIds(), true);

        if ($event->hasData(MemberAware::MEMBER)) {
            $channelContext = $event->getData(ChannelContextAware::CHANNEL_CONTEXT);

            if (!$channelContext instanceof ChannelContext || $channelContext->getMember() !== null) {
                return $baseContextEvaluation;
            }

            $member = $event->getData(MemberAware::MEMBER);
            if (!$member instanceof MemberEntity) {
                return $baseContextEvaluation;
            }

            $entity = $this->ruleLoader->load($event->getContext())->filterForFlow()->get($ruleId);
            $rule = $entity?->getPayload();

            return $rule instanceof Rule
                ? $rule->match(new MemberRuleScope($member, $channelContext))
                : $baseContextEvaluation;
        }

        if ($baseContextEvaluation) {
            return true;
        }

        $entity = $this->ruleLoader->load($event->getContext())->get($ruleId);
        $rule = $entity?->getPayload();

        return $rule instanceof Rule && $rule->match($this->createRuleScope($event));
    }

    private function createRuleScope(StorableFlow $event): RuleScope
    {
        $userRecovery = $event->getData(UserAware::USER_RECOVERY);
        $user = $userRecovery instanceof UserRecoveryEntity ? $userRecovery->getUser() : null;
        if ($user !== null) {
            return new UserRuleScope($event->getContext(), $user);
        }

        return new RuleScope($event->getContext());
    }
}
