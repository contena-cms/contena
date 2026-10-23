<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Gateway\Context\Command\Handler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\System\User\UserEntity;
use Contena\Core\System\User\Channel\AccountService;
use Contena\Core\Framework\Gateway\Context\Command\Handler\LoginUserCommandHandler;
use Contena\Core\Framework\Gateway\Context\Command\LoginUserCommand;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Test\Generator;

/**
 * @internal
 */
#[CoversClass(LoginUserCommandHandler::class)]
class LoginUserCommandHandlerTest extends TestCase
{
    public function testHandle(): void
    {
        $command = LoginUserCommand::createFromPayload(['userEmail' => 'foo@bar.com']);
        $context = Generator::generateChannelContext();
        $parameters = [];

        $user = new UserEntity();
        $user->setId('userId');

        $accountService = $this->createMock(AccountService::class);
        $accountService
            ->expects($this->once())
            ->method('getUserByEmail')
            ->with('foo@bar.com', $context)
            ->willReturn($user);

        $accountService
            ->expects($this->once())
            ->method('loginById')
            ->with('userId', $context)
            ->willReturn('newHatoken');

        $handler = new LoginUserCommandHandler($accountService);
        $handler->handle($command, $context, $parameters);

        static::assertSame(['token' => 'newHatoken'], $parameters);
    }

    public function testSupportedCommands(): void
    {
        static::assertSame([LoginUserCommand::class], LoginUserCommandHandler::supportedCommands());
    }
}
