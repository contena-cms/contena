<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Payment\Gateway;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Payment\Event\PaymentGatewayCompletedEvent;
use Contena\Core\System\Payment\Event\PaymentGatewayFailedEvent;
use Contena\Core\System\Payment\Event\PaymentGatewayStartedEvent;
use Contena\Core\System\Payment\Gateway\GatewayInterface;
use Contena\Core\System\Payment\Gateway\GatewayOperationExecutor;
use Contena\Core\System\Payment\Gateway\PaymentOperation;
use Contena\Core\System\Payment\Gateway\PaymentStatus;
use Contena\Core\System\Payment\PaymentException;
use Contena\Core\System\Payment\Routing\PaymentRoute;
use Contena\Core\System\Payment\Struct\GatewayResponse;
use Contena\Core\System\Payment\Struct\GatewayResult;
use Contena\Core\System\Payment\Struct\PaymentEntityReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[CoversClass(GatewayOperationExecutor::class)]
#[CoversClass(PaymentGatewayStartedEvent::class)]
#[CoversClass(PaymentGatewayCompletedEvent::class)]
#[CoversClass(PaymentGatewayFailedEvent::class)]
final class GatewayOperationExecutorTest extends TestCase
{
    private EventDispatcher $dispatcher;

    private Context $context;

    protected function setUp(): void
    {
        $this->dispatcher = new EventDispatcher();
        $this->context = Context::createDefaultContext();
    }

    public function testGatewayObserverCannotDiscardProviderSuccessOrRepeatCall(): void
    {
        $this->dispatcher->addListener(PaymentGatewayCompletedEvent::class, static function (PaymentGatewayCompletedEvent $event): never {
            static::assertSame(PaymentStatus::SUCCEEDED, $event->gatewayResult->status);
            throw PaymentException::invalidRequest('Observer failed');
        });
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error');
        $executor = new GatewayOperationExecutor($this->dispatcher, $logger);
        $result = new GatewayResult(PaymentStatus::SUCCEEDED, response: new GatewayResponse(resourceId: 'provider-1'));
        $calls = 0;

        $actual = $executor->execute(PaymentOperation::PAY, $this->reference(), $this->route(), $this->context, static function () use (&$calls, $result): GatewayResult {
            ++$calls;

            return $result;
        });

        static::assertSame($result, $actual);
        static::assertSame(1, $calls);
    }

    public function testGatewayStartListenerCanVetoBeforeProviderCall(): void
    {
        $failure = PaymentException::invalidRequest('Risk policy denied');
        $this->dispatcher->addListener(PaymentGatewayStartedEvent::class, static fn (): never => throw $failure);
        $executor = new GatewayOperationExecutor($this->dispatcher);

        $this->expectExceptionObject($failure);
        $executor->execute(PaymentOperation::PAY, $this->reference(), $this->route(), $this->context, static function (): never {
            static::fail('A vetoed operation must not reach the provider.');
        });
    }

    public function testFailedGatewayObserverCannotMaskOriginalException(): void
    {
        $original = PaymentException::invalidRequest('Provider transport failed');
        $this->dispatcher->addListener(PaymentGatewayFailedEvent::class, static function (PaymentGatewayFailedEvent $event) use ($original): never {
            static::assertSame($original, $event->exception);
            throw PaymentException::invalidRequest('Observer failed');
        });
        $executor = new GatewayOperationExecutor($this->dispatcher);

        $this->expectExceptionObject($original);
        $executor->execute(PaymentOperation::PAY, $this->reference(), $this->route(), $this->context, static fn (): never => throw $original);
    }

    private function reference(): PaymentEntityReference
    {
        return new PaymentEntityReference('payment_order', Uuid::randomHex());
    }

    private function route(): PaymentRoute
    {
        $gateway = static::createStub(GatewayInterface::class);
        $gateway->method('code')->willReturn('test');

        return new PaymentRoute($gateway, Uuid::randomHex(), [], false);
    }
}
