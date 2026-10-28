<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Checkout\Customer\Extension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Customer\Extension\ResetPasswordRouteExtension;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\System\SalesChannel\SuccessResponse;
use Contena\Core\Test\Generator;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(ResetPasswordRouteExtension::class)]
class ResetPasswordRouteExtensionTest extends TestCase
{
    public function testSubscriberResolvesReset(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new ResetPasswordRouteExample());

        $coreCalled = false;
        (new ExtensionDispatcher($dispatcher))->publish(
            name: ResetPasswordRouteExtension::NAME,
            extension: new ResetPasswordRouteExtension(
                new RequestDataBag(),
                Generator::generateSalesChannelContext(),
            ),
            function: static function () use (&$coreCalled): SuccessResponse {
                $coreCalled = true;

                return new SuccessResponse();
            },
        );

        static::assertFalse($coreCalled, 'The core reset flow must be skipped when a subscriber resolves it.');
    }
}
