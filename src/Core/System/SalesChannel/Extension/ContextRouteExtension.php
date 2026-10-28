<?php declare(strict_types=1);

namespace Contena\Core\System\SalesChannel\Extension;

use Contena\Core\Framework\Extensions\Extension;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\SalesChannel\ContextLoadRouteResponse;
use Contena\Core\System\SalesChannel\SalesChannelContext;

/**
 * @public
 *
 * @codeCoverageIgnore
 *
 * @extends Extension<ContextLoadRouteResponse>
 */
#[Package('framework')]
final class ContextRouteExtension extends Extension
{
    public const NAME = 'context-route.load';

    /**
     * @internal Contena owns the constructor; the properties are public API.
     */
    public function __construct(
        public readonly SalesChannelContext $context,
    ) {
    }
}
