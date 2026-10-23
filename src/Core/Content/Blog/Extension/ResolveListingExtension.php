<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Extension;

use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\Extensions\Extension;
use Contena\Core\System\Channel\ChannelContext;

/**
 * @codeCoverageIgnore
 *
 * @extends Extension<EntitySearchResult<BlogCollection>>
 */
final class ResolveListingExtension extends Extension
{
    public const NAME = 'listing-loader.resolve';

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
        public readonly ChannelContext $context
    ) {
    }
}
