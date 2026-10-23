<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Cms\DataResolver\Fixtures;

use Contena\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Contena\Core\Content\Cms\DataResolver\CriteriaCollection;
use Contena\Core\Content\Cms\DataResolver\Element\CmsElementResolverInterface;
use Contena\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Contena\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;

/**
 * @internal
 */
class MultiCmsElementResolver implements CmsElementResolverInterface
{
    public function __construct(
        private readonly string $type,
        private readonly string $entity
    ) {
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function collect(CmsSlotEntity $slot, ResolverContext $resolverContext): ?CriteriaCollection
    {
        $criteriaCollection = new CriteriaCollection();
        $criteria = new Criteria();
        $criteria->setLimit(1);
        $criteriaCollection->add('fetch', $this->entity, $criteria);

        return $criteriaCollection;
    }

    public function enrich(CmsSlotEntity $slot, ResolverContext $resolverContext, ElementDataCollection $result): void
    {
        /** @var EntitySearchResult<EntityCollection<Entity>> $fetchResult */
        $fetchResult = $result->get('fetch');
        $slot->setData($fetchResult);
    }
}
