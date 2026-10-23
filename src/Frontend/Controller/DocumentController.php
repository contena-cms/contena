<?php declare(strict_types=1);

namespace Contena\Frontend\Controller;

use Contena\Core\System\User\Channel\AbstractLogoutRoute;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Frontend\Framework\Routing\FrontendRouteScope;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 * Do not use direct or indirect repository calls in a controller. Always use a store-api route to get or put data
 */
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [FrontendRouteScope::ID]])]
class DocumentController extends FrontendController
{
    /**
     * @internal
     */
    public function __construct(
        private readonly AbstractDocumentRoute $documentRoute,
        private readonly AbstractLogoutRoute $logoutRoute
    ) {
    }

    #[Route(
        path: '/account/order/document/{documentId}/{deepLinkCode}',
        name: 'frontend.account.order.single.document',
        defaults: [
            PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED => true,
            PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED_ALLOW_GUEST => true,
        ],
        methods: [Request::METHOD_GET]
    )]
    #[Route(
        path: '/account/order/document/{documentId}/{deepLinkCode}/{fileType}',
        name: 'frontend.account.order.single.document.a11y',
        defaults: [PlatformRequest::ATTRIBUTE_NO_STORE => true],
        methods: [Request::METHOD_GET, Request::METHOD_POST]
    )]
    public function downloadDocument(Request $request, ChannelContext $context, string $documentId): Response
    {
        // {fileType} is a file extension for the legacy v1 flow and a DocumentFormat name for document v2
        $format = $request->attributes->get('fileType') ?? ($request->query->has('fileType') ? $request->query->getString('fileType') : null);
        $fileType = $format ?? PdfRenderer::FILE_EXTENSION;

        try {
            // @phpstan-ignore arguments.count (format is hidden on AbstractDocumentRoute::download() via NewOptionalParameter to avoid a BC break for decorators; DocumentRoute's final implementation reads this 6th argument for real)
            return $this->documentRoute->download($documentId, $request, $context, $request->attributes->get('deepLinkCode'), $fileType, $format);
        } catch (GuestNotAuthenticatedException|WrongGuestCredentialsException|UserAuthThrottledException $exception) {
            if ($context->getUser() !== null) {
                $this->logoutRoute->logout($context, new RequestDataBag([]));
            }

            return $this->redirectToRoute(
                'frontend.account.guest.login.page',
                [
                    'redirectTo' => 'frontend.account.order.single.document.a11y',
                    'redirectParameters' => [
                        'deepLinkCode' => $request->attributes->get('deepLinkCode'),
                        'documentId' => $documentId,
                        'fileType' => $fileType,
                    ],
                    'waitTime' => ($exception instanceof UserAuthThrottledException) ? $exception->getWaitTime() : '',
                ]
            );
        } catch (UserNotLoggedInException $exception) {
            if ($context->getUser() !== null) {
                $this->logoutRoute->logout($context, new RequestDataBag([]));
            }

            throw $exception;
        }
    }
}
