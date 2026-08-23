<?php declare(strict_types=1);

namespace Contena\Core\Content\Seo\SeoUrlRoute;

use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\System\Channel\ChannelEntity;

interface SeoUrlRouteInterface extends EntitySeoUrlRouteInterface
{
    public function getMapping(Entity $entity, ?ChannelEntity $channel): SeoUrlMapping;
}
