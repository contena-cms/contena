<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Extension;

use Contena\Core\Content\Blog\Channel\Garan\GaranLabelRouteResponse;
use Contena\Core\Framework\Extensions\Extension;
use Contena\Core\System\Channel\ChannelContext;

/**
 * @public
 *
 * @codeCoverageIgnore
 *
 * @extends Extension<GaranLabelRouteResponse>
 */
final class GaranLabelRouteExtension extends Extension
{
    public const NAME = 'garan-label-route.load';

    /**
     * @internal Contena owns the constructor; the properties are public API.
     */
    public function __construct(
        public readonly string $blogId,
        public readonly ChannelContext $context,
    ) {
    }
}
