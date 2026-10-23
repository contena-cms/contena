<?php declare(strict_types=1);

use Contena\Core\PlatformRequest;
use Contena\Frontend\Controller\FrontendController;
use Symfony\Component\Routing\Attribute\Route;

// The ACL key is matched in both its string-literal ('_acl') and constant (PlatformRequest::ATTRIBUTE_ACL) forms.
// The class-level route uses the literal form and the method-level route the constant form to cover both.
#[Route(defaults: ['_acl' => ['class-non-existing-permission']])]
class InvalidAclRouteInRouteAttributeController extends FrontendController
{
    #[Route(defaults: ['_httpCache' => true, PlatformRequest::ATTRIBUTE_ACL => ['system:create', 'order:read', 'system:core:update', 'non-existing-permission']])]
    public function index(): void
    {
    }
}
