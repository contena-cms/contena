<?php declare(strict_types=1);

namespace Contena\Core\Framework\Api\Acl\Event;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Event\NestedEvent;

class AclGetAdditionalPrivilegesEvent extends NestedEvent
{
    public function __construct(
        private readonly Context $context,
        private array $privileges
    ) {
    }

    public function getPrivileges(): array
    {
        return $this->privileges;
    }

    public function setPrivileges(array $privileges): void
    {
        $this->privileges = $privileges;
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
