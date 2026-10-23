<?php declare(strict_types=1);

namespace Contena\Core\System\User\Extension;

use Contena\Core\Framework\Extensions\Extension;
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
final class AddWishlistBlogRouteExtension extends Extension
{
    public const NAME = 'add-wishlist-blog-route.add';

    /**
     * @internal Contena owns the constructor; the properties are public API.
     */
    public function __construct(
        public readonly string $blogId,
        public readonly ChannelContext $context,
        public readonly UserEntity $user,
    ) {
    }
}
