<?php declare(strict_types=1);

namespace Contena\Core\Content\Seo\SeoUrlRoute;

use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\System\Channel\ChannelEntity;

/**
 * @internal
 */
interface EntitySeoUrlRouteInterface
{
    public function getConfig(): SeoUrlRouteConfig;

    public function prepareCriteria(Criteria $criteria, ChannelEntity $channel): void;
}
