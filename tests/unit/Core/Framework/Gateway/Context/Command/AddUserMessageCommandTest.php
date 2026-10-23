<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Gateway\Context\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Gateway\Context\Command\AddUserMessageCommand;

/**
 * @internal
 */
#[CoversClass(AddUserMessageCommand::class)]
class AddUserMessageCommandTest extends TestCase
{
    public function testCommand(): void
    {
        $command = AddUserMessageCommand::createFromPayload(['message' => 'Foo Bar']);

        static::assertSame('context_add-user-message', $command::getDefaultKeyName());
        static::assertSame('Foo Bar', $command->message);
    }
}
