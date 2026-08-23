<?php declare(strict_types=1);

namespace Contena\Core\Framework\Adapter\Cache\Http\Extension;

use Contena\Core\Framework\Extensions\Extension;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @extends Extension<array<string>>
 *
 * @codeCoverageIgnore
 */
final class ResolveCacheRelevantRuleIdsExtension extends Extension
{
    public const string NAME = 'cache-response.resolve-rule-areas';

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
         *
         * @var list<string>
         */
        public array $ruleAreas,

        /**
         * @public
         */
        public readonly ChannelContext $channelContext,
    ) {
    }
}
