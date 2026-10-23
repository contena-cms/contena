<?php declare(strict_types=1);

namespace Contena\Frontend\Controller;

use Contena\Core\System\User\Aggregate\UserAddress\UserAddressEntity;
use Contena\Core\System\User\UserEntity;
use Contena\Core\System\User\UserException;
use Contena\Core\System\User\Exception\AddressNotFoundException;
use Contena\Core\System\User\Exception\CannotDeleteDefaultAddressException;
use Contena\Core\System\User\Channel\AbstractDeleteAddressRoute;
use Contena\Core\System\User\Channel\AbstractListAddressRoute;
use Contena\Core\System\User\Channel\AbstractUpsertAddressRoute;
use Contena\Core\System\User\Channel\AccountService;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Routing\RoutingException;
use Contena\Core\Framework\Uuid\Exception\InvalidUuidException;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Framework\Uuid\UuidException;
use Contena\Core\Framework\Validation\DataBag\DataBag;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\Framework\Validation\Exception\ConstraintViolationException;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\Context\ChannelContextService;
use Contena\Core\System\Channel\Context\ChannelContextServiceInterface;
use Contena\Core\System\Channel\Context\ChannelContextServiceParameters;
use Contena\Core\System\Channel\NoContentResponse;
use Contena\Core\System\Channel\Channel\AbstractContextSwitchRoute;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Frontend\Framework\Routing\FrontendRouteScope;
use Contena\Frontend\Page\Address\AddressEditorModalStruct;
use Contena\Frontend\Page\Address\Detail\AddressDetailPageLoadedHook;
use Contena\Frontend\Page\Address\Detail\AddressDetailPageLoader;
use Contena\Frontend\Page\Address\Listing\AddressBookWidgetLoadedHook;
use Contena\Frontend\Page\Address\Listing\AddressListingPageLoadedHook;
use Contena\Frontend\Page\Address\Listing\AddressListingPageLoader;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 * Do not use direct or indirect repository calls in a controller. Always use a store-api route to get or put data
 */
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [FrontendRouteScope::ID]])]
class AddressController extends FrontendController
{
    private const ADDRESS_TYPE_BILLING = 'billing';
    private const ADDRESS_TYPE_SHIPPING = 'shipping';

    /**
     * @internal
     */
    public function __construct(
        private readonly AddressListingPageLoader $addressListingPageLoader,
        private readonly AddressDetailPageLoader $addressDetailPageLoader,
        private readonly AccountService $accountService,
        private readonly AbstractListAddressRoute $listAddressRoute,
        private readonly AbstractUpsertAddressRoute $updateAddressRoute,
        private readonly AbstractDeleteAddressRoute $deleteAddressRoute,
        private readonly AbstractContextSwitchRoute $contextSwitchRoute,
        private readonly ChannelContextServiceInterface $channelContextService
    ) {
    }

