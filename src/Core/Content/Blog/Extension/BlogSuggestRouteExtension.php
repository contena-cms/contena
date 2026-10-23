<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Extension;

use Contena\Core\Content\Blog\Channel\Suggest\BlogSuggestRouteResponse;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Extensions\Extension;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @public
 *
 * @codeCoverageIgnore
 *
 * @extends Extension<BlogSuggestRouteResponse>
 */
final class BlogSuggestRouteExtension extends Extension
{
    public const NAME = 'blog-suggest-route.load';

    /**
     * @internal Contena owns the constructor; the properties are public API.
     */
    public function __construct(
        public readonly Request $request,
        public readonly ChannelContext $context,
        public readonly Criteria $criteria,
    ) {
    }
}
