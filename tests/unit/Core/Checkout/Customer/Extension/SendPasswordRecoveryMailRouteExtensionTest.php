<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Checkout\Customer\Extension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Customer\Extension\SendPasswordRecoveryMailRouteExtension;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\System\SalesChannel\SuccessResponse;
use Contena\Core\Test\Generator;
use Contena\Tests\Examples\SendPasswordRecoveryMailRouteExample;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(SendPasswordRecoveryMailRouteExtension::class)]
class SendPasswordRecoveryMailRouteExtensionTest extends TestCase
{
    public function testSubscriberResolvesRecoveryMail(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new SendPasswordRecoveryMailRouteExample());

        $coreCalled = false;
        (new ExtensionDispatcher($dispatcher))->publish(
            name: SendPasswordRecoveryMailRouteExtension::NAME,
            extension: new SendPasswordRecoveryMailRouteExtension(
                new RequestDataBag(),
                Generator::generateSalesChannelContext(),
                validateStorefrontUrl: true,
            ),
            function: static function () use (&$coreCalled): SuccessResponse {
                $coreCalled = true;

                return new SuccessResponse();
            },
        );

        static::assertFalse($coreCalled, 'The core recovery flow must be skipped when a subscriber resolves it.');
    }
}
