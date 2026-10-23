<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Extension;

use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Extensions\Extension;
use Contena\Core\System\Channel\ChannelContext;

/**
 * @codeCoverageIgnore
 *
 * @extends Extension<Criteria>
 */
final class BlogListingCriteriaExtension extends Extension
{
    public const NAME = 'blog.listing.criteria';

    /**
     * @internal contena owns the __constructor, but the properties are public API
     */
    public function __construct(
        /**
         * @public
         *
         * @description The criteria which should be used to load the blogs. Is also containing the selected user filter
         */
        public readonly Criteria $criteria,

        /**
         * @public
         *
         * @description Allows you to access to the current user/sales-channel context
         */
        public readonly ChannelContext $context,
        /**
         * @public
         *
         * @description Contains current category id
         */
        public readonly string $categoryId,
    ) {
    }
}
