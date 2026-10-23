<?php declare(strict_types=1);

namespace Contena\Core\Content\Flow\Dispatching;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Contena\Core\Checkout\Cart\AbstractRuleLoader;
use Contena\Core\System\User\UserEntity;
use Contena\Core\Checkout\Order\OrderEntity;
use Contena\Core\Content\Flow\Dispatching\Action\FlowAction;
use Contena\Core\Content\Flow\Dispatching\Struct\ActionSequence;
use Contena\Core\Content\Flow\Dispatching\Struct\Flow;
use Contena\Core\Content\Flow\Dispatching\Struct\IfSequence;
use Contena\Core\Content\Flow\Dispatching\Struct\Sequence;
use Contena\Core\Content\Flow\Exception\ExecuteSequenceException;
use Contena\Core\Content\Flow\Extension\FlowExecutorExtension;
use Contena\Core\Content\Flow\FlowException;
use Contena\Core\Content\Flow\Rule\UserRuleScope;
use Contena\Core\Content\Flow\Rule\FlowRuleScopeBuilder;
use Contena\Core\Content\Flow\Telemetry\FlowMetricsInstrumentor;
use Contena\Core\Framework\App\Event\AppFlowActionEvent;
use Contena\Core\Framework\App\Flow\Action\AppFlowActionProvider;
use Contena\Core\Framework\DataAbstractionLayer\Doctrine\RetryableTransaction;
use Contena\Core\Framework\Event\UserAware;
use Contena\Core\Framework\Event\OrderAware;
use Contena\Core\Framework\Event\ChannelContextAware;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Rule\Rule;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @internal not intended for decoration or replacement
 *
 * @final
 *
 * @phpstan-import-type FlowHolder from AbstractFlowLoader
 */
class FlowExecutor
{
    /**
     * @var array<string, FlowAction>
     */
    private readonly array $actions;

    /**
     * @param FlowAction[] $actions
     */
    public function __construct(
        private readonly EventDispatcherInterface $dispatcher,
        private readonly AppFlowActionProvider $appFlowActionProvider,
        private readonly AbstractRuleLoader $ruleLoader,
        private readonly FlowRuleScopeBuilder $scopeBuilder,
        private readonly Connection $connection,
        private readonly ExtensionDispatcher $extensions,
        private readonly LoggerInterface $logger,
        $actions,
        private readonly FlowMetricsInstrumentor $flowMetrics,
    ) {
        $this->actions = $actions instanceof \Traversable ? iterator_to_array($actions) : $actions;
    }

    /**
     * @param array<FlowHolder> $flowHolders
     */
    public function executeFlows(array $flowHolders, StorableFlow $event): void
    {
        foreach ($flowHolders as $flowHolder) {
            $flow = $flowHolder['payload'];
            $id = $flowHolder['id'];
            $name = $flowHolder['name'];

            try {
                $this->runFlow($flow, $event);
            } catch (ExecuteSequenceException $e) {
                $this->logger->error(
                    "Could not execute flow with error message:\n"
                    . 'Flow name: ' . $name . "\n"
                    . 'Flow id: ' . $id . "\n"
                    . 'Sequence id: ' . $e->getSequenceId() . "\n"
                    . $e->getMessage() . "\n"
                    . 'Error Code: ' . $e->getCode() . "\n",
                    ['exception' => $e]
                );
            } catch (\Throwable $e) {
                $this->logger->error(
                    "Could not execute flow with error message:\n"
                    . 'Flow name: ' . $name . "\n"
                    . 'Flow id: ' . $id . "\n"
                    . $e->getMessage() . "\n"
                    . 'Error Code: ' . $e->getCode() . "\n",
                    ['exception' => $e]
                );
            }
        }
    }

    public function execute(Flow $flow, StorableFlow $event): void
    {
        $this->runFlow($flow, $event);
    }

    public function executeSequence(?Sequence $sequence, StorableFlow $event): void
    {
        if ($sequence === null) {
            return;
        }

        $event->getFlowState()->currentSequence = $sequence;

        if ($sequence instanceof IfSequence) {
            $this->executeIf($sequence, $event);

            return;
        }

        if ($sequence instanceof ActionSequence) {
            $this->executeAction($sequence, $event);
        }
    }

