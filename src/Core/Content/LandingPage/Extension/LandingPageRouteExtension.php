<?php declare(strict_types=1);

namespace Contena\Core\Content\LandingPage\Extension;

use Contena\Core\Content\LandingPage\SalesChannel\LandingPageRouteResponse;
use Contena\Core\Framework\Extensions\Extension;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @public
 *
 * @codeCoverageIgnore
 *
 * @extends Extension<LandingPageRouteResponse>
 */
#[Package('discovery')]
final class LandingPageRouteExtension extends Extension
{
    public const NAME = 'landing-page-route.load';

    /**
     * @internal Contena owns the constructor; the properties are public API.
     */
    public function __construct(
        public readonly string $landingPageId,
        public readonly Request $request,
        public readonly SalesChannelContext $context,
    ) {
    }
}
