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
final class ImitateUserRouteExtension extends Extension
{
    public const NAME = 'imitate-user-route.imitate-user-login';

    /**
     * @internal Contena owns the constructor; the properties are public API.
     */
    public function __construct(
        public readonly RequestDataBag $data,
        public readonly ChannelContext $context,
    ) {
    }
}
