<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Extension;

use Contena\Core\Content\Blog\Channel\PurchaseLimit\BlogPurchaseLimitRouteResponse;
use Contena\Core\Framework\Extensions\Extension;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @public
 *
 * @codeCoverageIgnore
 *
 * @extends Extension<BlogPurchaseLimitRouteResponse>
 */
final class BlogPurchaseLimitRouteExtension extends Extension
{
    public const NAME = 'blog-purchase-limit-route.read-blogs-purchase-limit';

    /**
     * @internal Contena owns the constructor; the properties are public API.
     */
    public function __construct(
        public readonly Request $request,
        public readonly ChannelContext $context,
    ) {
    }
}
