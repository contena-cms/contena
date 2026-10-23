<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Extension;

use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\Extensions\Extension;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Channel\ChannelContext;

/**
 * @public this class is used as type-hint for all event listeners, so the class string is "public consumable" API
 *
 * @title Calculation of blog prices
 *
 * @description This event allows to intercept the blog price calculation process.
 *
 * @codeCoverageIgnore
 *
 * @extends Extension<void>
 */
final class BlogPriceCalculationExtension extends Extension
{
    public const NAME = 'blog.calculate-prices';

    /**
     * @internal contena owns the __constructor, but the properties are public API
     */
    public function __construct(
        /**
         * @public
         *
         * @description The blogs which has to be calculated
         *
         * @var iterable<Entity> $blogs
         */
        public readonly iterable $blogs,

        /**
         * @public
         *
         * @description Allows you to access to the current user/sales-channel context
         */
        public readonly ChannelContext $context
    ) {
    }
}
