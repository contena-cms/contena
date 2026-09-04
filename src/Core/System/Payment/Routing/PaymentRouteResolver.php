<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Routing;

use Contena\Core\Content\Rule\AbstractRuleLoader;
use Contena\Core\Content\Rule\RuleCollection;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\Framework\Rule\Rule;
use Contena\Core\System\Payment\Configuration\ChannelConfigValidator;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentAppChannelMethod\PaymentAppChannelMethodCollection;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentAppChannelMethod\PaymentAppChannelMethodEntity;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentChannel\Aggregate\PaymentChannelConfig\PaymentChannelConfigCollection;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentChannel\Aggregate\PaymentChannelConfig\PaymentChannelConfigEntity;
use Contena\Core\System\Payment\Event\PaymentRouteResolvedEvent;
use Contena\Core\System\Payment\Gateway\GatewayRegistry;
use Contena\Core\System\Payment\PaymentException;
use Contena\Core\System\Payment\Rule\PaymentRuleScope;
use Contena\Core\System\Payment\Struct\PaymentRoute;
use Contena\Core\System\Payment\Struct\PaymentRouteRequest;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class PaymentRouteResolver extends AbstractPaymentRouteResolver
{
    /**
     * @internal
     *
     * @param EntityRepository<PaymentAppChannelMethodCollection> $methodRepository
     * @param EntityRepository<PaymentChannelConfigCollection> $configRepository
     */
    public function __construct(
        private readonly EntityRepository $methodRepository,
        private readonly EntityRepository $configRepository,
        private readonly GatewayRegistry $gatewayRegistry,
        private readonly ChannelConfigValidator $configValidator,
        private readonly AbstractRuleLoader $ruleLoader,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function getDecorated(): AbstractPaymentRouteResolver
    {
        throw new DecorationPatternException(self::class);
    }

    public function resolve(PaymentRouteRequest $request): PaymentRoute
    {
        $context = $request->context;
        $appId = $request->app->getId();
        $rules = $this->ruleLoader->load($context);

        foreach ($this->channelCandidates($request, $rules) as $channel) {
            if (!$this->gatewayRegistry->supports($channel, $request->operation)) {
                continue;
            }

            $config = $this->matchingConfig($appId, $channel, $request, $rules);
            if (!$config instanceof PaymentChannelConfigEntity || !$config->channel) {
                continue;
            }

            $values = $config->config ?? [];
            $this->configValidator->validate($channel, $config->channel->configSchema ?? [], $values);
            $route = new PaymentRoute($this->gatewayRegistry->get($channel), $config->getId(), $values, $config->paymentAppId === null);
            $event = new PaymentRouteResolvedEvent($route, $request);
            $this->eventDispatcher->dispatch($event);

            return $event->route;
        }

        throw PaymentException::routeNotFound($appId, $request->operation, $request->method);
    }

    public function resolveConfigured(string $channel, string $channelConfigId, string $operation, Context $context): PaymentRoute
    {
        if (!$this->gatewayRegistry->supports($channel, $operation)) {
            throw PaymentException::capabilityNotSupported($channel, $operation);
        }

        $config = $this->configById($channelConfigId, $context)
            ?? $this->configById($channelConfigId, Context::createDefaultContext());
        if (!$config instanceof PaymentChannelConfigEntity || $config->channel?->code !== $channel) {
            throw PaymentException::channelConfigNotFound($channelConfigId);
        }

        $values = $config->config ?? [];
        $this->configValidator->validate($channel, $config->channel->configSchema ?? [], $values);

        return new PaymentRoute($this->gatewayRegistry->get($channel), $config->getId(), $values, $config->paymentAppId === null);
    }

    /**
     * @return list<string>
     */
    private function channelCandidates(PaymentRouteRequest $request, RuleCollection $rules): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('paymentAppId', $request->app->getId()));
        $criteria->addFilter(new EqualsFilter('status', true));
        $criteria->addAssociation('channelMethod.channel');
        $criteria->addSorting(new FieldSorting('sort'));
        if ($request->method !== null) {
            $criteria->addFilter(new EqualsFilter('channelMethod.methodCode', $request->method));
            $criteria->addFilter(new EqualsFilter('channelMethod.status', true));
        }

        $channels = [];
        foreach ($this->methodRepository->search($criteria, $request->context)->getEntities() as $assignment) {
            if (!$assignment instanceof PaymentAppChannelMethodEntity || !$assignment->channelMethod?->channel?->status) {
                continue;
            }
            if (!$this->ruleMatches($assignment->ruleId, $rules, $request)) {
                continue;
            }

            $code = $assignment->channelMethod->channel->code;
            if ($request->preferredChannel === null || $request->preferredChannel === $code) {
                $channels[] = $code;
            }
        }

        return array_values(array_unique($channels));
    }

    private function matchingConfig(string $appId, string $channel, PaymentRouteRequest $request, RuleCollection $rules): ?PaymentChannelConfigEntity
    {
        $config = $this->findConfig($appId, $channel, $request->context);
        if ($config instanceof PaymentChannelConfigEntity && $this->ruleMatches($config->ruleId, $rules, $request)) {
            return $config;
        }

        $platformRules = $this->ruleLoader->load(Context::createDefaultContext());
        $config = $this->findConfig(null, $channel, Context::createDefaultContext());

        return $config instanceof PaymentChannelConfigEntity && $this->ruleMatches($config->ruleId, $platformRules, $request) ? $config : null;
    }

    private function ruleMatches(?string $ruleId, RuleCollection $rules, PaymentRouteRequest $request): bool
    {
        if ($ruleId === null) {
            return true;
        }

        $rule = $rules->get($ruleId)?->getPayload();

        return $rule instanceof Rule && $rule->match($this->ruleScope($request));
    }

    private function ruleScope(PaymentRouteRequest $request): PaymentRuleScope
    {
        return new PaymentRuleScope(
            $request->context,
            $request->app,
            $request->operation,
            $request->method,
            $request->preferredChannel,
            $request->amount,
            $request->currencyCode,
            $request->data,
        );
    }

    private function findConfig(?string $appId, string $channel, Context $context): ?PaymentChannelConfigEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('paymentAppId', $appId));
        $criteria->addFilter(new EqualsFilter('channel.code', $channel));
        $criteria->addFilter(new EqualsFilter('status', true));
        $criteria->addAssociation('channel');
        $criteria->setLimit(1);

        $config = $this->configRepository->search($criteria, $context)->getEntities()->first();

        return $config instanceof PaymentChannelConfigEntity ? $config : null;
    }

    private function configById(string $channelConfigId, Context $context): ?PaymentChannelConfigEntity
    {
        $criteria = new Criteria([$channelConfigId]);
        $criteria->addAssociation('channel');

        $config = $this->configRepository->search($criteria, $context)->getEntities()->first();

        return $config instanceof PaymentChannelConfigEntity ? $config : null;
    }
}
