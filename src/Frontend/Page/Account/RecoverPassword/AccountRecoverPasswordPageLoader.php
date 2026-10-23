<?php declare(strict_types=1);

namespace Contena\Frontend\Page\Account\RecoverPassword;

use Contena\Core\System\User\Channel\AbstractUserRecoveryIsExpiredRoute;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Routing\RoutingException;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Frontend\Page\GenericPageLoaderInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Do not use direct or indirect repository calls in a PageLoader. Always use a store-api route to get or put data.
 */
class AccountRecoverPasswordPageLoader
{
    /**
     * @internal
     */
    public function __construct(
        private readonly GenericPageLoaderInterface $genericLoader,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly AbstractUserRecoveryIsExpiredRoute $recoveryIsExpiredRoute
    ) {
    }

    /**
     * @throws CategoryNotFoundException
     * @throws InconsistentCriteriaIdsException
     * @throws RoutingException
     * @throws ConstraintViolationException
     * @throws UserNotFoundByHashException
     */
    public function load(Request $request, ChannelContext $context, string $hash): AccountRecoverPasswordPage
    {
        $page = $this->genericLoader->load($request, $context);

        $page = AccountRecoverPasswordPage::createFrom($page);
        $page->setHash($hash);

        $userHashCriteria = new Criteria();
        $userHashCriteria->addFilter(new EqualsFilter('hash', $hash));

        $userRecoveryResponse = $this->recoveryIsExpiredRoute
            ->load(new RequestDataBag(['hash' => $hash]), $context);

        $page->setHashExpired($userRecoveryResponse->isExpired());

        $this->eventDispatcher->dispatch(
            new AccountRecoverPasswordPageLoadedEvent($page, $context, $request)
        );

        return $page;
    }
}
