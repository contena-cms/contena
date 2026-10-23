<?php declare(strict_types=1);

namespace Contena\Core\Content\Category\Extension;

use Contena\Core\Content\Category\Channel\CategoryListRouteResponse;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Extensions\Extension;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Channel\ChannelContext;

/**
 * @public
 *
 * @codeCoverageIgnore
 *
 * @extends Extension<CategoryListRouteResponse>
 */
final class CategoryListRouteExtension extends Extension
{
    public const NAME = 'category-list-route.load';

    /**
     * @internal Contena owns the constructor; the properties are public API.
     */
    public function __construct(
        public readonly Criteria $criteria,
        public readonly ChannelContext $context,
    ) {
    }
}
