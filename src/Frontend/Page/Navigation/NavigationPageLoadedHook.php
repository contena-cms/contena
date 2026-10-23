<?php declare(strict_types=1);

namespace Contena\Frontend\Page\Navigation;

use Contena\Core\System\Channel\ChannelContext;
use Contena\Frontend\Page\PageLoadedHook;
use Contena\Core\Framework\Script\Execution\Awareness\ChannelContextAwareTrait;

/**
 * Triggered when the NavigationPage is loaded
 *
 * @hook-use-case data_loading
 *
 * @since 6.4.8.0
 *
 * @final
 */
class NavigationPageLoadedHook extends PageLoadedHook
{
    use ChannelContextAwareTrait;

    final public const HOOK_NAME = 'navigation-page-loaded';

    public function __construct(
        private readonly NavigationPage $page,
        ChannelContext $context
    ) {
        parent::__construct($context->getContext());
        $this->channelContext = $context;
    }

    public function getName(): string
    {
        return self::HOOK_NAME;
    }

    public function getPage(): NavigationPage
    {
        return $this->page;
    }
}
