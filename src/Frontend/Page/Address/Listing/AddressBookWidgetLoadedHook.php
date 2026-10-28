<?php declare(strict_types=1);

namespace Contena\Storefront\Page\Address\Listing;

use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Script\Execution\Awareness\SalesChannelContextAwareTrait;
use Contena\Core\System\SalesChannel\SalesChannelContext;
use Contena\Storefront\Page\PageLoadedHook;

/**
 * Triggered when the AddressBookWidget is loaded
 *
 * @hook-use-case data_loading
 *
 * @since 6.4.8.0
 *
 * @final
 */
#[Package('checkout')]
class AddressBookWidgetLoadedHook extends PageLoadedHook
{
    use SalesChannelContextAwareTrait;

    final public const HOOK_NAME = 'address-book-widget-loaded';

    public function __construct(
        private readonly AddressListingPage $page,
        SalesChannelContext $context
    ) {
        parent::__construct($context->getContext());
        $this->salesChannelContext = $context;
    }

    public function getName(): string
    {
        return self::HOOK_NAME;
    }

    public function getPage(): AddressListingPage
    {
        return $this->page;
    }
}
