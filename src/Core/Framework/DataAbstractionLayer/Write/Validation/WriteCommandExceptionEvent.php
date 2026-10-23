<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Write\Validation;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Contena\Core\Framework\Event\ContenaEvent;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @codeCoverageIgnore
 */
class WriteCommandExceptionEvent extends Event implements ContenaEvent
{
    /**
     * @param WriteCommand[] $commands
     */
    public function __construct(
        private readonly \Throwable $exception,
        private readonly array $commands,
        private readonly Context $context
    ) {
    }

    public function getException(): \Throwable
    {
        return $this->exception;
    }

    /**
     * @return WriteCommand[]
     */
    public function getCommands(): array
    {
        return $this->commands;
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
