<?php declare(strict_types=1);

namespace Contena\Core\System\User\Extension;

use Contena\Core\Framework\Extensions\Extension;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\ContextTokenResponse;

/**
 * @public
 *
 * @codeCoverageIgnore
 *
 * @extends Extension<ContextTokenResponse>
 */
final class LogoutRouteExtension extends Extension
{
    public const NAME = 'logout-route.logout';

    /**
     * @internal Contena owns the constructor; the properties are public API.
     */
    public function __construct(
        public readonly ChannelContext $context,
        public readonly RequestDataBag $data,
    ) {
    }
}
