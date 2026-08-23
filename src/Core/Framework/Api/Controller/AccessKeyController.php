<?php declare(strict_types=1);

namespace Contena\Core\Framework\Api\Controller;

use Contena\Core\Framework\Api\Util\AccessKeyHelper;
use Contena\Core\Framework\Routing\ApiRouteScope;
use Contena\Core\PlatformRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
class AccessKeyController extends AbstractController
{
    #[Route(
        path: '/api/_action/access-key/intergration',
        name: 'api.action.access-key.integration',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['api_action_access-key_integration']],
        methods: [Request::METHOD_GET]
    )]
    public function generateIntegrationKey(): JsonResponse
    {
        return new JsonResponse([
            'accessKey' => AccessKeyHelper::generateAccessKey('integration'),
            'secretAccessKey' => AccessKeyHelper::generateSecretAccessKey(),
        ]);
    }

    #[Route(
        path: '/api/_action/access-key/user',
        name: 'api.action.access-key.user',
        methods: [Request::METHOD_GET]
    )]
    public function generateUserKey(): JsonResponse
    {
        return new JsonResponse([
            'accessKey' => AccessKeyHelper::generateAccessKey('user'),
            'secretAccessKey' => AccessKeyHelper::generateSecretAccessKey(),
        ]);
    }

    #[Route(
        path: '/api/_action/access-key/channel',
        name: 'api.action.access-key.channel',
        methods: [Request::METHOD_GET]
    )]
    public function generateChannelKey(): JsonResponse
    {
        return new JsonResponse([
            'accessKey' => AccessKeyHelper::generateAccessKey('channel'),
        ]);
    }
}
