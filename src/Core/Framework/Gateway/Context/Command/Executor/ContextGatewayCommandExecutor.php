<?php declare(strict_types=1);

namespace Contena\Core\Framework\Gateway\Context\Command\Executor;

use Contena\Core\Framework\Gateway\Context\Command\ContextGatewayCommandCollection;
use Contena\Core\Framework\Gateway\Context\Command\Registry\ContextGatewayCommandRegistry;
use Contena\Core\Framework\Gateway\Context\Command\TokenCommandInterface;
use Contena\Core\Framework\Gateway\GatewayException;
use Contena\Core\Framework\Log\ExceptionLogger;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\Channel\AbstractContextSwitchRoute;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\Context\ChannelContextServiceInterface;
use Contena\Core\System\Channel\Context\ChannelContextServiceParameters;
use Contena\Core\System\Channel\ContextTokenResponse;

/**
 * @internal
 */
class ContextGatewayCommandExecutor
{
    /**
     * @internal
     */
    public function __construct(
        private readonly AbstractContextSwitchRoute $contextSwitchRoute,
        private readonly ContextGatewayCommandRegistry $registry,
        private readonly ContextGatewayCommandValidator $commandValidator,
        private readonly ExceptionLogger $logger,
        private readonly ChannelContextServiceInterface $channelContextService,
    ) {
    }

    public function execute(ContextGatewayCommandCollection $commands, ChannelContext $context): ContextTokenResponse
    {
        $this->commandValidator->validate($commands, $context);

        $parameters = [];

        if ($tokenCommand = $commands->getSingleTokenCommand()) {
            $this->registry->get($tokenCommand::COMMAND_KEY)->handle($tokenCommand, $context, $parameters);

            $token = $parameters['token'];
            unset($parameters['token']);

            $contextParameters = new ChannelContextServiceParameters($context->getChannelId(), $token);
            $context = $this->channelContextService->get($contextParameters);
        }

        foreach ($commands as $command) {
            // these commands are already handled
            if ($command instanceof TokenCommandInterface) {
                continue;
            }

            if (!$this->registry->has($command::getDefaultKeyName())) {
                $this->logger->logOrThrowException(GatewayException::handlerNotFound($command::getDefaultKeyName()));
                continue;
            }

            $this->registry->get($command::getDefaultKeyName())->handle($command, $context, $parameters);
        }

        $response = new ContextTokenResponse($context->getToken());
        $response->headers->remove(PlatformRequest::HEADER_CONTEXT_TOKEN);

        if ($parameters !== []) {
            $response = $this->contextSwitchRoute->switchContext(new RequestDataBag($parameters), $context);
        }

        if ($tokenCommand) {
            $response->headers->set(PlatformRequest::HEADER_CONTEXT_TOKEN, $context->getToken());
        }

        return $response;
    }
}
