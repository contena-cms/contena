<?php declare(strict_types=1);

namespace Contena\Core\Framework\Gateway\Context\Command;

use Contena\Core\Framework\Log\Package;

class RegisterUserCommand extends AbstractContextGatewayCommand implements TokenCommandInterface
{
    public const COMMAND_KEY = 'context_register-user';

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        public readonly array $data
    ) {
    }

    public static function getDefaultKeyName(): string
    {
        return self::COMMAND_KEY;
    }
}
