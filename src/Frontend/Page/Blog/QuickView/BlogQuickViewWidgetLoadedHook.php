<?php declare(strict_types=1);

namespace Contena\Frontend\Page\Blog\QuickView;

use Contena\Core\System\Channel\ChannelContext;
use Contena\Frontend\Page\PageLoadedHook;

/**
 * Triggered when the BlogQuickViewWidget is loaded
 *
 * @hook-use-case data_loading
 *
 * @since 6.4.8.0
 *
 * @final
 */
class BlogQuickViewWidgetLoadedHook extends PageLoadedHook
{
    use ChannelContextAwareTrait;

    final public const HOOK_NAME = 'blog-quick-view-widget-loaded';

    public function __construct(
        private readonly MinimalQuickViewPage $page,
        ChannelContext $context
    ) {
        parent::__construct($context->getContext());
        $this->channelContext = $context;
    }

    public function getName(): string
    {
        return self::HOOK_NAME;
    }

    public function getPage(): MinimalQuickViewPage
    {
        return $this->page;
    }
}
