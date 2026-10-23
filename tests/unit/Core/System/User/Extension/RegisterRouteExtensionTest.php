<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\User\Extension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\System\User\Channel\UserResponse;
use Contena\Core\System\User\Extension\RegisterRouteExtension;
use Contena\Core\System\User\UserEntity;
use Contena\Core\Test\Generator;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[CoversClass(RegisterRouteExtension::class)]
class RegisterRouteExtensionTest extends TestCase
{
    public function testSubscriberResolvesRegistration(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new RegisterRouteExample());

        $coreCalled = false;
        $result = (new ExtensionDispatcher($dispatcher))->publish(
            name: RegisterRouteExtension::NAME,
            extension: new RegisterRouteExtension(
                new RequestDataBag(),
                Generator::generateChannelContext(),
                validateFrontendUrl: true,
                additionalValidationDefinitions: null,
            ),
            function: static function () use (&$coreCalled): UserResponse {
                $coreCalled = true;

                return new UserResponse((new UserEntity())->assign(['id' => 'core']));
            },
        );

        static::assertFalse($coreCalled, 'The core registration must be skipped when a subscriber resolves it.');
        static::assertSame('example', $result->getUser()->getId());
    }
}
