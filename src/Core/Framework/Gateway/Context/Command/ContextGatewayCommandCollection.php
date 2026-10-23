<?php declare(strict_types=1);

namespace Contena\Core\Framework\Gateway\Context\Command;

use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Struct\Collection;

/**
 * @template T of AbstractContextGatewayCommand = AbstractContextGatewayCommand
 *
 * @extends Collection<T>
 */
class ContextGatewayCommandCollection extends Collection
{
    public function getSingleTokenCommand(): LoginUserCommand|RegisterUserCommand|null
    {
        return $this->getTokenCommands()->first();
    }

    /**
     * @return self<LoginUserCommand|RegisterUserCommand>
     */
    public function getTokenCommands(): self
    {
        return $this->filter(static fn (AbstractContextGatewayCommand $command) => $command instanceof LoginUserCommand || $command instanceof RegisterUserCommand);
    }

    /**
     * @return string[]
     */
    public function getCommandTypes(): array
    {
        return $this->map(static fn (AbstractContextGatewayCommand $command) => $command::getDefaultKeyName());
    }
}
