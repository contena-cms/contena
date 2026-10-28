<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\App\Checkout\Gateway;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Contena\Core\Checkout\Cart\Cart;
use Contena\Core\Checkout\Gateway\Command\CheckoutGatewayCommandCollection;
use Contena\Core\Checkout\Gateway\Command\Event\CheckoutGatewayCommandsCollectedEvent;
use Contena\Core\Checkout\Gateway\Command\Executor\CheckoutGatewayCommandExecutor;
use Contena\Core\Checkout\Gateway\Command\Registry\CheckoutGatewayCommandRegistry;
use Contena\Core\Checkout\Gateway\Command\Struct\CheckoutGatewayPayloadStruct;
use Contena\Core\Checkout\Payment\PaymentMethodCollection;
use Contena\Core\Checkout\Payment\PaymentMethodEntity;
use Contena\Core\Checkout\Shipping\ShippingMethodCollection;
use Contena\Core\Checkout\Shipping\ShippingMethodEntity;
use Contena\Core\Framework\App\ActiveAppsLoader;
use Contena\Core\Framework\App\AppCollection;
use Contena\Core\Framework\App\AppEntity;
use Contena\Core\Framework\App\Checkout\Gateway\AppCheckoutGateway;
use Contena\Core\Framework\App\Checkout\Gateway\AppCheckoutGatewayResponse;
use Contena\Core\Framework\App\Checkout\Payload\AppCheckoutGatewayPayload;
use Contena\Core\Framework\App\Checkout\Payload\AppCheckoutGatewayPayloadService;
use Contena\Core\Framework\App\Privileges\AppCapability;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\NotEqualsFilter;
use Contena\Core\Framework\Log\ExceptionLogger;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Test\Generator;
use Contena\Tests\Unit\Core\Checkout\Gateway\Command\_fixture\StubCheckoutGatewayCommand;
use Contena\Tests\Unit\Core\Checkout\Gateway\Command\_fixture\StubCheckoutGatewayHandler;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(AppCheckoutGateway::class)]
class AppCheckoutGatewayTest extends TestCase
{
    public function testProcessWithoutAppsDoesNothing(): void
    {
        $appRepository = $this->createMock(EntityRepository::class);
        $appRepository
            ->expects($this->never())
            ->method('search');

        $gateway = new AppCheckoutGateway(
            static::createStub(AppCheckoutGatewayPayloadService::class),
            new CheckoutGatewayCommandExecutor($this->getRegistry(), new ExceptionLogger('test', false, new NullLogger())),
            static::createStub(CheckoutGatewayCommandRegistry::class),
            $appRepository,
            static::createStub(EventDispatcherInterface::class),
            static::createStub(ExceptionLogger::class),
            static::createStub(ActiveAppsLoader::class),
            static::createStub(AppCapability::class)
        );

        $gateway->process(new CheckoutGatewayPayloadStruct(new Cart('hatoken'), Generator::generateSalesChannelContext(), new PaymentMethodCollection(), new ShippingMethodCollection()));
    }

