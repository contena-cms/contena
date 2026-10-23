<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Gateway\Context\Command\Executor;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Gateway\Context\Command\ChangeBillingAddressCommand;
use Contena\Core\Framework\Gateway\Context\Command\ChangeCurrencyCommand;
use Contena\Core\Framework\Gateway\Context\Command\ChangeLanguageCommand;
use Contena\Core\Framework\Gateway\Context\Command\ChangePaymentMethodCommand;
use Contena\Core\Framework\Gateway\Context\Command\ChangeShippingAddressCommand;
use Contena\Core\Framework\Gateway\Context\Command\ChangeShippingLocationCommand;
use Contena\Core\Framework\Gateway\Context\Command\ChangeShippingMethodCommand;
use Contena\Core\Framework\Gateway\Context\Command\ContextGatewayCommandCollection;
use Contena\Core\Framework\Gateway\Context\Command\Executor\ContextGatewayCommandExecutor;
use Contena\Core\Framework\Gateway\Context\Command\Executor\ContextGatewayCommandValidator;
use Contena\Core\Framework\Gateway\Context\Command\LoginUserCommand;
use Contena\Core\Framework\Gateway\Context\Command\RegisterUserCommand;
use Contena\Core\Framework\Gateway\Context\Command\Registry\ContextGatewayCommandRegistry;
use Contena\Core\Framework\Gateway\GatewayException;
use Contena\Core\Framework\Log\ExceptionLogger;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\Context\ChannelContextServiceInterface;
use Contena\Core\System\Channel\Context\ChannelContextServiceParameters;
use Contena\Core\System\Channel\ContextTokenResponse;
use Contena\Core\System\Channel\Channel\AbstractContextSwitchRoute;
use Contena\Core\Test\Generator;
use Contena\Tests\Unit\Core\Framework\Gateway\Context\Command\_fixture\StubAllCommandsGatewayCommandHandler;
use Contena\Tests\Unit\Core\Framework\Gateway\Context\Command\_fixture\StubContextGatewayCommand;

/**
 * @internal
 */
#[CoversClass(ContextGatewayCommandExecutor::class)]
class ContextGatewayCommandExecutorTest extends TestCase
{
    public function testExecuteWithRegisterCommand(): void
    {
        $commands = new ContextGatewayCommandCollection();
        $commands->add(RegisterUserCommand::createFromPayload(['data' => ['firstName' => 'Foo', 'lastName' => 'bar']]));

        $context = Generator::generateChannelContext();
        $newContext = Generator::generateChannelContext(token: 'hatoken');

        $registry = new ContextGatewayCommandRegistry([new StubAllCommandsGatewayCommandHandler()]);
        $channelService = $this->createMock(ChannelContextServiceInterface::class);
        $channelService
            ->expects($this->once())
            ->method('get')
            ->with(static::equalTo(new ChannelContextServiceParameters($context->getChannelId(), 'hatoken')))
            ->willReturn($newContext);

        $executor = new ContextGatewayCommandExecutor(
            static::createStub(AbstractContextSwitchRoute::class),
            $registry,
            static::createStub(ContextGatewayCommandValidator::class),
            static::createStub(ExceptionLogger::class),
            $channelService
        );

        $response = $executor->execute($commands, $context);

        static::assertSame('hatoken', $response->getToken());
    }

    public function testExecuteWithLoginCommand(): void
    {
        $commands = new ContextGatewayCommandCollection();
        $commands->add(LoginUserCommand::createFromPayload(['userEmail' => 'hatoken']));

        $context = Generator::generateChannelContext();
        $newContext = Generator::generateChannelContext(token: 'hatoken');

        $registry = new ContextGatewayCommandRegistry([new StubAllCommandsGatewayCommandHandler()]);
        $channelService = $this->createMock(ChannelContextServiceInterface::class);
        $channelService
            ->expects($this->once())
            ->method('get')
            ->with(static::equalTo(new ChannelContextServiceParameters($context->getChannelId(), 'hatoken')))
            ->willReturn($newContext);

        $executor = new ContextGatewayCommandExecutor(
            static::createStub(AbstractContextSwitchRoute::class),
            $registry,
            static::createStub(ContextGatewayCommandValidator::class),
            static::createStub(ExceptionLogger::class),
            $channelService
        );

        $response = $executor->execute($commands, $context);

        static::assertSame('hatoken', $response->getToken());
    }

