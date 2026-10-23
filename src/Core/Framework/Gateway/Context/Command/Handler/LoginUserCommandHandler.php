<?php declare(strict_types=1);

namespace Contena\Core\Framework\Gateway\Context\Command\Handler;

use Contena\Core\System\User\Channel\AccountService;
use Contena\Core\Framework\Gateway\Context\Command\AbstractContextGatewayCommand;
use Contena\Core\Framework\Gateway\Context\Command\LoginUserCommand;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Channel\ChannelContext;

/**
 * @extends AbstractContextGatewayCommandHandler<LoginUserCommand>
 *
 * @internal
 */
class LoginUserCommandHandler extends AbstractContextGatewayCommandHandler
{
    /**
     * @internal
     */
    public function __construct(
        private readonly AccountService $accountService,
    ) {
    }

    public function handle(AbstractContextGatewayCommand $command, ChannelContext $context, array &$parameters): void
    {
        $user = $this->accountService->getUserByEmail($command->userEmail, $context);
        $parameters['token'] = $this->accountService->loginById($user->getId(), $context);
    }

    public static function supportedCommands(): array
    {
        return [LoginUserCommand::class];
    }
}
