<?php declare(strict_types=1);

namespace Contena\Core\Content\Shared\MailFlow\Event;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Event\GenericEvent;
use Contena\Core\Framework\Event\ContenaEvent;
use Symfony\Contracts\EventDispatcher\Event;

class MailFlowDataCriteriaEvent extends Event implements ContenaEvent, GenericEvent
{
    public function __construct(
        public readonly string $entityName,
        public readonly Criteria $criteria,
        private readonly Context $context,
    ) {
    }

    public function getName(): string
    {
        return 'mail-flow.data.' . $this->entityName . '.criteria.event';
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
