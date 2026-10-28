<?php declare(strict_types=1);

namespace Contena\Core\Framework\Gateway\Context\Command;

use Contena\Core\Framework\Log\Package;

#[Package('framework')]
class ChangePaymentMethodCommand extends AbstractContextGatewayCommand
{
    public const COMMAND_KEY = 'context_change-payment-method';

    public function __construct(
        public readonly string $technicalName,
    ) {
    }

    public static function getDefaultKeyName(): string
    {
        return self::COMMAND_KEY;
    }
}
