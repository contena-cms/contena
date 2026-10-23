<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\User\Extension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\System\User\Channel\UserRecoveryIsExpiredResponse;
use Contena\Core\System\User\Extension\UserRecoveryIsExpiredRouteExtension;
use Contena\Core\Test\Generator;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[CoversClass(UserRecoveryIsExpiredRouteExtension::class)]
class UserRecoveryIsExpiredRouteExtensionTest extends TestCase
{
    public function testSubscriberResolvesExpiryCheck(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new UserRecoveryIsExpiredRouteExample());

        $coreCalled = false;
        $result = (new ExtensionDispatcher($dispatcher))->publish(
            name: UserRecoveryIsExpiredRouteExtension::NAME,
            extension: new UserRecoveryIsExpiredRouteExtension(
                new RequestDataBag(),
                Generator::generateChannelContext(),
            ),
            function: static function () use (&$coreCalled): UserRecoveryIsExpiredResponse {
                $coreCalled = true;

                return new UserRecoveryIsExpiredResponse(true);
            },
        );

        static::assertFalse($coreCalled, 'The core expiry check must be skipped when a subscriber resolves it.');
        static::assertFalse($result->isExpired());
    }
}
