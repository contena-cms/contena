<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Gateway\Context\Command\Handler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Gateway\Context\Command\ChangeBillingAddressCommand;
use Contena\Core\Framework\Gateway\Context\Command\ChangeShippingAddressCommand;
use Contena\Core\Framework\Gateway\Context\Command\Handler\ChangeAddressCommandHandler;
use Contena\Core\Test\Generator;

/**
 * @internal
 */
#[CoversClass(ChangeAddressCommandHandler::class)]
class ChangeAddressCommandHandlerTest extends TestCase
{
    public function testHandleBillingAddressCommand(): void
    {
        $command = ChangeBillingAddressCommand::createFromPayload(['addressId' => 'billingAddressId']);
        $context = Generator::generateChannelContext();
        $parameters = [];

        $handler = new ChangeAddressCommandHandler();

        $handler->handle($command, $context, $parameters);

        static::assertSame(['billingAddressId' => 'billingAddressId'], $parameters);
    }

    public function testHandleShippingAddressCommand(): void
    {
        $command = ChangeShippingAddressCommand::createFromPayload(['addressId' => 'shippingAddressId']);
        $context = Generator::generateChannelContext();
        $parameters = [];

        $handler = new ChangeAddressCommandHandler();

        $handler->handle($command, $context, $parameters);

        static::assertSame(['shippingAddressId' => 'shippingAddressId'], $parameters);
    }

    public function testGetSupportedCommands(): void
    {
        static::assertSame([
            ChangeBillingAddressCommand::class,
            ChangeShippingAddressCommand::class,
        ], ChangeAddressCommandHandler::supportedCommands());
    }
}
