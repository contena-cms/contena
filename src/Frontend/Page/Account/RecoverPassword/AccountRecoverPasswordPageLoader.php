<?php declare(strict_types=1);

namespace Contena\Frontend\Page\Account\RecoverPassword;

use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Member\Channel\AbstractMemberRecoveryIsExpiredRoute;
use Contena\Frontend\Page\GenericPageLoaderInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Do not use direct or indirect repository calls in a PageLoader. Always use a channel-api route to get or put data.
 */
class AccountRecoverPasswordPageLoader
{
    /**
     * @internal
     */
    public function __construct(
        private readonly GenericPageLoaderInterface $genericLoader,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly AbstractMemberRecoveryIsExpiredRoute $recoveryIsExpiredRoute,
    ) {
    }

    public function load(Request $request, ChannelContext $context, string $hash): AccountRecoverPasswordPage
    {
        $page = $this->genericLoader->load($request, $context);

        $page = AccountRecoverPasswordPage::createFrom($page);
        $page->setHash($hash);

        $memberRecoveryResponse = $this->recoveryIsExpiredRoute
            ->load(new RequestDataBag(['hash' => $hash]), $context);

        $page->setHashExpired($memberRecoveryResponse->isExpired());

        $this->eventDispatcher->dispatch(
            new AccountRecoverPasswordPageLoadedEvent($page, $context, $request),
        );

        return $page;
    }
}
