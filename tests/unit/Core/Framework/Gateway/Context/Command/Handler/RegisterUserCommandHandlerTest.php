<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Gateway\Context\Command\Handler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\System\User\UserEntity;
use Contena\Core\System\User\Channel\AbstractRegisterRoute;
use Contena\Core\System\User\Channel\UserResponse;
use Contena\Core\Framework\Gateway\Context\Command\Handler\RegisterUserCommandHandler;
use Contena\Core\Framework\Gateway\Context\Command\RegisterUserCommand;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\Test\Generator;

/**
 * @internal
 */
#[CoversClass(RegisterUserCommandHandler::class)]
class RegisterUserCommandHandlerTest extends TestCase
{
    public function testHandle(): void
    {
        $command = RegisterUserCommand::createFromPayload(
            [
                'data' => [
                    'billingAddress' => ['foo' => 'bar'],
                    'shippingAddress' => ['baz' => 'bat'],
                    'vatIds' => ['vatId1', 'vatId2'],
                ],
            ]
        );

        $context = Generator::generateChannelContext();
        $parameters = [];

        $expectedData = new RequestDataBag(
            [
                'vatIds' => new RequestDataBag(['vatId1', 'vatId2']),
                'billingAddress' => ['foo' => 'bar'],
                'shippingAddress' => ['baz' => 'bat'],
            ]
        );

        $response = new UserResponse(new UserEntity());
        $response->headers->set('sw-context-token', 'hatoken');

        $registerRoute = $this->createMock(AbstractRegisterRoute::class);
        $registerRoute
            ->expects($this->once())
            ->method('register')
            ->with($expectedData, $context)
            ->willReturn($response);

        $handler = new RegisterUserCommandHandler($registerRoute);
        $handler->handle($command, $context, $parameters);

        static::assertSame(['token' => 'hatoken'], $parameters);
    }

    public function testSupportedCommands(): void
    {
        static::assertSame([RegisterUserCommand::class], RegisterUserCommandHandler::supportedCommands());
    }
}
