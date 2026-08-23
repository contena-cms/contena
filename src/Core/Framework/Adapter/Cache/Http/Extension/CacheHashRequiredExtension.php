<?php declare(strict_types=1);

namespace Contena\Core\Framework\Adapter\Cache\Http\Extension;

use Contena\Core\Framework\Extensions\Extension;
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
     * @internal Contena owns the constructor, but the properties are public API
     */
    public function __construct(
        /**
         * @public
         */
        public readonly Request $request,

        /**
         * @public
         */
        public readonly ChannelContext $channelContext,
    ) {
    }
}
