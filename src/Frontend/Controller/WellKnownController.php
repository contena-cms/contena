<?php declare(strict_types=1);

namespace Contena\Frontend\Controller;

use Contena\Core\PlatformRequest;
use Contena\Frontend\Framework\Routing\FrontendRouteScope;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 * Do not use direct or indirect repository calls in a controller. Always use a channel-api route to get or put data
 */
#[Route(path: '.well-known/', defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [FrontendRouteScope::ID]])]
class WellKnownController extends FrontendController
{
    #[Route(path: 'change-password', name: 'frontend.well-known.change-password', methods: ['GET'])]
    public function changePassword(): Response
    {
        return $this->redirectToRoute(
            'frontend.account.profile.page',
            ['_fragment' => '#profile-password-form'],
        );
    }
}