    #[Route(
        path: '/account/address',
        name: 'frontend.account.address.page',
        options: ['seo' => false],
        defaults: [
            PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED => true,
            PlatformRequest::ATTRIBUTE_NO_STORE => true,
        ],
        methods: [Request::METHOD_GET]
    )]
    public function accountAddressOverview(Request $request, ChannelContext $context, UserEntity $user): Response
    {
        $page = $this->addressListingPageLoader->load($request, $context, $user);

        $this->hook(new AddressListingPageLoadedHook($page, $context));

        return $this->renderFrontend('@Frontend/frontend/page/account/addressbook/index.html.twig', ['page' => $page]);
    }

    #[Route(
        path: '/account/address/create',
        name: 'frontend.account.address.create.page',
        options: ['seo' => false],
        defaults: [
            PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED => true,
            PlatformRequest::ATTRIBUTE_NO_STORE => true,
        ],
        methods: [Request::METHOD_GET]
    )]
    public function accountCreateAddress(
        Request $request,
        RequestDataBag $data,
        ChannelContext $context,
        UserEntity $user
    ): Response {
        $page = $this->addressDetailPageLoader->load($request, $context, $user);

        $this->hook(new AddressDetailPageLoadedHook($page, $context));

        return $this->renderFrontend('@Frontend/frontend/page/account/addressbook/create.html.twig', [
            'page' => $page,
            'data' => $data,
        ]);
    }

    #[Route(
        path: '/account/address/{addressId}',
        name: 'frontend.account.address.edit.page',
        options: ['seo' => false],
        defaults: [
            PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED => true,
            PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED_ALLOW_GUEST => true,
            PlatformRequest::ATTRIBUTE_NO_STORE => true,
        ],
        methods: [Request::METHOD_GET]
    )]
    public function accountEditAddress(Request $request, ChannelContext $context, UserEntity $user): Response
    {
        $page = $this->addressDetailPageLoader->load($request, $context, $user);

        $this->hook(new AddressDetailPageLoadedHook($page, $context));

        return $this->renderFrontend('@Frontend/frontend/page/account/addressbook/edit.html.twig', [
            'page' => $page,
            'redirectTo' => $request->query->get('redirectTo') ?: 'frontend.account.address.page',
        ]);
    }

    #[Route(
        path: '/account/address/default-{type}/{addressId}',
        name: 'frontend.account.address.set-default-address',
        defaults: [PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED => true],
        methods: [Request::METHOD_POST]
    )]
    public function switchDefaultAddress(
        string $type,
        string $addressId,
        ChannelContext $context,
        UserEntity $user
    ): RedirectResponse {
        if (!Uuid::isValid($addressId)) {
            throw UuidException::invalidUuid($addressId);
        }

        try {
            if ($type === self::ADDRESS_TYPE_SHIPPING) {
                $this->accountService->setDefaultShippingAddress($addressId, $context, $user);
                $this->addFlash(self::SUCCESS, $this->trans('account.addressDefaultChanged'));
            } elseif ($type === self::ADDRESS_TYPE_BILLING) {
                $this->accountService->setDefaultBillingAddress($addressId, $context, $user);
                $this->addFlash(self::SUCCESS, $this->trans('account.addressDefaultChanged'));
            } else {
                $this->addFlash(self::DANGER, $this->trans('account.addressDefaultNotChanged'));
            }
        } catch (AddressNotFoundException) {
            $this->addFlash(self::DANGER, $this->trans('account.addressDefaultNotChanged'));
        }

        return $this->redirectToRoute('frontend.account.address.page');
    }

    #[Route(
        path: '/account/address/switch',
        name: 'frontend.account.address.switch-default',
        defaults: [
            'XmlHttpRequest' => true,
            PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED => true,
            PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED_ALLOW_GUEST => true,
        ],
        methods: [Request::METHOD_POST]
    )]
    public function checkoutSwitchDefaultAddress(
        Request $request,
        RequestDataBag $data,
        ChannelContext $context,
        UserEntity $user
    ): Response {
        match ($data->get('type')) {
            self::ADDRESS_TYPE_SHIPPING => $this->accountService->setDefaultShippingAddress($data->get('id'), $context, $user),
            self::ADDRESS_TYPE_BILLING => $this->accountService->setDefaultBillingAddress($data->get('id'), $context, $user),
            default => throw RoutingException::invalidRequestParameter('type'),
        };

        $contextToken = $this->contextSwitchRoute->switchContext($data, $context);

        $this->channelContextService->get(new ChannelContextServiceParameters(
            $context->getChannelId(),
            $contextToken->getToken()
        ));

        $this->addFlash(self::SUCCESS, $this->trans('account.addressDefaultChanged'));

        $request->request->set('redirectTo', $request->request->get('redirectTo', 'frontend.account.addressmanager.get'));

        return $this->createActionResponse($request);
    }

    #[Route(
        path: '/account/address/create',
        name: 'frontend.account.address.create',
        options: ['seo' => false],
        defaults: [PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED => true],
        methods: [Request::METHOD_POST]
    )]
    #[Route(
        path: '/account/address/{addressId}',
        name: 'frontend.account.address.edit.save',
        options: ['seo' => false],
        defaults: [
            PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED => true,
            PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED_ALLOW_GUEST => true,
        ],
        methods: [Request::METHOD_POST]
    )]
    public function saveAddress(
        RequestDataBag $data,
        ChannelContext $context,
        UserEntity $user,
        Request $request
    ): Response {
        /** @var RequestDataBag $address */
        $address = $data->get('address');

        try {
            $this->updateAddressRoute->upsert(
                $address->get('id'),
                $address->toRequestDataBag(),
                $context,
                $user
            );

            $this->addFlash(self::SUCCESS, $this->trans('account.addressSaved'));

            if (!$request->request->get('redirectTo') && !$request->query->get('redirectTo')) {
                // address page is not available for guest users, redirect to cart as only way to get here was a cart error
                $request->request->set('redirectTo', $user->getGuest() ? 'frontend.checkout.cart.page' : 'frontend.account.address.page');
            }

            return $this->createActionResponse($request);
        } catch (ConstraintViolationException $formViolations) {
        }

        if (!$address->get('id')) {
            return $this->forwardToRoute('frontend.account.address.create.page', ['formViolations' => $formViolations]);
        }

        return $this->forwardToRoute(
            'frontend.account.address.edit.page',
            ['formViolations' => $formViolations],
            ['addressId' => $address->get('id')]
        );
    }

    #[Route(
        path: '/account/address/delete/{addressId}',
        name: 'frontend.account.address.delete',
        options: ['seo' => false],
        defaults: [
            'XmlHttpRequest' => true,
            PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED => true,
        ],
        methods: [Request::METHOD_POST]
    )]
    public function deleteAddress(
        string $addressId,
        Request $request,
        ChannelContext $context,
        UserEntity $user
    ): Response {
        if (!$addressId) {
            throw RoutingException::missingRequestParameter('addressId');
        }

        try {
            $this->deleteAddressRoute->delete($addressId, $context, $user);
            $this->addFlash(self::SUCCESS, $this->trans('account.addressDeleted'));
        } catch (InvalidUuidException|AddressNotFoundException|CannotDeleteDefaultAddressException|UserException) {
            $this->addFlash(self::DANGER, $this->trans('account.addressNotDeleted'));
        }

        return $this->redirectToRoute('frontend.account.address.page');
    }

    #[Route(
        path: '/widgets/account/address-manager/switch',
        name: 'frontend.account.addressmanager.switch',
        options: ['seo' => true],
        defaults: [
            'XmlHttpRequest' => true,
            PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED => true,
            PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED_ALLOW_GUEST => true,
        ],
        methods: [Request::METHOD_POST]
    )]
    public function addressManagerSwitch(Request $request, ChannelContext $context): Response
    {
        if (!$request->request->get(ChannelContextService::SHIPPING_ADDRESS_ID)) {
            $request->request->remove(ChannelContextService::SHIPPING_ADDRESS_ID);
        }

        if (!$request->request->get(ChannelContextService::BILLING_ADDRESS_ID)) {
            $request->request->remove(ChannelContextService::BILLING_ADDRESS_ID);
        }

        $this->contextSwitchRoute->switchContext(new RequestDataBag($request->request->all()), $context);

        $this->addFlash(self::SUCCESS, $this->trans('account.addressSuccessfulChange'));

        $request->request->set('redirectTo', $request->request->get('redirectTo', 'frontend.checkout.confirm.page'));

        return $this->createActionResponse($request);
    }

    #[Route(
        path: '/widgets/account/address-manager',
        name: 'frontend.account.addressmanager.get',
        options: ['seo' => true],
        defaults: [
            'XmlHttpRequest' => true,
            PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED => true,
            PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED_ALLOW_GUEST => true,
        ],
        methods: [Request::METHOD_GET]
    )]
    public function addressManager(Request $request, ChannelContext $context, UserEntity $user): Response
    {
        $viewData = new AddressEditorModalStruct();

        $page = $this->addressListingPageLoader->load($request, $context, $user);
        $this->hook(new AddressBookWidgetLoadedHook($page, $context));
        $viewData->setPage($page);

        $response = $this->renderFrontend(
            '@Frontend/frontend/component/address/address-manager-modal.html.twig',
            [
                ...$viewData->getVars(),
                'redirectTo' => $request->query->get('redirectTo', 'frontend.checkout.confirm.page'),
                'redirectParameters' => $request->query->get('redirectParameters'),
            ],
        );

        $response->headers->set('x-robots-tag', 'noindex');

        return $response;
    }

    #[Route(
        path: '/widgets/account/address-manager/{addressId?}',
        name: 'frontend.account.addressmanager',
        options: ['seo' => true],
        defaults: [
            'XmlHttpRequest' => true,
            PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED => true,
            PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED_ALLOW_GUEST => true,
        ],
        methods: [Request::METHOD_POST]
    )]
    public function addressManagerUpsert(
        Request $request,
        RequestDataBag $dataBag,
        ChannelContext $context,
        UserEntity $user,
        ?string $addressId = null,
        #[MapQueryParameter]
        ?string $type = null
    ): Response {
        $viewData = new AddressEditorModalStruct();

        match ($type) {
            self::ADDRESS_TYPE_SHIPPING => $viewData->setChangeShipping(true),
            self::ADDRESS_TYPE_BILLING => $viewData->setChangeBilling(true),
            default => throw RoutingException::invalidRequestParameter('type'),
        };

        $params = [];

        if ($addressId) {
            $params['postedAddress'] = $this->getById($addressId, $context, $user);
        }

        /** @var RequestDataBag|null $addressData */
        $addressData = $dataBag->get('address');

        try {
            // if there is no data in the dataBag, the create form will be rendered
            if ($addressData !== null && $addressData->count() !== 0) {
                $addressData->set('id', $addressId);
                $this->handleAddressCreation($viewData, $addressData, $context, $user);
                $this->addFlash(self::SUCCESS, $this->trans('account.addressSaved'));

                return new NoContentResponse();
            }
        } catch (ConstraintViolationException $formViolations) {
            $params['formViolations'] = $formViolations;
            $params['postedAddress'] = $addressData;
        } catch (\Throwable) {
            $viewData->setSuccess(false);
            $viewData->setMessages([
                'type' => self::DANGER,
                'text' => $this->trans('error.message-default'),
            ]);
        }

        $page = $this->addressListingPageLoader->load($request, $context, $user);
        $this->hook(new AddressBookWidgetLoadedHook($page, $context));
        $viewData->setPage($page);

        $params = array_merge($params, $viewData->getVars());

        $response = $this->renderFrontend(
            '@Frontend/frontend/component/address/address-manager-modal-create-address.html.twig',
            $params
        );

        $response->headers->set('x-robots-tag', 'noindex');

        return $response;
    }

    private function handleAddressCreation(
        AddressEditorModalStruct $viewData,
        RequestDataBag $dataBag,
        ChannelContext $context,
        UserEntity $user
    ): void {
        $response = $this->updateAddressRoute->upsert(
            $dataBag->get('id'),
            $dataBag->toRequestDataBag(),
            $context,
            $user
        );

        $addressId = $response->getAddress()->getId();

        $viewData->setAddressId($addressId);
        $viewData->setSuccess(true);
        $viewData->setMessages(['type' => 'success', 'text' => $this->trans('account.addressSaved')]);

        if (!$viewData->isChangeShipping() && !$viewData->isChangeBilling()) {
            return;
        }

        $requestDataBag = new RequestDataBag();
        $requestDataBag->set(
            $viewData->isChangeShipping()
                ? ChannelContextService::SHIPPING_ADDRESS_ID
                : ChannelContextService::BILLING_ADDRESS_ID,
            $addressId
        );

        $this->contextSwitchRoute->switchContext($requestDataBag, $context);
    }

    private function getById(
        string $addressId,
        ChannelContext $context,
        UserEntity $user
    ): UserAddressEntity {
        if (!Uuid::isValid($addressId)) {
            throw UuidException::invalidUuid($addressId);
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('id', $addressId));
        $criteria->addFilter(new EqualsFilter('userId', $user->getId()));

        $address = $this->listAddressRoute->load($criteria, $context, $user)->getAddressCollection()->get($addressId);

        if (!$address) {
            throw UserException::addressNotFound($addressId);
        }

        return $address;
    }
}
