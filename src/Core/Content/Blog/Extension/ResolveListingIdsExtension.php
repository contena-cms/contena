<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Extension;

use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Contena\Core\Framework\Extensions\Extension;
use Contena\Core\System\Channel\ChannelContext;

/**
 * @public this class is used as type-hint for all event listeners, so the class string is "public consumable" API
 *
 * @title Determination of the listing blog ids
 *
 * @description This event allows intercepting the listing process, when the blog ids should be determined for the current category page and the applied filter.
 *
 * @codeCoverageIgnore
 *
 * @extends Extension<IdSearchResult>
 */
final class ResolveListingIdsExtension extends Extension
{
    public const NAME = 'listing-loader.resolve-listing-ids';

    /**
     * @internal contena owns the __constructor, but the properties are public API
     */
    public function __construct(
        /**
         * @public
         *
         * @description The criteria which should be used to load the blog ids. Is also containing the selected user filter
         */
        public Criteria $criteria,

        /**
         * @public
         *
         * @description Allows you to access to the current user/sales-channel context
         */
        public ChannelContext $context
    ) {
    }
}
