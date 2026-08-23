<?php declare(strict_types=1);

namespace Contena\Core\Content\Seo\Channel;

use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * This route can be used to load all SEO URLs of the authenticated channel.
 * With this route it is also possible to send the standard API parameters such as: 'page', 'limit', 'filter', etc.
 */
abstract class AbstractSeoUrlRoute
{
    abstract public function getDecorated(): AbstractSeoUrlRoute;

    abstract public function load(Request $request, ChannelContext $context, Criteria $criteria): SeoUrlRouteResponse;
}
