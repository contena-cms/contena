<?php declare(strict_types=1);

namespace Contena\Core\Framework\Gateway\Context\Command\Handler;

use Contena\Core\Framework\Gateway\Context\Command\AbstractContextGatewayCommand;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\SalesChannelContext;

/**
 * @template TCommand of AbstractContextGatewayCommand = AbstractContextGatewayCommand
 *
 * @internal
 */
#[Package('framework')]
abstract class AbstractContextGatewayCommandHandler
{
    /**
     * @param TCommand $command
     * @param array<string, mixed> $parameters
     */
    abstract public function handle(AbstractContextGatewayCommand $command, SalesChannelContext $context, array &$parameters): void;

    /**
     * @return array<class-string<TCommand>>
     */
    abstract public static function supportedCommands(): array;
}
