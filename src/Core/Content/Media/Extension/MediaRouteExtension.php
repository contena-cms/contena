<?php declare(strict_types=1);

namespace Contena\Core\Content\Media\Extension;

use Contena\Core\Content\Media\Channel\MediaRouteResponse;
use Contena\Core\Framework\Extensions\Extension;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @public
 *
 * @codeCoverageIgnore
 *
 * @extends Extension<MediaRouteResponse>
 */
final class MediaRouteExtension extends Extension
{
    public const NAME = 'media-route.load';

    /**
     * @internal Contena owns the constructor; the properties are public API.
     */
    public function __construct(
        public readonly Request $request,
        public readonly ChannelContext $context,
    ) {
    }
}
