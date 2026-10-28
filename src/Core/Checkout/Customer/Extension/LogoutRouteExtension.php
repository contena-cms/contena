<?php declare(strict_types=1);

namespace Contena\Core\Checkout\Customer\Extension;

use Contena\Core\Framework\Extensions\Extension;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\System\SalesChannel\ContextTokenResponse;
use Contena\Core\System\SalesChannel\SalesChannelContext;

/**
 * @public
 *
 * @codeCoverageIgnore
 *
 * @extends Extension<ContextTokenResponse>
 */
#[Package('checkout')]
final class LogoutRouteExtension extends Extension
{
    public const NAME = 'logout-route.logout';

    /**
     * @internal Contena owns the constructor; the properties are public API.
     */
    public function __construct(
        public readonly SalesChannelContext $context,
        public readonly RequestDataBag $data,
    ) {
    }
}
