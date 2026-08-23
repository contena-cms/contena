<?php declare(strict_types=1);

namespace Contena\Core\Content\Seo;

use Contena\Core\Content\Category\CategoryEntity;
use Contena\Core\Content\Category\Util\CategoryBreadcrumbHelper;
use Contena\Core\Content\Seo\SeoUrlRoute\EntitySeoUrlRouteInterface;
use Contena\Core\Content\Seo\SeoUrlRoute\SeoUrlMapping;
use Contena\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteInterface;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\System\Channel\ChannelEntity;

use function Symfony\Component\String\u;

/**
 * @internal
 */
class ConfiguredEntitySeoUrlRoute extends ConfiguredSeoUrlRoute
{
    public function __construct(private readonly EntitySeoUrlRouteInterface $decorated)
    {
        parent::__construct($this, $decorated->getConfig());
    }

    public function prepareCriteria(Criteria $criteria, ChannelEntity $channel): void
    {
        $this->decorated->prepareCriteria($criteria, $channel);
    }

    public function getMapping(Entity $entity, ?ChannelEntity $channel): SeoUrlMapping
    {
        if ($this->decorated instanceof SeoUrlRouteInterface) {
            return $this->decorated->getMapping($entity, $channel);
        }

        $serialized = $entity->jsonSerialize();

        if ($entity instanceof CategoryEntity) {
            $serialized['seoBreadcrumb'] = CategoryBreadcrumbHelper::build($entity, $channel);
        }

        return new SeoUrlMapping(
            $entity,
            $this->getConfig()->getPrimaryKeyParameter($entity->getUniqueIdentifier()),
            [u($this->getConfig()->getDefinition()->getEntityName())->camel()->toString() => $serialized]
        );
    }
}
