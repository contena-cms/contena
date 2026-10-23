<?php declare(strict_types=1);

namespace Contena\Core\Framework\Gateway\Context\Command;

use Contena\Core\Framework\Log\Package;

class LoginUserCommand extends AbstractContextGatewayCommand implements TokenCommandInterface
{
    public const COMMAND_KEY = 'context_login-user';

    public function __construct(
        public readonly string $userEmail,
    ) {
    }

    public static function getDefaultKeyName(): string
    {
        return self::COMMAND_KEY;
    }
}
