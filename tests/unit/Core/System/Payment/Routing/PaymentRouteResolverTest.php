<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Payment\Routing;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentApp\PaymentAppEntity;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentAppChannelMethod\PaymentAppChannelMethodCollection;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentAppChannelMethod\PaymentAppChannelMethodEntity;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentChannel\Aggregate\PaymentChannelConfig\PaymentChannelConfigCollection;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentChannel\Aggregate\PaymentChannelConfig\PaymentChannelConfigEntity;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentChannel\PaymentChannelEntity;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentChannelMethod\PaymentChannelMethodEntity;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentOrder\PaymentOrderEntity;
use Contena\Core\System\Payment\Event\PaymentRouteCandidateEvent;
use Contena\Core\System\Payment\Gateway\GatewayRegistry;
use Contena\Core\System\Payment\Gateway\PaymentHandlerInterface;
use Contena\Core\System\Payment\Gateway\PaymentOperation;
use Contena\Core\System\Payment\Gateway\PaymentQueryHandlerInterface;
use Contena\Core\System\Payment\Gateway\PaymentStatus;
use Contena\Core\System\Payment\PaymentException;
use Contena\Core\System\Payment\Routing\ConfiguredPaymentRouteProvider;
use Contena\Core\System\Payment\Routing\PaymentGatewayResolver;
use Contena\Core\System\Payment\Routing\PaymentRouteResolver;
use Contena\Core\System\Payment\Routing\PaymentRoutingRequest;
use Contena\Core\System\Payment\Struct\GatewayResult;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[CoversClass(PaymentRouteResolver::class)]
#[CoversClass(ConfiguredPaymentRouteProvider::class)]
final class PaymentRouteResolverTest extends TestCase
{
    public function testResolverRejectsDisabledApplicationBeforeLoadingCandidates(): void
    {
        $app = new PaymentAppEntity()->assign(['id' => Uuid::randomHex(), 'appCode' => 'disabled-app', 'status' => false]);
        $resolver = new PaymentRouteResolver([], [], new EventDispatcher());
        $this->expectExceptionObject(PaymentException::appNotFound('disabled-app'));

        $resolver->resolve($app, Context::createDefaultContext(), new PaymentRoutingRequest(PaymentOperation::PAY, PaymentHandlerInterface::class));
    }

    public function testResolverRejectsGlobalExecutionScope(): void
    {
        $app = new PaymentAppEntity()->assign(['id' => Uuid::randomHex(), 'appCode' => 'platform-app', 'status' => true]);
        $resolver = new PaymentRouteResolver([], [], new EventDispatcher());
        $this->expectExceptionObject(PaymentException::invalidRequest('Payment operations require a platform or tenant context.'));

        $resolver->resolve($app, Context::createGlobalContext(), new PaymentRoutingRequest(PaymentOperation::PAY, PaymentHandlerInterface::class));
    }

    public function testResolveConfiguredUsesThePersistedConfiguration(): void
    {
        $configId = Uuid::randomHex();
        $channel = new PaymentChannelEntity()->assign([
            'id' => Uuid::randomHex(),
            'code' => 'query-provider',
            'configSchema' => [],
            'status' => true,
            'sort' => 1,
        ]);
        $config = new PaymentChannelConfigEntity()->assign([
            'id' => $configId,
            'channelId' => $channel->getId(),
            'channel' => $channel,
            'config' => ['merchantId' => 'merchant-1'],
            'status' => true,
        ]);
        $gateway = new class implements PaymentQueryHandlerInterface {
            public function code(): string
            {
                return 'query-provider';
            }

            public function query(PaymentOrderEntity $order, array $config): GatewayResult
            {
                return new GatewayResult(PaymentStatus::PENDING);
            }
        };

        $gatewayResolver = new PaymentGatewayResolver(StaticEntityRepository::of(PaymentChannelConfigCollection::class, [new PaymentChannelConfigCollection([$config])]), new GatewayRegistry([$gateway]));
        $route = $gatewayResolver->resolve($configId, Context::createDefaultContext());
        static::assertSame($gateway, $route->gateway);
    }

