<?php declare(strict_types=1);

namespace Contena\Frontend\Pagelet\Menu\Offcanvas;

use Contena\Core\System\Channel\ChannelContext;
use Contena\Frontend\Page\PageLoadedHook;
use Contena\Core\Framework\Script\Execution\Awareness\ChannelContextAwareTrait;

/**
 * Triggered when the MenuOffcanvasPagelet is loaded
 *
 * @hook-use-case data_loading
 *
 * @since 6.4.8.0
 *
 * @final
 */
class MenuOffcanvasPageletLoadedHook extends PageLoadedHook
{
    use ChannelContextAwareTrait;

    final public const HOOK_NAME = 'menu-offcanvas-pagelet-loaded';

    public function __construct(
        private readonly MenuOffcanvasPagelet $page,
        ChannelContext $context
    ) {
        parent::__construct($context->getContext());
        $this->channelContext = $context;
    }

    public function getName(): string
    {
        return self::HOOK_NAME;
    }

    public function getPage(): MenuOffcanvasPagelet
    {
        return $this->page;
    }
}
