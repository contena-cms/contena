<?php declare(strict_types=1);

namespace Contena\Core\Content\Cms\Extension;

use Contena\Core\Content\Cms\Channel\CmsRouteResponse;
use Contena\Core\Framework\Extensions\Extension;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @public
 *
 * @codeCoverageIgnore
 *
 * @extends Extension<CmsRouteResponse>
 */
final class CmsRouteExtension extends Extension
{
    public const NAME = 'cms-route.load';

    /**
     * @internal Contena owns the constructor; the properties are public API.
     */
    public function __construct(
        public readonly string $id,
        public readonly Request $request,
        public readonly ChannelContext $context,
    ) {
    }
}
