<?php declare(strict_types=1);

namespace Contena\Storefront\Pagelet\Header;

use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Script\Execution\Awareness\SalesChannelContextAwareTrait;
use Contena\Core\System\SalesChannel\SalesChannelContext;
use Contena\Storefront\Page\PageLoadedHook;

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
#[Package('discovery')]
class HeaderPageletLoadedHook extends PageLoadedHook
{
    use SalesChannelContextAwareTrait;

    final public const HOOK_NAME = 'header-pagelet-loaded';

    public function __construct(
        private readonly HeaderPagelet $page,
        SalesChannelContext $context
    ) {
        parent::__construct($context->getContext());
        $this->salesChannelContext = $context;
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
