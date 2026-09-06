<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Routing;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentAppChannelMethod\PaymentAppChannelMethodCollection;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentChannel\Aggregate\PaymentChannelConfig\PaymentChannelConfigCollection;
use Contena\Core\System\Payment\Gateway\GatewayRegistry;

/**
 * @internal
 */
final class ConfiguredPaymentRouteProvider implements PaymentRouteProviderInterface
{
    /**
     * @param EntityRepository<PaymentAppChannelMethodCollection> $methodRepository
     * @param EntityRepository<PaymentChannelConfigCollection> $configRepository
     */
    public function __construct(
        private readonly EntityRepository $methodRepository,
        private readonly EntityRepository $configRepository,
        private readonly GatewayRegistry $gatewayRegistry,
    ) {
    }

    public function provide(PaymentRoutingContext $routing): iterable
    {
        $channels = $this->channels($routing);
        if ($channels === []) {
            return;
        }

        foreach ([$routing->app->getId(), null] as $appId) {
            $context = $appId === null ? Context::createDefaultContext() : $routing->context;
            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('paymentAppId', $appId));
            $criteria->addFilter(new EqualsFilter('status', true));
            $criteria->addFilter(new EqualsFilter('channel.status', true));
            $criteria->addAssociation('channel');
            $criteria->addSorting(new FieldSorting('channel.sort'), new FieldSorting('id'));

            $configs = $this->configRepository->search($criteria, $context)->getEntities();
            // Method assignment order takes precedence over channel order.
            foreach ($channels ?? array_map(static fn ($config): string => $config->channel->code ?? '', $configs->getElements())
                |> array_unique(...)
                |> array_values(...) as $channel) {
                foreach ($configs as $config) {
                    if ($config->channel?->code !== $channel || !$config->channel->status || !$config->status
                        || $config->tenantId !== $context->getTenantId() || $config->paymentAppId !== $appId
                        || !$this->gatewayRegistry->has($channel)
                    ) {
                        continue;
                    }

                    $gateway = $this->gatewayRegistry->get($channel);
                    yield new PaymentRoute($gateway, $config->getId(), $config->config ?? [], $appId === null);
                }
            }
        }
    }

    /**
     * null means the operation has no payment-method assignment; an empty list
     * means the requested payment method is not enabled for this application.
     *
     * @return list<string>|null
     */
    private function channels(PaymentRoutingContext $routing): ?array
    {
        if ($routing->request->methodCode === null) {
            return null;
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('paymentAppId', $routing->app->getId()));
        $criteria->addFilter(new EqualsFilter('status', true));
        $criteria->addFilter(new EqualsFilter('channelMethod.methodCode', $routing->request->methodCode));
        $criteria->addFilter(new EqualsFilter('channelMethod.status', true));
        $criteria->addAssociation('channelMethod.channel');
        $criteria->addSorting(new FieldSorting('sort'), new FieldSorting('id'));
        $channels = [];
        foreach ($this->methodRepository->search($criteria, $routing->context)->getEntities() as $assignment) {
            if (!$assignment->status || !$assignment->channelMethod?->status || !$assignment->channelMethod->channel?->status
            ) {
                continue;
            }
            $channels[] = $assignment->channelMethod->channel->code;
        }

        return array_values(array_unique($channels));
    }
}
