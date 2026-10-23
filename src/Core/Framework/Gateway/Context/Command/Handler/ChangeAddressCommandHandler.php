<?php declare(strict_types=1);

namespace Contena\Core\Framework\Gateway\Context\Command\Handler;

use Contena\Core\Framework\Gateway\Context\Command\AbstractContextGatewayCommand;
use Contena\Core\Framework\Gateway\Context\Command\ChangeBillingAddressCommand;
use Contena\Core\Framework\Gateway\Context\Command\ChangeShippingAddressCommand;
use Contena\Core\System\Channel\ChannelContext;

/**
 * @extends AbstractContextGatewayCommandHandler<ChangeBillingAddressCommand|ChangeShippingAddressCommand>
 *
 * @internal
 */
class ChangeAddressCommandHandler extends AbstractContextGatewayCommandHandler
{
    public function handle(AbstractContextGatewayCommand $command, ChannelContext $context, array &$parameters): void
    {
        if ($command instanceof ChangeBillingAddressCommand) {
            $parameters['billingAddressId'] = $command->addressId;
        }

        if ($command instanceof ChangeShippingAddressCommand) {
            $parameters['shippingAddressId'] = $command->addressId;
        }
    }

    public static function supportedCommands(): array
    {
        return [
            ChangeBillingAddressCommand::class,
            ChangeShippingAddressCommand::class,
        ];
    }
}
