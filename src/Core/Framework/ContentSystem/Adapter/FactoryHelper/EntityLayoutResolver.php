<?php declare(strict_types=1);

namespace Contena\Core\Framework\ContentSystem\Adapter\FactoryHelper;

use Contena\Core\Framework\ContentSystem\Adapter\Entity\AbstractContentLayoutAssignmentEntity;
use Contena\Core\Framework\ContentSystem\PlaceholderValues;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\OrFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * Resolves content layout assignments and processes placeholders for entity-based rendering.
 *
 * @internal
 *
 * @final
 */
class EntityLayoutResolver
{
    /**
     * Returns only the content layout ID without loading the full assignment or contentLayout association.
     *
     * @param EntityRepository<covariant EntityCollection<covariant Entity>> $repository
     */
    public function findLayoutId(
        string $entityIdField,
        string $entityId,
        ChannelContext $context,
        EntityRepository $repository
    ): ?string {
        $criteria = $this->buildAssignmentCriteria($entityIdField, $entityId, $context);

        $entity = $repository->search($criteria, $context->getContext())->getEntities()->first();

        if (!$entity instanceof AbstractContentLayoutAssignmentEntity) {
            return null;
        }

        return $entity->getContentLayoutId();
    }

    public function resolvePlaceholders(
        string $entityIdField,
        string $entityId,
        Request $request
    ): PlaceholderValues {
        $scalarParameters = array_filter($request->query->all(), '\is_scalar');

        return PlaceholderValues::from(array_merge(
            [$entityIdField => $entityId],
            $scalarParameters
        ));
    }

    private function buildAssignmentCriteria(string $entityIdField, string $entityId, ChannelContext $context): Criteria
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter($entityIdField, $entityId));
        $criteria->addFilter(new OrFilter([
            new EqualsFilter('channelId', $context->getChannel()->getId()),
            new EqualsFilter('channelId', null),
        ]));
        $criteria->addSorting(new FieldSorting('channelId', FieldSorting::DESCENDING));
        $criteria->setLimit(1);

        return $criteria;
    }
}
