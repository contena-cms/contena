<?php declare(strict_types=1);

namespace Contena\Core\System\User\Extension;

use Contena\Core\Framework\Extensions\Extension;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\SuccessResponse;
use Contena\Core\System\User\UserEntity;

/**
 * @public
 *
 * @codeCoverageIgnore
 *
 * @extends Extension<SuccessResponse>
 */
final class ChangeUserProfileRouteExtension extends Extension
{
    public const NAME = 'change-user-profile-route.change';

    /**
     * @internal Contena owns the constructor; the properties are public API.
     */
    public function __construct(
        public readonly RequestDataBag $data,
        public readonly ChannelContext $context,
        public readonly UserEntity $user,
    ) {
    }
}
