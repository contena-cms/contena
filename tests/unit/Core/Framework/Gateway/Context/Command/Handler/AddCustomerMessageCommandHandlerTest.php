<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Gateway\Context\Command\Handler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Gateway\Context\Command\AddCustomerMessageCommand;
use Contena\Core\Framework\Gateway\Context\Command\Handler\AddCustomerMessageCommandHandler;
use Contena\Core\Framework\Gateway\GatewayException;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Test\Generator;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AddCustomerMessageCommandHandler::class)]
class AddCustomerMessageCommandHandlerTest extends TestCase
{
    public function testAddCustomerMessage(): void
    {
        $command = AddCustomerMessageCommand::createFromPayload(['message' => 'Foo Bar']);
        $context = Generator::generateSalesChannelContext();
        $parameters = [];

        $this->expectExceptionObject(GatewayException::customerMessage('Foo Bar'));

        $handler = new AddCustomerMessageCommandHandler();
        $handler->handle($command, $context, $parameters);
    }

    public function testGetSupportedCommands(): void
    {
        static::assertSame([AddCustomerMessageCommand::class], AddCustomerMessageCommandHandler::supportedCommands());
    }
}
