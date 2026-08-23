<?php declare(strict_types=1);

namespace Contena\Core\Framework\Validation;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Event\GenericEvent;
use Contena\Core\Framework\Event\ContenaEvent;
use Contena\Core\Framework\Validation\DataBag\DataBag;
use Symfony\Contracts\EventDispatcher\Event;

class BuildValidationEvent extends Event implements ContenaEvent, GenericEvent
{
    public function __construct(
        private readonly DataValidationDefinition $definition,
        private readonly DataBag $data,
        private readonly Context $context
    ) {
    }

    public function getName(): string
    {
        return 'framework.validation.' . $this->definition->getName();
    }

    public function getDefinition(): DataValidationDefinition
    {
        return $this->definition;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getData(): DataBag
    {
        return $this->data;
    }
}
