<?php declare(strict_types=1);

namespace Contena\Core\Content\Shared\MailFlow\DataProvider;

use Contena\Core\Content\Shared\MailFlow\Event\MailFlowDataCriteriaEvent;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;

/**
 * @internal
 *
 * @template TEntity of Entity
 */
interface MailFlowDataProviderInterface
{
    public function getEntityName(): string;

    /**
     * Implementations should dispatch {@see MailFlowDataCriteriaEvent} when building the criteria
     * so provider-specific criteria can still be extended by listeners.
     */
    public function getCriteria(string $entityId, Context $context): Criteria;

    /**
     * @return TEntity|null
     */
    public function getData(string $entityId, Context $context): ?Entity;
}
