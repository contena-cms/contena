<?php declare(strict_types=1);

namespace Contena\Frontend\Page\LandingPage;

use Contena\Core\System\Channel\ChannelContext;
use Contena\Frontend\Page\PageLoadedHook;

/**
 * Triggered when the LandingPage is loaded
 *
 * @hook-use-case data_loading
 *
 * @since 6.4.8.0
 *
 * @final
 */
class LandingPageLoadedHook extends PageLoadedHook
{
    use ChannelContextAwareTrait;

    final public const HOOK_NAME = 'landing-page-loaded';

    public function __construct(
        private readonly LandingPage $page,
        ChannelContext $context
    ) {
        parent::__construct($context->getContext());
        $this->channelContext = $context;
    }

    public function getName(): string
    {
        return self::HOOK_NAME;
    }

    public function getPage(): LandingPage
    {
        return $this->page;
    }
}
