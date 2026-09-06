<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Payment\Routing;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentApp\PaymentAppEntity;
use Contena\Core\System\Payment\Event\PaymentRouteCandidateEvent;
use Contena\Core\System\Payment\Event\PaymentRouteResolvedEvent;
use Contena\Core\System\Payment\Gateway\GatewayInterface;
use Contena\Core\System\Payment\Gateway\GatewayRegistry;
use Contena\Core\System\Payment\Gateway\PaymentHandlerInterface;
use Contena\Core\System\Payment\Gateway\PaymentOperation;
use Contena\Core\System\Payment\PaymentAppGuard;
use Contena\Core\System\Payment\PaymentException;
use Contena\Core\System\Payment\Routing\FirstAvailableRouteStrategy;
use Contena\Core\System\Payment\Routing\PaymentRoute;
use Contena\Core\System\Payment\Routing\PaymentRouteProviderInterface;
use Contena\Core\System\Payment\Routing\PaymentRouteResolver;
use Contena\Core\System\Payment\Routing\PaymentRouteSelectionStrategyInterface;
use Contena\Core\System\Payment\Routing\PaymentRoutingContext;
use Contena\Core\System\Payment\Routing\PaymentRoutingRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[CoversClass(PaymentRouteResolver::class)]
#[CoversClass(PaymentRouteCandidateEvent::class)]
#[CoversClass(FirstAvailableRouteStrategy::class)]
#[CoversClass(GatewayRegistry::class)]
final class PaymentRoutingExtensionTest extends TestCase
{
    public function testPluginProvidersAndStrategySelectOnlyEligibleCapableCandidates(): void
    {
        $app = new PaymentAppEntity()->assign(['id' => Uuid::randomHex(), 'appCode' => 'routing', 'status' => true]);
        $context = Context::createDefaultContext();
        $gateway = static::createStub(PaymentHandlerInterface::class);
        $gateway->method('code')->willReturn('capable');
        $unsupported = static::createStub(GatewayInterface::class);
        $unsupported->method('code')->willReturn('unsupported');
        $denied = new PaymentRoute($gateway, Uuid::randomHex(), [], false);
        $first = new PaymentRoute($gateway, Uuid::randomHex(), [], false);
        $preferred = new PaymentRoute($gateway, Uuid::randomHex(), [], false);
        $provider = static::createStub(PaymentRouteProviderInterface::class);
        $provider->method('provide')->willReturn([new PaymentRoute($unsupported, Uuid::randomHex(), [], false), $denied, $first, $preferred, $preferred]);
        $strategy = $this->createMock(PaymentRouteSelectionStrategyInterface::class);
        $strategy->expects($this->once())->method('select')->willReturnCallback(static function (array $candidates, PaymentRoutingContext $routing) use ($first, $preferred, $context): PaymentRoute {
            static::assertSame([$first, $preferred], $candidates);
            static::assertSame($context, $routing->context);

            return $preferred;
        });
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(PaymentRouteCandidateEvent::class, static function (PaymentRouteCandidateEvent $event) use ($denied): void {
            if ($event->route === $denied) {
                $event->eligible = false;
            }
        });
        $resolved = false;
        $dispatcher->addListener(PaymentRouteResolvedEvent::class, static function (PaymentRouteResolvedEvent $event) use ($preferred, &$resolved): void {
            static::assertSame($preferred, $event->route);
            $resolved = true;
        });
        $resolver = new PaymentRouteResolver([$provider], [$strategy, new FirstAvailableRouteStrategy()], new PaymentAppGuard(), $dispatcher);

        static::assertSame($preferred, $resolver->resolve($app, $context, new PaymentRoutingRequest(PaymentOperation::PAY, PaymentHandlerInterface::class)));
        static::assertTrue($resolved);
    }

    public function testStrategyCannotInventARouteOutsideTheCandidateSet(): void
    {
        $app = new PaymentAppEntity()->assign(['id' => Uuid::randomHex(), 'appCode' => 'routing', 'status' => true]);
        $gateway = static::createStub(PaymentHandlerInterface::class);
        $gateway->method('code')->willReturn('gateway');
        $strategy = static::createStub(PaymentRouteSelectionStrategyInterface::class);
        $strategy->method('select')->willReturn(new PaymentRoute($gateway, Uuid::randomHex(), [], false));
        $resolver = new PaymentRouteResolver([], [$strategy], new PaymentAppGuard(), new EventDispatcher());

        $this->expectExceptionObject(PaymentException::invalidRequest('A routing strategy must select an eligible payment route.'));
        $resolver->resolve($app, Context::createDefaultContext(), new PaymentRoutingRequest(PaymentOperation::PAY, PaymentHandlerInterface::class));
    }

    public function testDuplicateGatewayCodesFailInsteadOfSilentlyReplacingTheFirstPlugin(): void
    {
        $gateway = static::createStub(GatewayInterface::class);
        $gateway->method('code')->willReturn('duplicate');

        $this->expectExceptionObject(PaymentException::invalidExtensionRegistration(GatewayInterface::class, 'duplicate'));
        new GatewayRegistry([$gateway, $gateway]);
    }
}
