<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Payment\Routing;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Payment\Configuration\ChannelConfigValidator;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentAppChannelMethod\PaymentAppChannelMethodCollection;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentChannel\Aggregate\PaymentChannelConfig\PaymentChannelConfigCollection;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentChannel\Aggregate\PaymentChannelConfig\PaymentChannelConfigEntity;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentChannel\PaymentChannelEntity;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentOrder\PaymentOrderEntity;
use Contena\Core\System\Payment\Gateway\GatewayRegistry;
use Contena\Core\System\Payment\Gateway\PaymentOperation;
use Contena\Core\System\Payment\Gateway\PaymentStatus;
use Contena\Core\System\Payment\Gateway\QueryHandlerInterface;
use Contena\Core\System\Payment\Routing\PaymentRouteResolver;
use Contena\Core\System\Payment\Struct\PaymentResult;
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
            new EventDispatcher(),
        );

        $route = $resolver->resolveConfigured('query-provider', $configId, PaymentOperation::QUERY, Context::createDefaultContext());

        static::assertSame($gateway, $route->gateway);
        static::assertSame($configId, $route->channelConfigId);
        static::assertSame(['merchantId' => 'merchant-1'], $route->config);
        static::assertTrue($route->platformConfig);
    }
}
