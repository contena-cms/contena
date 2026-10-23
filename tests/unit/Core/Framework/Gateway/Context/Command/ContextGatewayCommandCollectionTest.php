<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Gateway\Context\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Gateway\Context\Command\AbstractContextGatewayCommand;
use Contena\Core\Framework\Gateway\Context\Command\ChangePaymentMethodCommand;
use Contena\Core\Framework\Gateway\Context\Command\ContextGatewayCommandCollection;
use Contena\Core\Framework\Gateway\Context\Command\LoginUserCommand;
use Contena\Core\Framework\Gateway\Context\Command\RegisterUserCommand;

/**
 * @internal
 */
#[CoversClass(ContextGatewayCommandCollection::class)]
class ContextGatewayCommandCollectionTest extends TestCase
{
    public function testGetTokenCommandWithRegister(): void
    {
        $commands = new ContextGatewayCommandCollection();
        $commands->add(self::getCommand(RegisterUserCommand::class, ['data' => ['foo' => 'bar']]));
        $commands->add(self::getCommand(RegisterUserCommand::class, ['data' => ['wow' => 'ser']]));

        $registerCommand = $commands->getSingleTokenCommand();

        static::assertInstanceOf(RegisterUserCommand::class, $registerCommand);
        static::assertSame(['foo' => 'bar'], $registerCommand->data);
    }

    public function testGetTokenCommandWithLogin(): void
    {
        $commands = new ContextGatewayCommandCollection();
        $commands->add(self::getCommand(LoginUserCommand::class, ['userEmail' => 'foo@bar.com']));
        $commands->add(self::getCommand(LoginUserCommand::class, ['userEmail' => 'wow@ser.com']));

        $loginCommand = $commands->getSingleTokenCommand();

        static::assertInstanceOf(LoginUserCommand::class, $loginCommand);
        static::assertSame('foo@bar.com', $loginCommand->userEmail);
    }

    public function testGetTokenCommandWithNull(): void
    {
        $commands = new ContextGatewayCommandCollection();
        $commands->add(self::getCommand(ChangePaymentMethodCommand::class, ['technicalName' => 'test_payment']));

        $tokenCommand = $commands->getSingleTokenCommand();

        static::assertNull($tokenCommand);
    }

    /**
     * @template T of AbstractContextGatewayCommand
     *
     * @param class-string<T> $type
     * @param array<string, mixed> $data
     */
    private static function getCommand(string $type, array $data = []): AbstractContextGatewayCommand
    {
        return $type::createFromPayload($data);
    }
}