    public function testProcess(): void
    {
        $context = Generator::generateSalesChannelContext();

        $criteria = new Criteria();
        $criteria->addAssociation('paymentMethods');

        $criteria->addFilter(
            new EqualsFilter('active', true),
            new NotEqualsFilter('checkoutGatewayUrl', null),
        );

        $app = new AppEntity();
        $app->setId(Uuid::randomHex());
        $app->setUniqueIdentifier(Uuid::randomHex());
        $app->setCheckoutGatewayUrl('https://example.com');

        $result = new EntitySearchResult(
            'app',
            1,
            new AppCollection([$app]),
            null,
            $criteria,
            $context->getContext()
        );

        $appRepo = $this->createMock(EntityRepository::class);
        $appRepo
            ->expects($this->once())
            ->method('search')
            ->with(static::equalTo($criteria))
            ->willReturn($result);

        $id = Uuid::randomHex();

        $paymentMethod = new PaymentMethodEntity();
        $paymentMethod->setUniqueIdentifier($id);
        $paymentMethod->setTechnicalName('payment-test');

        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setUniqueIdentifier($id);
        $shippingMethod->setTechnicalName('shipping-test');

        $cart = new Cart('hatoken');
        $payments = new PaymentMethodCollection([$paymentMethod]);
        $shipments = new ShippingMethodCollection([$shippingMethod]);

        $payloadService = $this->createMock(AppCheckoutGatewayPayloadService::class);
        $payloadService
            ->expects($this->once())
            ->method('request')
            ->with(
                'https://example.com',
                static::equalTo(new AppCheckoutGatewayPayload($context, $cart, [$id => 'payment-test'], [$id => 'shipping-test'])),
                $app
            )
            ->willReturn(new AppCheckoutGatewayResponse([['command' => 'test', 'payload' => [['test-method']]]]));

        $registry = new CheckoutGatewayCommandRegistry([new StubCheckoutGatewayHandler()]);

        $expectedCollection = new CheckoutGatewayCommandCollection([new StubCheckoutGatewayCommand(['test-method'])]);

        $executor = new CheckoutGatewayCommandExecutor($this->getRegistry(), new ExceptionLogger('test', false, new NullLogger()));

        $payload = new CheckoutGatewayPayloadStruct($cart, $context, $payments, $shipments);

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with(static::equalTo(new CheckoutGatewayCommandsCollectedEvent($payload, $expectedCollection)));

        $loader = static::createStub(ActiveAppsLoader::class);
        $loader->method('getActiveApps')->willReturn([$app]);

        $capabilityAccess = static::createStub(AppCapability::class);
        $capabilityAccess->method('whenGranted')->willReturnCallback(
            static fn (string $appId, string $action, callable $callback): mixed => $callback()
        );

        $gateway = new AppCheckoutGateway(
            $payloadService,
            $executor,
            $registry,
            $appRepo,
            $eventDispatcher,
            static::createStub(ExceptionLogger::class),
            $loader,
            $capabilityAccess
        );

        $gateway->process($payload);
    }

    public function testProcessSkipsAppWithoutGrantedPermission(): void
    {
        $context = Generator::generateSalesChannelContext();

        $app = new AppEntity();
        $app->setId(Uuid::randomHex());
        $app->setUniqueIdentifier(Uuid::randomHex());
        $app->setCheckoutGatewayUrl('https://example.com');

        $result = new EntitySearchResult(
            'app',
            1,
            new AppCollection([$app]),
            null,
            new Criteria(),
            $context->getContext()
        );

        $appRepo = static::createStub(EntityRepository::class);
        $appRepo->method('search')->willReturn($result);

        $payloadService = $this->createMock(AppCheckoutGatewayPayloadService::class);
        $payloadService->expects($this->never())->method('request');

        $loader = static::createStub(ActiveAppsLoader::class);
        $loader->method('getActiveApps')->willReturn([$app]);

        $capabilityAccess = static::createStub(AppCapability::class);
        $capabilityAccess->method('whenGranted')->willReturn(null);

        $gateway = new AppCheckoutGateway(
            $payloadService,
            new CheckoutGatewayCommandExecutor($this->getRegistry(), new ExceptionLogger('test', false, new NullLogger())),
            static::createStub(CheckoutGatewayCommandRegistry::class),
            $appRepo,
            static::createStub(EventDispatcherInterface::class),
            static::createStub(ExceptionLogger::class),
            $loader,
            $capabilityAccess
        );

        $gateway->process(new CheckoutGatewayPayloadStruct(new Cart('hatoken'), $context, new PaymentMethodCollection(), new ShippingMethodCollection()));
    }

    private function getRegistry(): CheckoutGatewayCommandRegistry
    {
        return new CheckoutGatewayCommandRegistry([new StubCheckoutGatewayHandler()]);
    }
}
