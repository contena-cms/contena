<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Gateway\Context\Command\Handler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Gateway\Context\Command\AddUserMessageCommand;
use Contena\Core\Framework\Gateway\Context\Command\Handler\AddUserMessageCommandHandler;
use Contena\Core\Framework\Gateway\GatewayException;
use Contena\Core\Test\Generator;

/**
 * @internal
 */
#[CoversClass(AddUserMessageCommandHandler::class)]
class AddUserMessageCommandHandlerTest extends TestCase
{
    public function testAddUserMessage(): void
    {
        $command = AddUserMessageCommand::createFromPayload(['message' => 'Foo Bar']);
        $context = Generator::generateChannelContext();
        $parameters = [];

        $this->expectExceptionObject(GatewayException::userMessage('Foo Bar'));

        $handler = new AddUserMessageCommandHandler();
        $handler->handle($command, $context, $parameters);
    }

    public function testGetSupportedCommands(): void
    {
        static::assertSame([AddUserMessageCommand::class], AddUserMessageCommandHandler::supportedCommands());
    }
}
