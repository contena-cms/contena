<?php declare(strict_types=1);

namespace Contena\Core\Content\Media\Api;

use Contena\Core\Content\Media\MediaFolderService;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Routing\ApiRouteScope;
use Contena\Core\PlatformRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
class MediaFolderController extends AbstractController
{
    /**
     * @internal
     */
    public function __construct(private readonly MediaFolderService $dissolveFolderService)
    {
    }

    #[Route(path: '/api/_action/media-folder/{folderId}/dissolve', name: 'api.action.media-folder.dissolve', methods: ['POST'])]
    public function dissolve(string $folderId, Context $context): Response
    {
        $this->dissolveFolderService->dissolve($folderId, $context);

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
