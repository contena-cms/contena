<?php declare(strict_types=1);

namespace Contena\Storefront\Page\Account\RecoverPassword;

use Contena\Core\Checkout\Customer\Exception\CustomerNotFoundByHashException;
use Contena\Core\Checkout\Customer\SalesChannel\AbstractCustomerRecoveryIsExpiredRoute;
use Contena\Core\Content\Category\Exception\CategoryNotFoundException;
use Contena\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Routing\RoutingException;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\Framework\Validation\Exception\ConstraintViolationException;
use Contena\Core\System\SalesChannel\SalesChannelContext;
use Contena\Storefront\Page\GenericPageLoaderInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Do not use direct or indirect repository calls in a PageLoader. Always use a store-api route to get or put data.
 */
#[Package('checkout')]
class AccountRecoverPasswordPageLoader
{
    /**
     * @internal
     */
    public function __construct(
        private readonly GenericPageLoaderInterface $genericLoader,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly AbstractCustomerRecoveryIsExpiredRoute $recoveryIsExpiredRoute
    ) {
    }

    /**
     * @throws CategoryNotFoundException
     * @throws InconsistentCriteriaIdsException
     * @throws RoutingException
     * @throws ConstraintViolationException
     * @throws CustomerNotFoundByHashException
     */
    public function load(Request $request, SalesChannelContext $context, string $hash): AccountRecoverPasswordPage
    {
        $page = $this->genericLoader->load($request, $context);

        $page = AccountRecoverPasswordPage::createFrom($page);
        $page->setHash($hash);

        $customerHashCriteria = new Criteria();
        $customerHashCriteria->addFilter(new EqualsFilter('hash', $hash));

        $customerRecoveryResponse = $this->recoveryIsExpiredRoute
            ->load(new RequestDataBag(['hash' => $hash]), $context);

        $page->setHashExpired($customerRecoveryResponse->isExpired());

        $this->eventDispatcher->dispatch(
            new AccountRecoverPasswordPageLoadedEvent($page, $context, $request)
        );

        return $page;
    }
}
