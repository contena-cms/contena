<?php declare(strict_types=1);

namespace Contena\Core\Framework\ContentSystem\Adapter\FactoryHelper;

use Contena\Core\Framework\ContentSystem\Adapter\Entity\AbstractContentLayoutAssignmentEntity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\OrFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Contena\Core\System\Channel\ChannelContext;

/**
 * Resolves header/footer content layout assignments with domain-aware priority.
 *
 * Resolution priority (highest to lowest):
 * 1. Domain + Channel (most specific)
 * 2. Channel only
 * 3. Global (null domain, null channel)
 *
 * @internal
 *
 * @final
 */
class DomainAwareLayoutResolver
{
    /**
     * @param EntityRepository<covariant EntityCollection<covariant AbstractContentLayoutAssignmentEntity>> $repository
     */
    public function resolve(
        ChannelContext $context,
        EntityRepository $repository
    ): ?AbstractContentLayoutAssignmentEntity {
        $domainId = $context->getDomainId();
        $channelId = $context->getChannel()->getId();

        $criteria = new Criteria();

        $criteria->addFilter(new OrFilter([
            new MultiFilter(MultiFilter::CONNECTION_AND, [
                new EqualsFilter('domainId', $domainId),
                new EqualsFilter('channelId', $channelId),
            ]),
            new MultiFilter(MultiFilter::CONNECTION_AND, [
                new EqualsFilter('domainId', null),
                new EqualsFilter('channelId', $channelId),
            ]),
            new MultiFilter(MultiFilter::CONNECTION_AND, [
                new EqualsFilter('domainId', null),
                new EqualsFilter('channelId', null),
            ]),
        ]));

        // Sort by specificity: non-null domainId first, then non-null channelId
        $criteria->addSorting(new FieldSorting('domainId', FieldSorting::DESCENDING));
        $criteria->addSorting(new FieldSorting('channelId', FieldSorting::DESCENDING));
        $criteria->setLimit(1);
        $criteria->addAssociation('contentLayout');

        return $repository->search($criteria, $context->getContext())->getEntities()->first();
    }
}
