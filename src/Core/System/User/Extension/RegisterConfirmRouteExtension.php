<?php declare(strict_types=1);

namespace Contena\Core\System\User\Extension;

use Contena\Core\Framework\Extensions\Extension;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\User\Channel\UserResponse;

/**
 * @public
 *
 * @codeCoverageIgnore
 *
 * @extends Extension<UserResponse>
 */
final class RegisterConfirmRouteExtension extends Extension
{
    public const NAME = 'register-confirm-route.confirm';

    /**
     * @internal Contena owns the constructor; the properties are public API.
     */
    public function __construct(
        public readonly RequestDataBag $dataBag,
        public readonly ChannelContext $context,
    ) {
    }
}
