<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Gateway\Context\Command\Executor;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Gateway\Context\Command\AbstractContextGatewayCommand;
use Contena\Core\Framework\Gateway\Context\Command\ChangeBillingAddressCommand;
use Contena\Core\Framework\Gateway\Context\Command\ChangeCurrencyCommand;
use Contena\Core\Framework\Gateway\Context\Command\ChangeLanguageCommand;
use Contena\Core\Framework\Gateway\Context\Command\ChangePaymentMethodCommand;
use Contena\Core\Framework\Gateway\Context\Command\ChangeShippingAddressCommand;
use Contena\Core\Framework\Gateway\Context\Command\ChangeShippingLocationCommand;
use Contena\Core\Framework\Gateway\Context\Command\ChangeShippingMethodCommand;
use Contena\Core\Framework\Gateway\Context\Command\ContextGatewayCommandCollection;
use Contena\Core\Framework\Gateway\Context\Command\Executor\ContextGatewayCommandValidator;
use Contena\Core\Framework\Gateway\Context\Command\LoginUserCommand;
use Contena\Core\Framework\Gateway\Context\Command\RegisterUserCommand;
use Contena\Core\Framework\Gateway\GatewayException;
use Contena\Core\Framework\Log\ExceptionLogger;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Test\Generator;

/**
 * @internal
 */
#[CoversClass(ContextGatewayCommandValidator::class)]
class ContextGatewayCommandValidatorTest extends TestCase
{
    public function testValidateWithMaximumCommands(): void
    {
        $logger = $this->createMock(ExceptionLogger::class);
        $logger
            ->expects($this->never())
            ->method(static::anything());

        $validator = new ContextGatewayCommandValidator($logger);

        $commands = new ContextGatewayCommandCollection();
        $commands->add(self::getCommand(RegisterUserCommand::class, ['data' => ['foo' => 'bar']]));
        $commands->add(self::getCommand(ChangeBillingAddressCommand::class, ['addressId' => '123']));
        $commands->add(self::getCommand(ChangeCurrencyCommand::class, ['iso' => 'EUR']));
        $commands->add(self::getCommand(ChangeLanguageCommand::class, ['iso' => 'de_DE']));
        $commands->add(self::getCommand(ChangePaymentMethodCommand::class, ['technicalName' => 'test_app_payment']));
        $commands->add(self::getCommand(ChangeShippingAddressCommand::class, ['addressId' => '123']));
        $commands->add(self::getCommand(ChangeShippingLocationCommand::class, ['countryIso' => 'DE', 'countryStateIso' => 'DE-BY']));
        $commands->add(self::getCommand(ChangeShippingMethodCommand::class, ['technicalName' => 'test_app_shipping']));

        $validator->validate($commands, Generator::generateChannelContext());
    }

    public function testValidateWithMultipleRegisterCommands(): void
    {
        $expectedException = GatewayException::commandValidationFailed('Only one register or login command is allowed');

        $logger = $this->createMock(ExceptionLogger::class);
        $logger
            ->expects($this->once())
            ->method('logOrThrowException')
            ->with($expectedException);

        $validator = new ContextGatewayCommandValidator($logger);

        $commands = new ContextGatewayCommandCollection();
        $commands->add(self::getCommand(RegisterUserCommand::class, ['data' => ['foo' => 'bar']]));
        $commands->add(self::getCommand(RegisterUserCommand::class, ['data' => ['foo' => 'bar']]));

        $validator->validate($commands, Generator::generateChannelContext());
    }

    public function testValidateWithMultipleLoginCommands(): void
    {
        $expectedException = GatewayException::commandValidationFailed('Only one register or login command is allowed');

        $logger = $this->createMock(ExceptionLogger::class);
        $logger
            ->expects($this->once())
            ->method('logOrThrowException')
            ->with($expectedException);

        $validator = new ContextGatewayCommandValidator($logger);

        $commands = new ContextGatewayCommandCollection();
        $commands->add(self::getCommand(LoginUserCommand::class, ['userEmail' => 'foo@bar.com']));
        $commands->add(self::getCommand(LoginUserCommand::class, ['userEmail' => 'foo@bar.com']));

        $validator->validate($commands, Generator::generateChannelContext());
    }

    public function testValidateWithLoginAndRegisterCommands(): void
    {
        $expectedException = GatewayException::commandValidationFailed('Only one register or login command is allowed');

        $logger = $this->createMock(ExceptionLogger::class);
        $logger
            ->expects($this->once())
            ->method('logOrThrowException')
            ->with($expectedException);

        $validator = new ContextGatewayCommandValidator($logger);

        $commands = new ContextGatewayCommandCollection();
        $commands->add(self::getCommand(RegisterUserCommand::class, ['data' => ['foo' => 'bar']]));
        $commands->add(self::getCommand(LoginUserCommand::class, ['userEmail' => 'foo@bar.com']));

        $validator->validate($commands, Generator::generateChannelContext());
    }

    public function testValidateWithDuplicateCommands(): void
    {
        $expectedException = GatewayException::commandValidationFailed('Duplicate commands of a type are not allowed');

        $logger = $this->createMock(ExceptionLogger::class);
        $logger
            ->expects($this->once())
            ->method('logOrThrowException')
            ->with($expectedException);

        $validator = new ContextGatewayCommandValidator($logger);

        $commands = new ContextGatewayCommandCollection();
        $commands->add(self::getCommand(ChangeCurrencyCommand::class, ['iso' => 'EUR']));
        $commands->add(self::getCommand(ChangeCurrencyCommand::class, ['iso' => 'USD']));

        $validator->validate($commands, Generator::generateChannelContext());
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
