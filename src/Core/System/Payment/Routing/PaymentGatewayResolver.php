<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Routing;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentApp\PaymentAppEntity;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentChannel\Aggregate\PaymentChannelConfig\PaymentChannelConfigCollection;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentChannel\Aggregate\PaymentChannelConfig\PaymentChannelConfigEntity;
use Contena\Core\System\Payment\Gateway\GatewayRegistry;
use Contena\Core\System\Payment\PaymentException;
use Contena\Core\System\Payment\Struct\PaymentRoute;

/**
 * @internal
 */
final class PaymentGatewayResolver
{
    /**
     * @param EntityRepository<PaymentChannelConfigCollection> $configRepository
     */
    public function __construct(
        private readonly EntityRepository $configRepository,
        private readonly GatewayRegistry $gatewayRegistry,
    ) {
    }

    public function resolve(string $channelConfigId, Context $context): PaymentRoute
    {
        $config = $this->configById($channelConfigId, $context)
            ?? $this->configById($channelConfigId, Context::createDefaultContext());
        $channel = $config?->channel?->code;
        if (!$config instanceof PaymentChannelConfigEntity || $channel === null) {
            throw PaymentException::channelConfigNotFound($channelConfigId);
        }

        return new PaymentRoute($this->gatewayRegistry->get($channel), $config->getId(), $config->config ?? [], $config->paymentAppId === null);
    }

    public function resolveForApp(PaymentAppEntity $app, ?string $channel, Context $context): PaymentRoute
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('paymentAppId', $app->getId()));
        $criteria->addFilter(new EqualsFilter('status', true));
        if ($channel !== null) {
            $criteria->addFilter(new EqualsFilter('channel.code', $channel));
        }
        $criteria->addAssociation('channel');
        $criteria->setLimit(1);
        $config = $this->configRepository->search($criteria, $context)->getEntities()->first();
        if (!$config instanceof PaymentChannelConfigEntity || $config->channel?->code === null) {
            throw PaymentException::routeNotFound($app->getId(), null);
        }

        return new PaymentRoute($this->gatewayRegistry->get($config->channel->code), $config->getId(), $config->config ?? [], $config->paymentAppId === null);
    }

    private function configById(string $channelConfigId, Context $context): ?PaymentChannelConfigEntity
    {
        $criteria = new Criteria([$channelConfigId]);
        $criteria->addAssociation('channel');

        $config = $this->configRepository->search($criteria, $context)->getEntities()->first();

        return $config instanceof PaymentChannelConfigEntity ? $config : null;
    }
}