    public function executeAction(ActionSequence $sequence, StorableFlow $event): void
    {
        if (!$sequence->action) {
            return;
        }

        if ($event->getFlowState()->stop) {
            return;
        }

        $event->setConfig($sequence->config);
        $event->getFlowState()->currentSequence = $sequence;

        $this->callHandle($sequence, $event);

        if ($event->getFlowState()->delayed) {
            return;
        }

        if (!$sequence->nextAction instanceof ActionSequence) {
            return;
        }

        $this->executeAction($sequence->nextAction, $event);
    }

    public function executeIf(IfSequence $sequence, StorableFlow $event): void
    {
        if ($this->sequenceRuleMatches($event, $sequence->ruleId)) {
            $this->executeSequence($sequence->trueCase, $event);

            return;
        }

        $this->executeSequence($sequence->falseCase, $event);
    }

    private function runFlow(Flow $flow, StorableFlow $event): void
    {
        // Metric covers extension too: an extension may stop propagation and replace _execute entirely - and
        // every flow execution still will be covered, and the duration will include extension pre/post overhead.
        $this->flowMetrics->measureExecution(
            $event,
            fn () => $this->extensions->publish(
                name: FlowExecutorExtension::NAME,
                extension: new FlowExecutorExtension($flow, $event),
                function: $this->_execute(...)
            ),
        );
    }

    private function _execute(Flow $flow, StorableFlow $event): void
    {
        $state = new FlowState();

        $event->setFlowState($state);
        $state->flowId = $flow->getId();
        foreach ($flow->getSequences() as $sequence) {
            $state->delayed = false;

            try {
                $this->executeSequence($sequence, $event);
            } catch (\Exception $e) {
                throw ExecuteSequenceException::sequenceExecutionFailed(
                    $sequence->flowId,
                    $sequence->sequenceId,
                    $e->getMessage(),
                    $e->getCode(),
                    $e
                );
            }

            if ($state->stop) {
                return;
            }
        }
    }

    private function callHandle(ActionSequence $sequence, StorableFlow $event): void
    {
        if ($sequence->appFlowActionId) {
            $eventData = $this->appFlowActionProvider->getWebhookPayloadAndHeaders($event, $sequence->appFlowActionId);

            $globalEvent = new AppFlowActionEvent(
                $sequence->action,
                $eventData['headers'],
                $eventData['payload'],
            );

            $this->dispatcher->dispatch($globalEvent, $sequence->action);

            return;
        }

        $action = $this->actions[$sequence->action] ?? null;

        if (!$action instanceof FlowAction) {
            return;
        }

        if (!$action instanceof TransactionalAction) {
            $action->handleFlow($event);

            return;
        }

        try {
            RetryableTransaction::transactional($this->connection, static function () use ($action, $event): void {
                $action->handleFlow($event);
            });
        } catch (\Throwable $e) {
            throw FlowException::transactionFailed($e);
        }
    }

    private function sequenceRuleMatches(StorableFlow $event, string $ruleId): bool
    {
        $baseContextEvaluation = \in_array($ruleId, $event->getContext()->getRuleIds(), true);

        if (!$event->hasData(OrderAware::ORDER) && !$event->hasData(UserAware::CUSTOMER)) {
            return $baseContextEvaluation;
        }

        $context = $event->getData(ChannelContextAware::CHANNEL_CONTEXT);

        if ($event->hasData(OrderAware::ORDER)) {
            $entity = $event->getData(OrderAware::ORDER);

            if (!$entity instanceof OrderEntity) {
                return $baseContextEvaluation;
            }
        } elseif (!$context instanceof ChannelContext || $context->getUser() !== null) {
            return $baseContextEvaluation;
        } else {
            $entity = $event->getData(UserAware::CUSTOMER);

            if (!$entity instanceof UserEntity) {
                return $baseContextEvaluation;
            }
        }

        $rule = $this->ruleLoader->load($event->getContext())->filterForFlow()->get($ruleId);

        if (!$rule || !$rule->getPayload() instanceof Rule) {
            return $baseContextEvaluation;
        }

        if ($entity instanceof OrderEntity) {
            return $rule->getPayload()->match($this->scopeBuilder->build($entity, $event->getContext()));
        }

        return $rule->getPayload()->match(new UserRuleScope($entity, $context));
    }
}
