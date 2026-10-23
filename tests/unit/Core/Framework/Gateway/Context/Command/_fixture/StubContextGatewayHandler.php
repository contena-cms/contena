<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Gateway\Context\Command\_fixture;

use Contena\Core\Framework\Gateway\Context\Command\AbstractContextGatewayCommand;
use Contena\Core\Framework\Gateway\Context\Command\Handler\AbstractContextGatewayCommandHandler;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Channel\ChannelContext;

/**
 * @internal
 *
 * @extends AbstractContextGatewayCommandHandler<StubContextGatewayCommand|StubContextGatewayFooCommand>
 */
class StubContextGatewayHandler extends AbstractContextGatewayCommandHandler
{
    public static function supportedCommands(): array
    {
        return [StubContextGatewayCommand::class, StubContextGatewayFooCommand::class];
    }

    /**
     * @param StubContextGatewayCommand|StubContextGatewayFooCommand $command
     */
    public function handle(AbstractContextGatewayCommand $command, ChannelContext $context, array &$parameters = []): void
    {
        if ($command instanceof StubContextGatewayFooCommand) {
            return;
        }

        $parameters['test'] = $command->data;
    }
}
