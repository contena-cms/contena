<?php declare(strict_types=1);

namespace Contena\Core\Framework\Gateway\Context\Command\Handler;

use Contena\Core\Framework\Gateway\Context\Command\AbstractContextGatewayCommand;
use Contena\Core\Framework\Gateway\Context\Command\AddUserMessageCommand;
use Contena\Core\Framework\Gateway\GatewayException;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Channel\ChannelContext;

/**
 * @extends AbstractContextGatewayCommandHandler<AddUserMessageCommand>
 *
 * @internal
 */
class AddUserMessageCommandHandler extends AbstractContextGatewayCommandHandler
{
    public function handle(AbstractContextGatewayCommand $command, ChannelContext $context, array &$parameters): void
    {
        throw GatewayException::userMessage($command->message);
    }

    public static function supportedCommands(): array
    {
        return [AddUserMessageCommand::class];
    }
}
