<?php declare(strict_types=1);

namespace Contena\Core\Framework\Gateway\Context\Command;


class ChangeBillingAddressCommand extends AbstractContextGatewayCommand
{
    public const COMMAND_KEY = 'context_change-billing-address';

    public function __construct(
        public readonly string $addressId,
    ) {
    }

    public static function getDefaultKeyName(): string
    {
        return self::COMMAND_KEY;
    }
}
