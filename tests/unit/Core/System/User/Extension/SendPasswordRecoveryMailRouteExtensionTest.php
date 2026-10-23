<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\User\Extension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\System\Channel\SuccessResponse;
use Contena\Core\System\User\Extension\SendPasswordRecoveryMailRouteExtension;
use Contena\Core\Test\Generator;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
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
                Generator::generateChannelContext(),
                validateFrontendUrl: true,
            ),
            function: static function () use (&$coreCalled): SuccessResponse {
                $coreCalled = true;

                return new SuccessResponse();
            },
        );

        static::assertFalse($coreCalled, 'The core recovery flow must be skipped when a subscriber resolves it.');
    }
}
