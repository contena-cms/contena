<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Gateway\Context\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Gateway\Context\Command\LoginUserCommand;

/**
 * @internal
 */
#[CoversClass(LoginUserCommand::class)]
class LoginUserCommandTest extends TestCase
{
    public function testCommand(): void
    {
        $command = LoginUserCommand::createFromPayload(['userEmail' => 'foo@bar.com']);

        static::assertSame('context_login-user', $command::getDefaultKeyName());
        static::assertSame('foo@bar.com', $command->userEmail);
    }
}
