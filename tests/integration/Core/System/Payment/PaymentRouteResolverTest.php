<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\System\Payment;

use Contena\Core\Content\Rule\AbstractRuleLoader;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Migration\V6_8\Migration1786016192ContenaBasicData;
use Contena\Core\System\Payment\Configuration\ChannelConfigValidator;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentApp\PaymentAppEntity;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentAppChannelMethod\PaymentAppChannelMethodCollection;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentChannel\Aggregate\PaymentChannelConfig\PaymentChannelConfigCollection;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentOrder\PaymentOrderEntity;
use Contena\Core\System\Payment\Gateway\GatewayRegistry;
use Contena\Core\System\Payment\Gateway\PaymentHandlerInterface;
use Contena\Core\System\Payment\Gateway\PaymentOperation;
use Contena\Core\System\Payment\Gateway\PaymentStatus;
use Contena\Core\System\Payment\Routing\PaymentRouteResolver;
use Contena\Core\System\Payment\Struct\PaymentResult;
use Contena\Core\System\Payment\Struct\PaymentRouteRequest;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
final class PaymentRouteResolverTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testResolvesRealMethodAndChannelAssociations(): void
    {
        $context = Context::createDefaultContext();
        new Migration1786016192ContenaBasicData()->update(static::getContainer()->get(Connection::class));
        $suffix = \bin2hex(\random_bytes(8));
        $appId = Uuid::randomHex();
        $channelId = Uuid::randomHex();
        $methodId = Uuid::randomHex();
        $configId = Uuid::randomHex();
        $channelCode = 'route-' . $suffix;

        $this->repository('payment_app')->create([[
            'id' => $appId,
            'appCode' => 'route-app-' . $suffix,
            'appSecret' => 'secret',
            'name' => 'Route resolver test',
            'status' => true,
        ]], $context);
        $this->repository('payment_channel')->create([[
            'id' => $channelId,
            'code' => $channelCode,
            'name' => 'Route resolver gateway',
            'configSchema' => [],
            'status' => true,
            'sort' => 1,
        ]], $context);
        $this->repository('payment_channel_method')->create([[
            'id' => $methodId,
            'channelId' => $channelId,
            'methodCode' => 'h5',
            'name' => 'H5',
            'status' => true,
            'sort' => 1,
        ]], $context);
        $this->repository('payment_app_channel_method')->create([[
            'id' => Uuid::randomHex(),
            'paymentAppId' => $appId,
            'channelMethodId' => $methodId,
            'status' => true,
            'sort' => 1,
        ]], $context);
        $this->repository('payment_channel_config')->create([[
            'id' => $configId,
            'paymentAppId' => $appId,
            'channelId' => $channelId,
            'config' => ['merchantId' => 'merchant-1'],
            'status' => true,
        ]], $context);

        $app = $this->repository('payment_app')->search(new Criteria([$appId]), $context)->getEntities()->first();
        static::assertInstanceOf(PaymentAppEntity::class, $app);
        $gateway = new RouteResolverGateway($channelCode);
        /** @var EntityRepository<PaymentAppChannelMethodCollection> $methodRepository */
        $methodRepository = $this->repository('payment_app_channel_method');
        /** @var EntityRepository<PaymentChannelConfigCollection> $configRepository */
        $configRepository = $this->repository('payment_channel_config');
        $resolver = new PaymentRouteResolver(
            $methodRepository,
            $configRepository,
            new GatewayRegistry([$gateway]),
            new ChannelConfigValidator(),
            static::getContainer()->get(AbstractRuleLoader::class),
            new EventDispatcher(),
        );

        $route = $resolver->resolve(new PaymentRouteRequest($context, $app, PaymentOperation::PAY, method: 'h5', amount: 1000, currencyCode: 'CNY'));

        static::assertSame($gateway, $route->gateway);
        static::assertSame($configId, $route->channelConfigId);
        static::assertSame(['merchantId' => 'merchant-1'], $route->config);
        static::assertFalse($route->platformConfig);
    }

    /**
     * @return EntityRepository<EntityCollection<Entity>>
     */
    private function repository(string $entity): EntityRepository
    {
        $repository = static::getContainer()->get($entity . '.repository');
        static::assertInstanceOf(EntityRepository::class, $repository);

        return $repository;
    }
}

/**
 * @internal
 */
final class RouteResolverGateway implements PaymentHandlerInterface
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
