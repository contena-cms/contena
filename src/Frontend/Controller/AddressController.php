<?php declare(strict_types=1);

namespace Contena\Frontend\Controller;

use Contena\Core\Framework\Routing\RoutingException;
use Contena\Core\Framework\Uuid\Exception\InvalidUuidException;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\Framework\Validation\Exception\ConstraintViolationException;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Member\Channel\AbstractDeleteAddressRoute;
use Contena\Core\System\Member\Channel\AbstractUpsertAddressRoute;
use Contena\Core\System\Member\Exception\AddressNotFoundException;
use Contena\Core\System\Member\MemberEntity;
use Contena\Core\System\Member\MemberException;
use Contena\Frontend\Framework\Routing\FrontendRouteScope;
use Contena\Frontend\Page\Address\Detail\AddressDetailPageLoader;
use Contena\Frontend\Page\Address\Listing\AddressListingPageLoader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 * Do not use direct or indirect repository calls in a controller. Always use a channel-api route to get or put data.
 */
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [FrontendRouteScope::ID]])]
class AddressController extends FrontendController
{
    /**
     * @internal
     */
    public function __construct(
        private readonly AddressListingPageLoader $addressListingPageLoader,
        private readonly AddressDetailPageLoader $addressDetailPageLoader,
        private readonly AbstractUpsertAddressRoute $updateAddressRoute,
        private readonly AbstractDeleteAddressRoute $deleteAddressRoute,
    ) {
    }

    #[Route(
        path: '/account/address',
        name: 'frontend.account.address.page',
        options: ['seo' => false],
        defaults: [PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED => true, PlatformRequest::ATTRIBUTE_NO_STORE => true],
        methods: [Request::METHOD_GET],
    )]
    public function accountAddressOverview(Request $request, ChannelContext $context, MemberEntity $member): Response
    {
        $page = $this->addressListingPageLoader->load($request, $context, $member);

        return $this->renderFrontend('@Frontend/frontend/page/account/addressbook/index.html.twig', ['page' => $page]);
    }

    #[Route(
        path: '/account/address/create',
        name: 'frontend.account.address.create.page',
        options: ['seo' => false],
        defaults: [PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED => true, PlatformRequest::ATTRIBUTE_NO_STORE => true],
        methods: [Request::METHOD_GET],
    )]
    public function accountCreateAddress(Request $request, RequestDataBag $data, ChannelContext $context, MemberEntity $member): Response
    {
        $page = $this->addressDetailPageLoader->load($request, $context, $member);

        return $this->renderFrontend('@Frontend/frontend/page/account/addressbook/create.html.twig', [
            'page' => $page,
            'data' => $data,
        ]);
    }

    #[Route(
        path: '/account/address/{addressId}',
        name: 'frontend.account.address.edit.page',
        options: ['seo' => false],
        defaults: [PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED => true, PlatformRequest::ATTRIBUTE_NO_STORE => true],
        methods: [Request::METHOD_GET],
    )]
    public function accountEditAddress(Request $request, ChannelContext $context, MemberEntity $member): Response
    {
        $page = $this->addressDetailPageLoader->load($request, $context, $member);

        return $this->renderFrontend('@Frontend/frontend/page/account/addressbook/edit.html.twig', [
            'page' => $page,
            'redirectTo' => $request->query->get('redirectTo') ?: 'frontend.account.address.page',
        ]);
    }

    #[Route(path: '/account/address/create', name: 'frontend.account.address.create', options: ['seo' => false], defaults: [PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED => true], methods: [Request::METHOD_POST])]
    #[Route(path: '/account/address/{addressId}', name: 'frontend.account.address.edit.save', options: ['seo' => false], defaults: [PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED => true], methods: [Request::METHOD_POST])]
    public function saveAddress(RequestDataBag $data, ChannelContext $context, MemberEntity $member, Request $request): Response
    {
        $address = $data->get('address');
        if (!$address instanceof RequestDataBag) {
            throw RoutingException::missingRequestParameter('address');
        }

        try {
            $this->updateAddressRoute->upsert(
                $address->get('id'),
                $address->toRequestDataBag(),
                $context,
                $member,
            );

            $this->addFlash(self::SUCCESS, $this->trans('account.addressSaved'));

            if (!$request->request->get('redirectTo') && !$request->query->get('redirectTo')) {
                $request->request->set('redirectTo', 'frontend.account.address.page');
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
            ['addressId' => $address->get('id')],
        );
    }

    #[Route(
        path: '/account/address/delete/{addressId}',
        name: 'frontend.account.address.delete',
        options: ['seo' => false],
        defaults: ['XmlHttpRequest' => true, PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED => true],
        methods: [Request::METHOD_POST],
    )]
    public function deleteAddress(string $addressId, ChannelContext $context, MemberEntity $member): Response
    {
        if (!$addressId) {
            throw RoutingException::missingRequestParameter('addressId');
        }

        try {
            $this->deleteAddressRoute->delete($addressId, $context, $member);
            $this->addFlash(self::SUCCESS, $this->trans('account.addressDeleted'));
        } catch (InvalidUuidException|AddressNotFoundException|MemberException) {
            $this->addFlash(self::DANGER, $this->trans('account.addressNotDeleted'));
        }

        return $this->redirectToRoute('frontend.account.address.page');
    }
}
