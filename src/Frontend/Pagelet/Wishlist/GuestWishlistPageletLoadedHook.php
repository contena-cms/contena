<?php declare(strict_types=1);

namespace Contena\Frontend\Pagelet\Wishlist;

use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Script\Execution\Awareness\ChannelContextAwareTrait;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Frontend\Page\PageLoadedHook;

/**
 * Triggered when the GuestWishlistPagelet is loaded
 *
 * @hook-use-case data_loading
 *
 * @since 6.4.8.0
 *
 * @final
 */
class GuestWishlistPageletLoadedHook extends PageLoadedHook
{
    use ChannelContextAwareTrait;

    final public const HOOK_NAME = 'guest-wishlist-pagelet-loaded';

    public function __construct(
        private readonly GuestWishlistPagelet $page,
        ChannelContext $context
    ) {
        parent::__construct($context->getContext());
        $this->channelContext = $context;
    }

    public function getName(): string
    {
        return self::HOOK_NAME;
    }

    public function getPage(): GuestWishlistPagelet
    {
        return $this->page;
    }
}
