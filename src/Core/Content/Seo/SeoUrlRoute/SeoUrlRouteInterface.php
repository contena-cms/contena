<?php declare(strict_types=1);

namespace Contena\Core\Content\Seo\SeoUrlRoute;

use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\SalesChannelEntity;

#[Package('inventory')]
interface SeoUrlRouteInterface extends EntitySeoUrlRouteInterface
{
    public function getMapping(Entity $entity, ?SalesChannelEntity $salesChannel): SeoUrlMapping;
}
