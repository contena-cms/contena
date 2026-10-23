<?php declare(strict_types=1);

namespace Contena\Core\Content\Sitemap\Extension;

use Contena\Core\Content\Sitemap\Channel\SitemapRouteResponse;
use Contena\Core\Framework\Extensions\Extension;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @public
 *
 * @codeCoverageIgnore
 *
 * @extends Extension<SitemapRouteResponse>
 */
final class SitemapRouteExtension extends Extension
{
    public const NAME = 'sitemap-route.load';

    /**
     * @internal Contena owns the constructor; the properties are public API.
     */
    public function __construct(
        public readonly Request $request,
        public readonly ChannelContext $context,
    ) {
    }
}
