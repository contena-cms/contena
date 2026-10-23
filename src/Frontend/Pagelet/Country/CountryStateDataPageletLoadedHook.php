<?php declare(strict_types=1);

namespace Contena\Frontend\Pagelet\Country;

use Contena\Core\System\Channel\ChannelContext;
use Contena\Frontend\Page\PageLoadedHook;
use Contena\Core\Framework\Script\Execution\Awareness\ChannelContextAwareTrait;

/**
 * Triggered when the CountryStateDataPagelet is loaded
 *
 * @hook-use-case data_loading
 *
 * @since 6.4.8.0
 *
 * @final
 */
class CountryStateDataPageletLoadedHook extends PageLoadedHook
{
    use ChannelContextAwareTrait;

    final public const HOOK_NAME = 'country-state-data-pagelet-loaded';

    public function __construct(
        private readonly CountryStateDataPagelet $pagelet,
        ChannelContext $context
    ) {
        parent::__construct($context->getContext());
        $this->channelContext = $context;
    }

    public function getName(): string
    {
        return self::HOOK_NAME;
    }

    public function getPage(): CountryStateDataPagelet
    {
        return $this->pagelet;
    }
}
