<?php declare(strict_types=1);

namespace Contena\Core\Framework\Gateway\Context\Command;


class ChangeShippingLocationCommand extends AbstractContextGatewayCommand
{
    public const COMMAND_KEY = 'context_change-shipping-location';

    public function __construct(
        public readonly ?string $countryIso = null,
        public readonly ?string $countryStateIso = null,
    ) {
    }

    public static function getDefaultKeyName(): string
    {
        return self::COMMAND_KEY;
    }
}
