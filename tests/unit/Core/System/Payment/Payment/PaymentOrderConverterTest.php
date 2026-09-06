<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Payment\Payment;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentApp\PaymentAppEntity;
use Contena\Core\System\Payment\Event\PaymentOrderConvertedEvent;
use Contena\Core\System\Payment\Gateway\GatewayInterface;
use Contena\Core\System\Payment\Payment\PaymentOrderConverter;
use Contena\Core\System\Payment\Payment\Struct\PaymentRequest;
use Contena\Core\System\Payment\Routing\PaymentRoute;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[CoversClass(PaymentOrderConverter::class)]
#[CoversClass(PaymentOrderConvertedEvent::class)]
final class PaymentOrderConverterTest extends TestCase
{
    private EventDispatcher $dispatcher;

    private PaymentOrderConverter $converter;

    private PaymentAppEntity $app;

    private PaymentRoute $route;

    protected function setUp(): void
    {
        $this->dispatcher = new EventDispatcher();
        $this->converter = new PaymentOrderConverter($this->dispatcher);
        $this->app = new PaymentAppEntity()->assign(['id' => Uuid::randomHex(), 'appCode' => 'converter', 'status' => true]);
        $gateway = static::createStub(GatewayInterface::class);
        $gateway->method('code')->willReturn('test');
        $this->route = new PaymentRoute($gateway, Uuid::randomHex(), [], false);
    }

    public function testConversionReturnsOnlyFlatOrderDataWithoutAllocatingIdentitiesOrTransactions(): void
    {
        $request = new PaymentRequest('merchant-order', 1299, 'h5', 'Subject', 'cny', clientIp: '192.0.2.1');

        $data = $this->converter->convert($this->app, $request, $this->route, Context::createDefaultContext());

        static::assertSame([
            'paymentAppId' => $this->app->getId(),
            'externalOrderNo' => 'merchant-order',
            'amount' => 1299,
            'currencyCode' => 'CNY',
            'channelCode' => 'test',
            'channelConfigId' => $this->route->channelConfigId,
            'methodCode' => 'h5',
            'deviceType' => 'h5',
            'subject' => 'Subject',
            'clientIp' => '192.0.2.1',
            'channelExtra' => [],
            'notifyUrl' => null,
            'returnUrl' => null,
        ], $data);
    }

    public function testPluginCanModifyConvertedOrderWithoutReplacingTheConverter(): void
    {
        $context = Context::createTenantContext(Uuid::randomHex());
        $request = new PaymentRequest('merchant-order', 100, 'h5', 'Subject');
        $this->dispatcher->addListener(PaymentOrderConvertedEvent::class, function (PaymentOrderConvertedEvent $event) use ($context, $request): void {
            static::assertSame($context, $event->getContext());
            static::assertSame($request, $event->request);
            static::assertSame($this->app, $event->app);
            static::assertSame($this->route, $event->route);
            $event->convertedOrder['customFields'] = ['merchant_campaign' => 'autumn'];
            $event->convertedOrder['subject'] = 'Plugin subject';
            $event->convertedOrder['amount'] = 200;
        });

        $data = $this->converter->convert($this->app, $request, $this->route, $context);

        static::assertSame(['merchant_campaign' => 'autumn'], $data['customFields']);
        static::assertSame('Plugin subject', $data['subject']);
        static::assertSame(200, $data['amount']);
    }
}
