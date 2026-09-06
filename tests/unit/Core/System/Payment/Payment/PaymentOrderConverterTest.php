<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Payment\Payment;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentApp\PaymentAppEntity;
use Contena\Core\System\Payment\Gateway\GatewayInterface;
use Contena\Core\System\Payment\Payment\Event\PaymentOrderConvertedEvent;
use Contena\Core\System\Payment\Payment\PaymentOrderConverter;
use Contena\Core\System\Payment\Payment\Struct\PaymentRequest;
use Contena\Core\System\Payment\PaymentException;
use Contena\Core\System\Payment\Routing\PaymentRoute;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
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

    public function testPluginCanEnrichConvertedOrderWithoutReplacingTheConverter(): void
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
        });

        $data = $this->converter->convert($this->app, $request, $this->route, $context);

        static::assertSame(['merchant_campaign' => 'autumn'], $data['customFields']);
        static::assertSame('Plugin subject', $data['subject']);
        static::assertSame(100, $data['amount']);
    }

    #[DataProvider('protectedFields')]
    public function testEnrichmentCannotReplaceAcceptedTermsOrPersistenceState(string $field, mixed $value, string $category): void
    {
        $this->dispatcher->addListener(PaymentOrderConvertedEvent::class, static function (PaymentOrderConvertedEvent $event) use ($field, $value): void {
            $event->convertedOrder[$field] = $value;
        });

        $this->expectExceptionObject(PaymentException::invalidRequest('Order conversion cannot ' . $category . ' field "' . $field . '".'));
        $this->converter->convert($this->app, new PaymentRequest('merchant-order', 100, 'h5', 'Subject'), $this->route, Context::createDefaultContext());
    }

    /**
     * @return iterable<string, array{string, mixed, string}>
     */
    public static function protectedFields(): iterable
    {
        yield 'accepted amount is immutable' => ['amount', 1, 'change the protected'];
        yield 'accepted currency is immutable' => ['currencyCode', 'USD', 'change the protected'];
        yield 'application ownership is immutable' => ['paymentAppId', 'other-app', 'change the protected'];
        yield 'routing configuration is immutable' => ['channelConfigId', 'other-config', 'change the protected'];
        yield 'order ID is allocated during persistence' => ['id', Uuid::randomHex(), 'assign the persistence'];
        yield 'tenant ownership is supplied by Context' => ['tenantId', null, 'assign the persistence'];
        yield 'conversion must not create execution records' => ['transactions', [], 'assign the persistence'];
        yield 'conversion cannot mark an order paid' => ['stateId', Uuid::randomHex(), 'assign the persistence'];
        yield 'conversion cannot erase reserved refunds' => ['refundedAmount', 0, 'assign the persistence'];
    }
}
