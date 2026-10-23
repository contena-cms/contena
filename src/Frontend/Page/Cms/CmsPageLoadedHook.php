<?php declare(strict_types=1);

namespace Contena\Frontend\Page\Cms;

use Contena\Core\Content\Cms\CmsPageEntity;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Frontend\Page\PageLoadedHook;
use Contena\Core\Framework\Script\Execution\Awareness\ChannelContextAwareTrait;

/**
 * Triggered when a CmsPage is loaded
 *
 * @hook-use-case data_loading
 *
 * @since 6.4.8.0
 *
 * @final
 */
class CmsPageLoadedHook extends PageLoadedHook
{
    use ChannelContextAwareTrait;

    final public const HOOK_NAME = 'cms-page-loaded';

    public function __construct(
        private readonly CmsPageEntity $page,
        ChannelContext $context
    ) {
        parent::__construct($context->getContext());
        $this->channelContext = $context;
    }

    public function getName(): string
    {
        return self::HOOK_NAME;
    }

    public function getPage(): CmsPageEntity
    {
        return $this->page;
    }
}
