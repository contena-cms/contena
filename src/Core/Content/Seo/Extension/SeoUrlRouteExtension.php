<?php declare(strict_types=1);

namespace Contena\Core\Content\Seo\Extension;

use Contena\Core\Content\Seo\SalesChannel\SeoUrlRouteResponse;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Extensions\Extension;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @public
 *
 * @codeCoverageIgnore
 *
 * @extends Extension<SeoUrlRouteResponse>
 */
#[Package('inventory')]
final class SeoUrlRouteExtension extends Extension
{
    public const NAME = 'seo-url-route.load';

    /**
     * @internal Contena owns the constructor; the properties are public API.
     */
    public function __construct(
        public readonly Request $request,
        public readonly SalesChannelContext $context,
        public readonly Criteria $criteria,
    ) {
    }
}
