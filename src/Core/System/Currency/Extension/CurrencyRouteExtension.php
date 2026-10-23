<?php declare(strict_types=1);

namespace Contena\Core\System\Currency\Extension;

use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Extensions\Extension;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Currency\Channel\CurrencyRouteResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * @public
 *
 * @codeCoverageIgnore
 *
 * @extends Extension<CurrencyRouteResponse>
 */
final class CurrencyRouteExtension extends Extension
{
    public const NAME = 'currency-route.load';

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
