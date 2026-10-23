<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Gateway\Context\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Gateway\Context\Command\RegisterUserCommand;

/**
 * @internal
 */
#[CoversClass(RegisterUserCommand::class)]
class RegisterUserCommandTest extends TestCase
{
    public function testCommand(): void
    {
        $command = RegisterUserCommand::createFromPayload(['data' => ['foo' => 'bar']]);

        static::assertSame('context_register-user', $command::getDefaultKeyName());
        static::assertSame(['foo' => 'bar'], $command->data);
    }
}
