<?php declare(strict_types=1);

namespace Contena\Core\Framework\Adapter\Cache\Http\Extension;

use Contena\Core\Checkout\Cart\Cart;
use Contena\Core\Framework\Extensions\Extension;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @extends Extension<bool>
 *
 * @codeCoverageIgnore
 */
final class CacheHashRequiredExtension extends Extension
{
    public const NAME = 'cache-hash.required';

    /**
     * @internal Contena owns the __constructor, but the properties are public API
     */
    public function __construct(
        /**
         * @public
         *
         * @description The HTTP request object
         */
        public readonly Request $request,

        /**
         * @public
         *
         * @description The sales channel context
         */
        public readonly ChannelContext $channelContext,

        /**
         * @public
         *
         * @description The current cart
         */
        public readonly Cart $cart,
    ) {
    }
}