    public function testDisabledAssignmentIsNotRouted(): void
    {
        $app = new PaymentAppEntity()->assign(['id' => Uuid::randomHex(), 'appCode' => 'app-1', 'status' => true]);
        $firstGateway = new RoutingPaymentGateway('first');
        $secondGateway = new RoutingPaymentGateway('second');
        $assignments = new PaymentAppChannelMethodCollection([
            $this->assignment($app, $firstGateway->code(), 1)->assign(['status' => false]),
            $this->assignment($app, $secondGateway->code(), 2),
        ]);
        $firstConfig = $this->config($app->getId(), $firstGateway->code(), ['key' => 'first']);
        $config = $this->config($app->getId(), $secondGateway->code(), ['key' => 'second']);
        $resolver = $this->resolver(
            StaticEntityRepository::of(PaymentAppChannelMethodCollection::class, [$assignments]),
            StaticEntityRepository::of(PaymentChannelConfigCollection::class, [new PaymentChannelConfigCollection([$firstConfig, $config]), new PaymentChannelConfigCollection()]),
            new GatewayRegistry([$firstGateway, $secondGateway]),
            new EventDispatcher(),
        );

        $route = $resolver->resolve($app, Context::createDefaultContext(), new PaymentRoutingRequest(PaymentOperation::PAY, PaymentHandlerInterface::class, 'h5', amount: 1000));

        static::assertSame($secondGateway, $route->gateway);
        static::assertSame(['key' => 'second'], $route->config);
    }

    public function testPluginCanRejectAppConfigurationAndAllowPlatformFallback(): void
    {
        $app = new PaymentAppEntity()->assign(['id' => Uuid::randomHex(), 'appCode' => 'app-2', 'status' => true]);
        $gateway = new RoutingPaymentGateway('platform-fallback');
        $assignment = $this->assignment($app, $gateway->code(), 1);
        $appConfig = $this->config($app->getId(), $gateway->code(), ['key' => 'app']);
        $platformConfig = $this->config(null, $gateway->code(), ['key' => 'platform']);
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(PaymentRouteCandidateEvent::class, static function (PaymentRouteCandidateEvent $event) use ($appConfig): void {
            if ($event->route->channelConfigId === $appConfig->getId()) {
                $event->eligible = false;
            }
        });
        $resolver = $this->resolver(
            StaticEntityRepository::of(PaymentAppChannelMethodCollection::class, [new PaymentAppChannelMethodCollection([$assignment])]),
            StaticEntityRepository::of(PaymentChannelConfigCollection::class, [
                new PaymentChannelConfigCollection([$appConfig]),
                new PaymentChannelConfigCollection([$platformConfig]),
            ]),
            new GatewayRegistry([$gateway]),
            $dispatcher,
        );

        $route = $resolver->resolve($app, Context::createDefaultContext(), new PaymentRoutingRequest(PaymentOperation::PAY, PaymentHandlerInterface::class, 'h5', amount: 1000));

        static::assertSame($platformConfig->getId(), $route->channelConfigId);
        static::assertSame(['key' => 'platform'], $route->config);
        static::assertTrue($route->platformConfig);
    }

    /**
     * @param EntityRepository<PaymentAppChannelMethodCollection> $methods
     * @param EntityRepository<PaymentChannelConfigCollection> $configs
     */
    private function resolver(EntityRepository $methods, EntityRepository $configs, GatewayRegistry $gateways, EventDispatcher $dispatcher): PaymentRouteResolver
    {
        return new PaymentRouteResolver(
            [new ConfiguredPaymentRouteProvider($methods, $configs, $gateways)],
            [],
            $dispatcher,
        );
    }

    private function assignment(PaymentAppEntity $app, string $channelCode, int $sort): PaymentAppChannelMethodEntity
    {
        $channel = new PaymentChannelEntity()->assign([
            'id' => Uuid::randomHex(),
            'code' => $channelCode,
            'status' => true,
            'sort' => $sort,
        ]);
        $method = new PaymentChannelMethodEntity()->assign([
            'id' => Uuid::randomHex(),
            'channelId' => $channel->getId(),
            'methodCode' => 'h5',
            'status' => true,
            'sort' => $sort,
            'channel' => $channel,
        ]);

        return new PaymentAppChannelMethodEntity()->assign([
            'id' => Uuid::randomHex(),
            'paymentAppId' => $app->getId(),
            'channelMethodId' => $method->getId(),
            'channelMethod' => $method,
            'status' => true,
            'sort' => $sort,
        ]);
    }

    /**
     * @param array<string, mixed> $values
     */
    private function config(?string $appId, string $channelCode, array $values): PaymentChannelConfigEntity
    {
        $channel = new PaymentChannelEntity()->assign([
            'id' => Uuid::randomHex(),
            'code' => $channelCode,
            'configSchema' => [],
            'status' => true,
            'sort' => 1,
        ]);

        return new PaymentChannelConfigEntity()->assign([
            'id' => Uuid::randomHex(),
            'paymentAppId' => $appId,
            'channelId' => $channel->getId(),
            'channel' => $channel,
            'config' => $values,
            'status' => true,
        ]);
    }
}

/**
 * @internal
 */
final class RoutingPaymentGateway implements PaymentHandlerInterface
{
    public function __construct(private readonly string $gatewayCode)
    {
    }

    public function code(): string
    {
        return $this->gatewayCode;
    }

    public function pay(PaymentOrderEntity $order, array $config): GatewayResult
    {
        return new GatewayResult(PaymentStatus::PENDING);
    }
}
