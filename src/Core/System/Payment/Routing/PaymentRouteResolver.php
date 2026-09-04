<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Routing;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentApp\PaymentAppEntity;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentAppChannelMethod\PaymentAppChannelMethodCollection;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentAppChannelMethod\PaymentAppChannelMethodEntity;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentChannel\Aggregate\PaymentChannelConfig\PaymentChannelConfigCollection;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentChannel\Aggregate\PaymentChannelConfig\PaymentChannelConfigEntity;
use Contena\Core\System\Payment\Event\PaymentRouteResolvedEvent;
use Contena\Core\System\Payment\Gateway\GatewayRegistry;
use Contena\Core\System\Payment\Gateway\PaymentHandlerInterface;
use Contena\Core\System\Payment\OpenApi\Api\PaymentRequest;
use Contena\Core\System\Payment\PaymentException;
use Contena\Core\System\Payment\Struct\PaymentRoute;
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
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function getDecorated(): AbstractPaymentRouteResolver
    {
        throw new DecorationPatternException(self::class);
    }

    public function resolve(
        PaymentAppEntity $app,
        Context $context,
        PaymentRequest $request,
    ): PaymentRoute {
        $method = $request->method;
        $preferredChannel = $request->channel;
        $amount = $request->amount;
        $appId = $app->getId();

        foreach ($this->channelCandidates($app, $context, $method, $preferredChannel) as $channelCode) {
            $config = $this->matchingConfig($appId, $channelCode, $context);
            if (!$config instanceof PaymentChannelConfigEntity || !$config->channel) {
                continue;
            }

            $values = $config->config ?? [];
            $gateway = $this->gatewayRegistry->get($channelCode);
            if (!$gateway instanceof PaymentHandlerInterface) {
                continue;
            }

            $route = new PaymentRoute($gateway, $config->getId(), $values, $config->paymentAppId === null);
            $event = new PaymentRouteResolvedEvent($route);
            $this->eventDispatcher->dispatch($event);

            return $event->route;
        }

        throw PaymentException::routeNotFound($appId, $method);
    }

    /**
     * @return list<string>
     */
    private function channelCandidates(PaymentAppEntity $app, Context $context, ?string $method, ?string $preferredChannel): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('paymentAppId', $app->getId()));
        $criteria->addFilter(new EqualsFilter('status', true));
        $criteria->addAssociation('channelMethod.channel');
        $criteria->addSorting(new FieldSorting('sort'));
        if ($method !== null) {
            $criteria->addFilter(new EqualsFilter('channelMethod.methodCode', $method));
            $criteria->addFilter(new EqualsFilter('channelMethod.status', true));
        }

        $channels = [];
        foreach ($this->methodRepository->search($criteria, $context)->getEntities() as $assignment) {
            if (!$assignment instanceof PaymentAppChannelMethodEntity || !$assignment->channelMethod?->channel?->status) {
                continue;
            }
            $code = $assignment->channelMethod->channel->code;
            if (!$this->gatewayRegistry->get($code) instanceof PaymentHandlerInterface) {
                continue;
            }
            if ($preferredChannel === null || $preferredChannel === $code) {
                $channels[] = $code;
            }
        }

        return array_values(array_unique($channels));
    }

    private function matchingConfig(string $appId, string $channel, Context $context): ?PaymentChannelConfigEntity
    {
        $config = $this->findConfig($appId, $channel, $context);
        if ($config instanceof PaymentChannelConfigEntity) {
            return $config;
        }

        $config = $this->findConfig(null, $channel, Context::createDefaultContext());

        return $config instanceof PaymentChannelConfigEntity ? $config : null;
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
}
