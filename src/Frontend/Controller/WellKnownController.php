<?php declare(strict_types=1);

namespace Contena\Storefront\Controller;

use Contena\Core\Framework\Log\Package;
use Contena\Core\PlatformRequest;
use Contena\Storefront\Framework\Routing\StorefrontRouteScope;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 * Do not use direct or indirect repository calls in a controller. Always use a store-api route to get or put data
 */
#[Package('discovery')]
#[Route(path: '.well-known/', defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StorefrontRouteScope::ID]])]
class WellKnownController extends StorefrontController
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
