<?php declare(strict_types=1);

namespace Contena\Storefront\Page\Address\Detail;

use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Script\Execution\Awareness\SalesChannelContextAwareTrait;
use Contena\Core\System\SalesChannel\SalesChannelContext;
use Contena\Storefront\Page\PageLoadedHook;

/**
 * Triggered when the AddressDetailPage is loaded
 *
 * @hook-use-case data_loading
 *
 * @since 6.4.8.0
 *
 * @final
 */
#[Package('checkout')]
class AddressDetailPageLoadedHook extends PageLoadedHook
{
    use SalesChannelContextAwareTrait;

    final public const HOOK_NAME = 'address-detail-page-loaded';

    public function __construct(
        private readonly AddressDetailPage $page,
        SalesChannelContext $context
    ) {
        parent::__construct($context->getContext());
        $this->salesChannelContext = $context;
    }

    public function getName(): string
    {
        return self::HOOK_NAME;
    }

    public function getPage(): AddressDetailPage
    {
        return $this->page;
    }
}
