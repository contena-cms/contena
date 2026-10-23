<?php declare(strict_types=1);

namespace Contena\Frontend\Page\Account\RecoverPassword;

use Contena\Core\System\Channel\ChannelContext;
use Contena\Frontend\Page\PageLoadedHook;
use Contena\Core\Framework\Script\Execution\Awareness\ChannelContextAwareTrait;

/**
 * Triggered when the AccountRecoverPasswordPage is loaded
 *
 * @hook-use-case data_loading
 *
 * @since 6.4.13.0
 *
 * @final
 */
class AccountRecoverPasswordPageLoadedHook extends PageLoadedHook
{
    use ChannelContextAwareTrait;

    final public const HOOK_NAME = 'account-recover-password-page-loaded';

    public function __construct(
        private readonly AccountRecoverPasswordPage $page,
        ChannelContext $context
    ) {
        parent::__construct($context->getContext());
        $this->channelContext = $context;
    }

    public function getName(): string
    {
        return self::HOOK_NAME;
    }

    public function getPage(): AccountRecoverPasswordPage
    {
        return $this->page;
    }
}
