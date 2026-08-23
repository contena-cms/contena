<?php declare(strict_types=1);

namespace Contena\Core\System\Consent\Api;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Routing\ApiRouteScope;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Consent\Service\ConsentService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 */
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
class ConsentController
{
    public function __construct(
        private readonly ConsentService $consentService,
    ) {
    }

    #[Route(path: '/api/consents', name: 'api.consents.fetch', defaults: ['auth_required' => true], methods: ['GET'])]
    public function fetchConsents(Context $context): Response
    {
        return new JsonResponse($this->consentService->list($context));
    }

    #[Route(path: '/api/consents/accept', name: 'api.consents.accept', defaults: ['auth_required' => true], methods: ['POST'])]
    public function acceptConsent(Context $context, Request $request): Response
    {
        $consent = $request->request->getString('consent');
        $revision = $request->request->getString('revision') ?: null;

        return new JsonResponse(
            $this->consentService->acceptConsent(
                $consent,
                $context,
                $revision,
            ),
            Response::HTTP_OK,
        );
    }

    #[Route(path: '/api/consents/revoke', name: 'api.consents.revoke', defaults: ['auth_required' => true], methods: ['POST'])]
    public function revokeConsent(Context $context, Request $request): Response
    {
        $consent = $request->request->getString('consent');

        return new JsonResponse($this->consentService->revokeConsent($consent, $context), Response::HTTP_OK);
    }
}
