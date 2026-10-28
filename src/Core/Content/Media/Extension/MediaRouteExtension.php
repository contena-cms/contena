<?php declare(strict_types=1);

namespace Contena\Core\Content\Media\Extension;

use Contena\Core\Content\Media\SalesChannel\MediaRouteResponse;
use Contena\Core\Framework\Extensions\Extension;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @public
 *
 * @codeCoverageIgnore
 *
 * @extends Extension<MediaRouteResponse>
 */
#[Package('discovery')]
final class MediaRouteExtension extends Extension
{
    public const NAME = 'media-route.load';

    /**
     * @internal Contena owns the constructor; the properties are public API.
     */
    public function __construct(
        public readonly Request $request,
        public readonly SalesChannelContext $context,
    ) {
    }
}
