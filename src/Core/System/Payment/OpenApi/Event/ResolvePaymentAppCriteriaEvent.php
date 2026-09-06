<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\OpenApi\Event;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Event\ContenaEvent;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\Event;

class ResolvePaymentAppCriteriaEvent extends Event implements ContenaEvent
{
    public function __construct(
        public private(set) readonly Request $request,
        public private(set) readonly Criteria $criteria,
        private readonly Context $context
    ) {
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
