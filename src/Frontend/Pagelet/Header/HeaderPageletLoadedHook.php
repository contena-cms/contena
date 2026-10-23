<?php declare(strict_types=1);

namespace Contena\Frontend\Pagelet\Header;

use Contena\Core\System\Channel\ChannelContext;
use Contena\Frontend\Page\PageLoadedHook;

/**
 * Triggered when the HeaderPagelet is loaded
 *
 * @hook-use-case data_loading
 *
 * @since 6.7.0.0
 *
 * @final
 *
 * @codeCoverageIgnore
 */
class HeaderPageletLoadedHook extends PageLoadedHook
{
    use ChannelContextAwareTrait;

    final public const HOOK_NAME = 'header-pagelet-loaded';

    public function __construct(
        private readonly HeaderPagelet $page,
        ChannelContext $context
    ) {
        parent::__construct($context->getContext());
        $this->channelContext = $context;
    }

    public function getName(): string
    {
        return self::HOOK_NAME;
    }

    public function getPage(): HeaderPagelet
    {
        return $this->page;
    }
}
