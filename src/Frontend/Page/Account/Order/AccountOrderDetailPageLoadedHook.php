<?php declare(strict_types=1);

namespace Contena\Frontend\Page\Account\Order;

use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Script\Execution\Awareness\ChannelContextAwareTrait;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Frontend\Page\PageLoadedHook;

/**
 * Triggered when the AccountOrderDetailPage is loaded
 *
 * @hook-use-case data_loading
 *
 * @since 6.4.8.0
 *
 * @final
 *
 * @deprecated tag:v6.8.0 - Will be removed without replacement
 */
class AccountOrderDetailPageLoadedHook extends PageLoadedHook
{
    use ChannelContextAwareTrait {
        getChannelContext as private getChannelContextHook;
    }

    final public const HOOK_NAME = 'account-order-detail-page-loaded';

    public function __construct(
        private readonly AccountOrderDetailPage $page,
        ChannelContext $context
    ) {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.8.0.0')
        );

        parent::__construct($context->getContext());
        $this->channelContext = $context;
    }

    public function getName(): string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0')
        );

        return self::HOOK_NAME;
    }

    public function getPage(): AccountOrderDetailPage
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0')
        );

        return $this->page;
    }

    public function getChannelContext(): ChannelContext
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0')
        );

        return $this->getChannelContextHook();
    }
}
