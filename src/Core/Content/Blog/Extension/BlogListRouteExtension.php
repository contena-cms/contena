<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Extension;

use Contena\Core\Content\Blog\Channel\BlogListResponse;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Extensions\Extension;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Channel\ChannelContext;

/**
 * @public
 *
 * @codeCoverageIgnore
 *
 * @extends Extension<BlogListResponse>
 */
final class BlogListRouteExtension extends Extension
{
    public const NAME = 'blog-list-route.load';

    /**
     * @internal Contena owns the constructor; the properties are public API.
     */
    public function __construct(
        public readonly Criteria $criteria,
        public readonly ChannelContext $context,
    ) {
    }
}
