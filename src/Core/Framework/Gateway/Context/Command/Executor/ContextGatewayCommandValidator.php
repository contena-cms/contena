<?php declare(strict_types=1);

namespace Contena\Core\Framework\Gateway\Context\Command\Executor;

use Contena\Core\Framework\Gateway\Context\Command\ContextGatewayCommandCollection;
use Contena\Core\Framework\Gateway\GatewayException;
use Contena\Core\Framework\Log\ExceptionLogger;
use Contena\Core\System\Channel\ChannelContext;

/**
 * @internal
 */
class ContextGatewayCommandValidator
{
    /**
     * @internal
     */
    public function __construct(
        private readonly ExceptionLogger $logger,
    ) {
    }

    public function validate(ContextGatewayCommandCollection $commands, ChannelContext $context): void
    {
        if ($commands->getTokenCommands()->count() > 1) {
            $this->logger->logOrThrowException(GatewayException::commandValidationFailed('Only one register or login command is allowed'));

            return;
        }

        $types = $commands->getCommandTypes();

        if (\count($types) !== \count(\array_unique($types))) {
            $this->logger->logOrThrowException(GatewayException::commandValidationFailed('Duplicate commands of a type are not allowed'));
        }
    }
}
