<?php declare(strict_types=1);

namespace Contena\Core\Content\Category\Extension;

use Contena\Core\Content\Category\Channel\CategoryRouteResponse;
use Contena\Core\Framework\Extensions\Extension;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @public
 *
 * @codeCoverageIgnore
 *
 * @extends Extension<CategoryRouteResponse>
 */
final class CategoryRouteExtension extends Extension
{
    public const NAME = 'category-route.load';

    /**
     * @internal Contena owns the constructor; the properties are public API.
     */
    public function __construct(
        public readonly string $navigationId,
        public readonly Request $request,
        public readonly ChannelContext $context,
    ) {
    }
}
