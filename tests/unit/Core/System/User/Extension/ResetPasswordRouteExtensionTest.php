<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\User\Extension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\System\User\Extension\ResetPasswordRouteExtension;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\System\Channel\SuccessResponse;
use Contena\Core\Test\Generator;
use Contena\Tests\Examples\ResetPasswordRouteExample;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
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
                Generator::generateChannelContext(),
            ),
            function: static function () use (&$coreCalled): SuccessResponse {
                $coreCalled = true;

                return new SuccessResponse();
            },
        );

        static::assertFalse($coreCalled, 'The core reset flow must be skipped when a subscriber resolves it.');
    }
}
