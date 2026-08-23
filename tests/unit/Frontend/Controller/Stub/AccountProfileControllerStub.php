<?php

declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Controller\Stub;

use Contena\Core\PlatformRequest;
use Contena\Frontend\Controller\AccountProfileController;
use Contena\Frontend\Framework\Routing\FrontendRouteScope;
use Contena\Tests\Unit\Frontend\Controller\FrontendControllerMockTrait;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 */
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [FrontendRouteScope::ID]])]
class AccountProfileControllerStub extends AccountProfileController
{
    use FrontendControllerMockTrait;
}
