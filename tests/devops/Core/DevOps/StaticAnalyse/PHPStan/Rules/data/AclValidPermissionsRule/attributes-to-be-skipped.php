<?php declare(strict_types=1);

use Contena\Core\PlatformRequest;
use Contena\Frontend\Controller\FrontendController;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: false)]
class InvalidAclRouteInRouteAttributeController extends FrontendController
{
    #[Route(defaults: [PlatformRequest::ATTRIBUTE_HTTP_CACHE => true])]
    public function noAcl(): void
    {
    }

    #[Route(defaults: [PlatformRequest::ATTRIBUTE_ACL => 'string here'])]
    public function aclIsNotArray(): void
    {
    }

    #[Route(defaults: [PlatformRequest::ATTRIBUTE_ACL => [null]])]
    public function aclContainInvalidValues(): void
    {
    }

    public function noAttribute(): void
    {
    }
}
