<?php declare(strict_types=1);

namespace Contena\Core\Framework\Api\Acl\Event;

use Contena\Core\Framework\Api\Context\AdminApiSource;
use Contena\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Symfony\Contracts\EventDispatcher\Event;

class CommandAclValidationEvent extends Event
{
    /**
     * @param list<string> $missingPrivileges
     */
    public function __construct(
        private array $missingPrivileges,
        private readonly AdminApiSource $source,
        private readonly WriteCommand $command
    ) {
    }

    /**
     * @return list<string>
     */
    public function getMissingPrivileges(): array
    {
        return $this->missingPrivileges;
    }

    public function addMissingPrivilege(string $privilege): void
    {
        $this->missingPrivileges[] = $privilege;
    }

    public function getSource(): AdminApiSource
    {
        return $this->source;
    }

    public function getCommand(): WriteCommand
    {
        return $this->command;
    }
}
