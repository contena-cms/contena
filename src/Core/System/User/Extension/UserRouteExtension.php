<?php declare(strict_types=1);

namespace Contena\Core\System\User\Extension;

use Contena\Core\System\User\UserEntity;
use Contena\Core\System\User\Channel\UserResponse;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Extensions\Extension;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @public
 *
 * @codeCoverageIgnore
 *
 * @extends Extension<UserResponse>
 */
final class UserRouteExtension extends Extension
{
    public const NAME = 'user-route.load';

    /**
     * @internal Contena owns the constructor; the properties are public API.
     */
    public function __construct(
        public readonly Request $request,
        public readonly ChannelContext $context,
        public readonly Criteria $criteria,
        public readonly UserEntity $user,
    ) {
    }
}
