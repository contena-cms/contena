<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Gateway\Context\Command\_fixture;

use Contena\Core\Framework\Gateway\Context\Command\AbstractContextGatewayCommand;
use Contena\Core\Framework\Gateway\Context\Command\ChangeBillingAddressCommand;
use Contena\Core\Framework\Gateway\Context\Command\ChangeCurrencyCommand;
use Contena\Core\Framework\Gateway\Context\Command\ChangeLanguageCommand;
use Contena\Core\Framework\Gateway\Context\Command\ChangePaymentMethodCommand;
use Contena\Core\Framework\Gateway\Context\Command\ChangeShippingAddressCommand;
use Contena\Core\Framework\Gateway\Context\Command\ChangeShippingLocationCommand;
use Contena\Core\Framework\Gateway\Context\Command\ChangeShippingMethodCommand;
use Contena\Core\Framework\Gateway\Context\Command\Handler\AbstractContextGatewayCommandHandler;
use Contena\Core\Framework\Gateway\Context\Command\LoginUserCommand;
use Contena\Core\Framework\Gateway\Context\Command\RegisterUserCommand;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Channel\ChannelContext;

/**
 * @internal
 *
 * @extends AbstractContextGatewayCommandHandler<AbstractContextGatewayCommand>
 *
 * Just for testing purposes.
 * Simply puts anything into parameters, which is provided in the command's data.
 * Supports all commands.
 */
class StubAllCommandsGatewayCommandHandler extends AbstractContextGatewayCommandHandler
{
    public function handle(AbstractContextGatewayCommand $command, ChannelContext $context, array &$parameters): void
    {
        if ($command instanceof RegisterUserCommand) {
            $parameters['token'] = 'hatoken';

            return;
        }

        if ($command instanceof LoginUserCommand) {
            $parameters['token'] = $command->userEmail;

            return;
        }

        if ($command instanceof ChangeBillingAddressCommand) {
            $parameters['billingAddress'] = $command->addressId;

            return;
        }

        if ($command instanceof ChangeShippingAddressCommand) {
            $parameters['shippingAddress'] = $command->addressId;

            return;
        }

        if ($command instanceof ChangeShippingLocationCommand) {
            if ($command->countryIso !== null) {
                $parameters['countryId'] = $command->countryIso;
            }

            if ($command->countryStateIso !== null) {
                $parameters['countryStateId'] = $command->countryStateIso;
            }

            return;
        }

        if ($command instanceof ChangeShippingMethodCommand) {
            $parameters['shippingMethod'] = $command->technicalName;

            return;
        }

        if ($command instanceof ChangePaymentMethodCommand) {
            $parameters['paymentMethod'] = $command->technicalName;

            return;
        }

        if ($command instanceof ChangeLanguageCommand) {
            $parameters['languageId'] = $command->iso;

            return;
        }

        if ($command instanceof ChangeCurrencyCommand) {
            $parameters['currencyId'] = $command->iso;
        }
    }

    public static function supportedCommands(): array
    {
        return [
            ChangeBillingAddressCommand::class,
            ChangeCurrencyCommand::class,
            ChangeLanguageCommand::class,
            ChangePaymentMethodCommand::class,
            ChangeShippingAddressCommand::class,
            ChangeShippingLocationCommand::class,
            ChangeShippingMethodCommand::class,
            LoginUserCommand::class,
            RegisterUserCommand::class,
        ];
    }
}
