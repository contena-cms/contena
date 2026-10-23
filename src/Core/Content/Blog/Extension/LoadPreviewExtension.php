<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Extension;

use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\Filter;
use Contena\Core\Framework\Extensions\Extension;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Channel\ChannelContext;

/**
 * @public this class is used as type-hint for all event listeners, so the class string is "public consumable" API
 *
 * @title Determination of the preview loading in blog listing
 *
 * @description This event allows intercepting the loading preview of blog listing when the blog IDs should be determined
 *
 * @codeCoverageIgnore
 *
 * @extends Extension<array>
 */
final class LoadPreviewExtension extends Extension
{
    public const NAME = 'listing-loader.load-previews';

    /**
     * @internal contena owns the __constructor, but the properties are public API
     */
    public function __construct(
        /**
         * @public
         *
         * @description The array should contain a list of blog ids.
         *
         * @var array<string>
         */
        public readonly array $ids,
        /**
         * @public
         *
         * @description Allows you to access to the current user/sales-channel context
         */
        public readonly ChannelContext $context,
        /**
         * @public
         *
         * @description The active post filters of the listing. A configured main variant is only used as preview if it matches them.
         *
         * @var list<Filter>
         */
        public readonly array $postFilters = []
    ) {
    }
}
