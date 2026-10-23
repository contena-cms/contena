<?php declare(strict_types=1);

namespace Contena\Core\Content\Breadcrumb\Extension;

use Contena\Core\Content\Breadcrumb\Channel\BreadcrumbRouteResponse;
use Contena\Core\Framework\Extensions\Extension;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @public
 *
 * @codeCoverageIgnore
 *
 * @extends Extension<BreadcrumbRouteResponse>
 */
final class BreadcrumbRouteExtension extends Extension
{
    public const NAME = 'breadcrumb-route.load';

    /**
     * @internal Contena owns the constructor; the properties are public API.
     */
    public function __construct(
        public readonly Request $request,
        public readonly ChannelContext $channelContext,
    ) {
    }
}
