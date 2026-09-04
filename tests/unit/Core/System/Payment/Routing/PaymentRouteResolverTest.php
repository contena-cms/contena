<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Payment\Routing;

use Contena\Core\Content\Rule\AbstractRuleLoader;
use Contena\Core\Content\Rule\RuleCollection;
use Contena\Core\Content\Rule\RuleEntity;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Rule\SimpleRule;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Payment\Configuration\ChannelConfigValidator;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentApp\PaymentAppEntity;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentAppChannelMethod\PaymentAppChannelMethodCollection;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentAppChannelMethod\PaymentAppChannelMethodEntity;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentChannel\Aggregate\PaymentChannelConfig\PaymentChannelConfigCollection;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentChannel\Aggregate\PaymentChannelConfig\PaymentChannelConfigEntity;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentChannel\PaymentChannelEntity;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentChannelMethod\PaymentChannelMethodEntity;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentOrder\PaymentOrderEntity;
use Contena\Core\System\Payment\Gateway\GatewayRegistry;
use Contena\Core\System\Payment\Gateway\PaymentHandlerInterface;
use Contena\Core\System\Payment\Gateway\PaymentOperation;
use Contena\Core\System\Payment\Gateway\PaymentStatus;
use Contena\Core\System\Payment\Gateway\QueryHandlerInterface;
use Contena\Core\System\Payment\Routing\PaymentRouteResolver;
use Contena\Core\System\Payment\Struct\PaymentResult;
use Contena\Core\System\Payment\Struct\PaymentRouteRequest;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[CoversClass(PaymentRouteResolver::class)]
final class PaymentRouteResolverTest extends TestCase
{
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
        $gateway = new class implements QueryHandlerInterface {
            public function code(): string
            {
                return 'query-provider';
            }

            public function query(PaymentOrderEntity $order, array $config): PaymentResult
            {
                return new PaymentResult(PaymentStatus::PENDING);
            }
        };

        $resolver = new PaymentRouteResolver(
            StaticEntityRepository::of(PaymentAppChannelMethodCollection::class),
            StaticEntityRepository::of(PaymentChannelConfigCollection::class, [new PaymentChannelConfigCollection([$config])]),
            new GatewayRegistry([$gateway]),
            new ChannelConfigValidator(),
            new StaticPaymentRuleLoader(new RuleCollection()),
            new EventDispatcher(),
        );

        $route = $resolver->resolveConfigured('query-provider', $configId, PaymentOperation::QUERY, Context::createDefaultContext());

        static::assertSame($gateway, $route->gateway);
        static::assertSame($configId, $route->channelConfigId);
        static::assertSame(['merchantId' => 'merchant-1'], $route->config);
        static::assertTrue($route->platformConfig);
    }

    public function testAssignmentRuleSkipsTheFirstChannelWhenItDoesNotMatch(): void
    {
        $app = new PaymentAppEntity()->assign(['id' => Uuid::randomHex(), 'appCode' => 'app-1', 'status' => true]);
        $rule = new RuleEntity()->assign([
            'id' => Uuid::randomHex(),
            'name' => 'Minimum amount',
            'priority' => 1,
            'payload' => new SimpleRule(false),
        ]);
        $firstGateway = new RoutingPaymentGateway('first');
        $secondGateway = new RoutingPaymentGateway('second');
        $assignments = new PaymentAppChannelMethodCollection([
            $this->assignment($app, $firstGateway->code(), $rule->getId(), 1),
            $this->assignment($app, $secondGateway->code(), null, 2),
        ]);
        $config = $this->config($app->getId(), $secondGateway->code(), ['key' => 'second']);
        $resolver = new PaymentRouteResolver(
            StaticEntityRepository::of(PaymentAppChannelMethodCollection::class, [$assignments]),
            StaticEntityRepository::of(PaymentChannelConfigCollection::class, [new PaymentChannelConfigCollection([$config])]),
            new GatewayRegistry([$firstGateway, $secondGateway]),
            new ChannelConfigValidator(),
            new StaticPaymentRuleLoader(new RuleCollection([$rule])),
            new EventDispatcher(),
        );

        $route = $resolver->resolve(new PaymentRouteRequest(
            context: Context::createDefaultContext(),
            app: $app,
            operation: PaymentOperation::PAY,
            method: 'h5',
            amount: 1000,
            currencyCode: 'CNY',
        ));

        static::assertSame($secondGateway, $route->gateway);
        static::assertSame(['key' => 'second'], $route->config);
    }

    public function testUnmatchedAppConfigurationFallsBackToPlatformConfiguration(): void
    {
        $app = new PaymentAppEntity()->assign(['id' => Uuid::randomHex(), 'appCode' => 'app-2', 'status' => true]);
        $gateway = new RoutingPaymentGateway('platform-fallback');
        $rule = new RuleEntity()->assign([
            'id' => Uuid::randomHex(),
            'name' => 'Minimum amount',
            'priority' => 1,
            'payload' => new SimpleRule(false),
        ]);
        $assignment = $this->assignment($app, $gateway->code(), null, 1);
        $appConfig = $this->config($app->getId(), $gateway->code(), ['key' => 'app'], $rule->getId());
        $platformConfig = $this->config(null, $gateway->code(), ['key' => 'platform']);
        $resolver = new PaymentRouteResolver(
            StaticEntityRepository::of(PaymentAppChannelMethodCollection::class, [new PaymentAppChannelMethodCollection([$assignment])]),
            StaticEntityRepository::of(PaymentChannelConfigCollection::class, [
                new PaymentChannelConfigCollection([$appConfig]),
                new PaymentChannelConfigCollection([$platformConfig]),
            ]),
            new GatewayRegistry([$gateway]),
            new ChannelConfigValidator(),
            new StaticPaymentRuleLoader(new RuleCollection([$rule])),
            new EventDispatcher(),
        );

        $route = $resolver->resolve(new PaymentRouteRequest(
            context: Context::createDefaultContext(),
            app: $app,
            operation: PaymentOperation::PAY,
            method: 'h5',
            amount: 1000,
            currencyCode: 'CNY',
        ));

        static::assertSame($platformConfig->getId(), $route->channelConfigId);
        static::assertSame(['key' => 'platform'], $route->config);
        static::assertTrue($route->platformConfig);
    }

    private function assignment(PaymentAppEntity $app, string $channelCode, ?string $ruleId, int $sort): PaymentAppChannelMethodEntity
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
            'ruleId' => $ruleId,
            'status' => true,
            'sort' => $sort,
        ]);
    }

    /**
     * @param array<string, mixed> $values
     */
    private function config(?string $appId, string $channelCode, array $values, ?string $ruleId = null): PaymentChannelConfigEntity
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
            'ruleId' => $ruleId,
            'status' => true,
        ]);
    }
}

/**
 * @internal
 */
final class StaticPaymentRuleLoader extends AbstractRuleLoader
{
    public function __construct(private readonly RuleCollection $rules)
    {
    }

    public function getDecorated(): AbstractRuleLoader
    {
        return $this;
    }

    public function load(Context $context): RuleCollection
    {
        return $this->rules;
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

    public function pay(PaymentOrderEntity $order, array $config): PaymentResult
    {
        return new PaymentResult(PaymentStatus::PENDING);
    }
}
