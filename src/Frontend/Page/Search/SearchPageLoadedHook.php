<?php declare(strict_types=1);

namespace Contena\Frontend\Page\Search;

use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Script\Execution\Awareness\ChannelContextAwareTrait;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Frontend\Page\PageLoadedHook;

/**
 * Triggered when the SearchPage is loaded
 *
 * @hook-use-case data_loading
 *
 * @since 6.4.8.0
 *
 * @final
 */
class SearchPageLoadedHook extends PageLoadedHook
{
    use ChannelContextAwareTrait;

    final public const HOOK_NAME = 'search-page-loaded';

    public function __construct(
        private readonly SearchPage $page,
        ChannelContext $context
    ) {
        parent::__construct($context->getContext());
        $this->channelContext = $context;
    }

    public function getName(): string
    {
        return self::HOOK_NAME;
    }

    public function getPage(): SearchPage
    {
        return $this->page;
    }
}
