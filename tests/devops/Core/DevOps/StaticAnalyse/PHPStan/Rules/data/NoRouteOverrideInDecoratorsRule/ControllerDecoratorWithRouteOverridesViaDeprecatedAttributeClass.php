<?php declare(strict_types=1);

namespace Contena\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\NoRouteOverrideInDecoratorsRule;

use Contena\Core\Content\Category\CategoryDefinition;
use Contena\Core\Framework\Routing\ChannelApiRouteScope;
use Contena\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

// note that we use Symfony\Component\Routing\Annotation\Route here and not the new one in attribute namespace
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ChannelApiRouteScope::ID]])]
class ControllerDecoratorWithRouteOverridesViaDeprecatedAttributeClass
{
    #[Route(
        path: '/channel-api/navigation/{activeId}/{rootId}',
        name: 'channel-api.navigation',
        methods: [Request::METHOD_GET, Request::METHOD_POST],
        defaults: [PlatformRequest::ATTRIBUTE_ENTITY => CategoryDefinition::ENTITY_NAME, PlatformRequest::ATTRIBUTE_HTTP_CACHE => true],
    )]
    public function dummy(): void
    {
    }
}
