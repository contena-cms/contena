<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Checkout\Customer\Extension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Customer\Extension\LoginByCredentialsExtension;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Test\Generator;
use Contena\Tests\Examples\LoginByCredentialsExample;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(LoginByCredentialsExtension::class)]
class LoginByCredentialsExtensionTest extends TestCase
{
    public function testSubscriberResolvesLogin(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new LoginByCredentialsExample());

        $coreCalled = false;
        $result = (new ExtensionDispatcher($dispatcher))->publish(
            name: LoginByCredentialsExtension::NAME,
            extension: new LoginByCredentialsExtension(
                'user@example.com',
                'secret',
                Generator::generateSalesChannelContext(),
            ),
            function: static function () use (&$coreCalled): string {
                $coreCalled = true;

                return 'core-token';
            },
        );

        static::assertFalse($coreCalled, 'The core login must be skipped when a subscriber resolves it.');
        static::assertSame('your-context-token', $result);
    }
}
