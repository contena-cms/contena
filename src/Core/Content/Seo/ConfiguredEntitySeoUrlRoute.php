<?php

declare(strict_types=1);

namespace Contena\Core\Content\Seo;

use Contena\Core\Content\Category\CategoryEntity;
use Contena\Core\Content\Category\Util\CategoryBreadcrumbHelper;
use Contena\Core\Content\Seo\SeoUrlRoute\EntitySeoUrlRouteInterface;
use Contena\Core\Content\Seo\SeoUrlRoute\SeoUrlMapping;
use Contena\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteInterface;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\SalesChannelEntity;

use function Symfony\Component\String\u;

/**
 * @internal
 */
#[Package('inventory')]
class ConfiguredEntitySeoUrlRoute extends ConfiguredSeoUrlRoute
{
    public function __construct(
        private readonly EntitySeoUrlRouteInterface $decorated,
    ) {
        parent::__construct($this, $decorated->getConfig());
    }

    public function prepareCriteria(Criteria $criteria, SalesChannelEntity $salesChannel): void
    {
        $this->decorated->prepareCriteria($criteria, $salesChannel);
    }

    public function getMapping(Entity $entity, ?SalesChannelEntity $salesChannel): SeoUrlMapping
    {
        if ($this->decorated instanceof SeoUrlRouteInterface) {
            return $this->decorated->getMapping($entity, $salesChannel);
        }

        // Fallback for config-only routes: expose the entity in the template under its entity name.
        $serialized = $entity->jsonSerialize();

        if ($entity instanceof CategoryEntity) {
            $serialized['seoBreadcrumb'] = CategoryBreadcrumbHelper::build($entity, $salesChannel);
        }

        return new SeoUrlMapping(
            $entity,
            $this->getConfig()->getPrimaryKeyParameter($entity->getUniqueIdentifier()),
            [u($this->getConfig()->getDefinition()->getEntityName())->camel()->toString() => $serialized]
        );
    }
}
