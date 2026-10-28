<?php declare(strict_types=1);

namespace Contena\Storefront\Page\LandingPage;

use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Script\Execution\Awareness\SalesChannelContextAwareTrait;
use Contena\Core\System\SalesChannel\SalesChannelContext;
use Contena\Storefront\Page\PageLoadedHook;

/**
 * Triggered when the LandingPage is loaded
 *
 * @hook-use-case data_loading
 *
 * @since 6.4.8.0
 *
 * @final
 */
#[Package('discovery')]
class LandingPageLoadedHook extends PageLoadedHook
{
    use SalesChannelContextAwareTrait;

    final public const HOOK_NAME = 'landing-page-loaded';

    public function __construct(
        private readonly LandingPage $page,
        SalesChannelContext $context
    ) {
        parent::__construct($context->getContext());
        $this->salesChannelContext = $context;
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
