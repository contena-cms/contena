<?php declare(strict_types=1);

namespace Contena\Core\Framework\Gateway\Context\Command\Handler;

use Contena\Core\System\User\Channel\AbstractRegisterRoute;
use Contena\Core\Framework\Gateway\Context\Command\AbstractContextGatewayCommand;
use Contena\Core\Framework\Gateway\Context\Command\RegisterUserCommand;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelContext;

/**
 * @extends AbstractContextGatewayCommandHandler<RegisterUserCommand>
 *
 * @internal
 */
class RegisterUserCommandHandler extends AbstractContextGatewayCommandHandler
{
    /**
     * @internal
     */
    public function __construct(
        private readonly AbstractRegisterRoute $registerRoute,
    ) {
    }

    public function handle(AbstractContextGatewayCommand $command, ChannelContext $context, array &$parameters): void
    {
        $data = new RequestDataBag($command->data);
        $response = $this->registerRoute->register($data, $context);

        $parameters['token'] = $response->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN);
    }

    public static function supportedCommands(): array
    {
        return [RegisterUserCommand::class];
    }
}