    public function testExecuteWithDifferentCommands(): void
    {
        $commands = new ContextGatewayCommandCollection();
        $commands->add(ChangeBillingAddressCommand::createFromPayload(['addressId' => 'billingAddressId']));
        $commands->add(ChangeCurrencyCommand::createFromPayload(['iso' => 'EUR']));
        $commands->add(ChangeLanguageCommand::createFromPayload(['iso' => 'de-DE']));
        $commands->add(ChangePaymentMethodCommand::createFromPayload(['technicalName' => 'app_test_payment']));
        $commands->add(ChangeShippingAddressCommand::createFromPayload(['addressId' => 'shippingAddressId']));
        $commands->add(ChangeShippingLocationCommand::createFromPayload(['countryIso' => 'DE', 'countryStateIso' => 'DE-BY']));
        $commands->add(ChangeShippingMethodCommand::createFromPayload(['technicalName' => 'app_test_shipping']));

        $context = Generator::generateChannelContext();
        $expectedContextParameters = new RequestDataBag([
            'billingAddress' => 'billingAddressId',
            'currencyId' => 'EUR',
            'languageId' => 'de-DE',
            'paymentMethod' => 'app_test_payment',
            'shippingAddress' => 'shippingAddressId',
            'countryId' => 'DE',
            'countryStateId' => 'DE-BY',
            'shippingMethod' => 'app_test_shipping',
        ]);

        $registry = new ContextGatewayCommandRegistry([new StubAllCommandsGatewayCommandHandler()]);

        $switchRoute = $this->createMock(AbstractContextSwitchRoute::class);
        $switchRoute
            ->expects($this->once())
            ->method('switchContext')
            ->with($expectedContextParameters, $context)
            ->willReturn(new ContextTokenResponse('hatoken'));

        $executor = new ContextGatewayCommandExecutor(
            $switchRoute,
            $registry,
            static::createStub(ContextGatewayCommandValidator::class),
            static::createStub(ExceptionLogger::class),
            static::createStub(ChannelContextServiceInterface::class),
        );

        $response = $executor->execute($commands, $context);

        static::assertSame('hatoken', $response->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
    }

    public function testExecuteWithTokenCommandAndContextSwitchKeepsContextTokenHeader(): void
    {
        $commands = new ContextGatewayCommandCollection();
        $commands->add(LoginUserCommand::createFromPayload(['userEmail' => 'hatoken']));
        $commands->add(ChangeCurrencyCommand::createFromPayload(['iso' => 'EUR']));

        $context = Generator::generateChannelContext();
        $newContext = Generator::generateChannelContext(token: 'hatoken');

        $registry = new ContextGatewayCommandRegistry([new StubAllCommandsGatewayCommandHandler()]);
        $channelService = $this->createMock(ChannelContextServiceInterface::class);
        $channelService
            ->expects($this->once())
            ->method('get')
            ->with(static::equalTo(new ChannelContextServiceParameters($context->getChannelId(), 'hatoken')))
            ->willReturn($newContext);

        $switchRoute = $this->createMock(AbstractContextSwitchRoute::class);
        $switchRoute
            ->expects($this->once())
            ->method('switchContext')
            ->with(new RequestDataBag(['currencyId' => 'EUR']), $newContext)
            ->willReturn(new ContextTokenResponse('hatoken'));

        $executor = new ContextGatewayCommandExecutor(
            $switchRoute,
            $registry,
            static::createStub(ContextGatewayCommandValidator::class),
            static::createStub(ExceptionLogger::class),
            $channelService,
        );

        $response = $executor->execute($commands, $context);

        static::assertSame('hatoken', $response->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
    }

    public function testExecuteWithUnknownCommand(): void
    {
        $commands = new ContextGatewayCommandCollection();
        $commands->add(StubContextGatewayCommand::createFromPayload());

        $context = Generator::generateChannelContext();

        $registry = new ContextGatewayCommandRegistry([]);

        $logger = $this->createMock(ExceptionLogger::class);
        $logger
            ->expects($this->once())
            ->method('logOrThrowException')
            ->with(GatewayException::handlerNotFound(StubContextGatewayCommand::getDefaultKeyName()));

        $executor = new ContextGatewayCommandExecutor(
            static::createStub(AbstractContextSwitchRoute::class),
            $registry,
            static::createStub(ContextGatewayCommandValidator::class),
            $logger,
            static::createStub(ChannelContextServiceInterface::class),
        );

        $response = $executor->execute($commands, $context);

        static::assertFalse($response->headers->has(PlatformRequest::HEADER_CONTEXT_TOKEN));
    }
}
