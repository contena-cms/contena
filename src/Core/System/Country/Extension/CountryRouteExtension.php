<?php declare(strict_types=1);

namespace Contena\Core\System\Country\Extension;

use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Extensions\Extension;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Country\Channel\CountryRouteResponse;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @public
 *
 * @codeCoverageIgnore
 *
 * @extends Extension<CountryRouteResponse>
 */
final class CountryRouteExtension extends Extension
{
    public const NAME = 'country-route.load';

    /**
     * @internal Contena owns the constructor; the properties are public API.
     */
    public function __construct(
        public readonly Request $request,
        public readonly Criteria $criteria,
        public readonly ChannelContext $context,
    ) {
    }
}
