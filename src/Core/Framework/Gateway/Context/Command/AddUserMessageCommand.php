<?php declare(strict_types=1);

namespace Contena\Core\Framework\Gateway\Context\Command;


class AddUserMessageCommand extends AbstractContextGatewayCommand
{
    public const COMMAND_KEY = 'context_add-user-message';

    /**
     * @internal
     */
    public function __construct(
        public readonly string $message,
    ) {
    }

    public static function getDefaultKeyName(): string
    {
        return self::COMMAND_KEY;
    }
}
