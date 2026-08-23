<?php declare(strict_types=1);

namespace Contena\Core\System\Channel\File\Api;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Routing\ApiRouteScope;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelException;
use Contena\Core\System\Channel\Context\AbstractChannelContextFactory;
use Contena\Core\System\Channel\File\ChannelFileRequestPathResolver;
use Contena\Core\System\Channel\File\Loader\ChannelFileLoader;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 */
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
class ChannelFileController extends AbstractController
{
    public function __construct(
        private readonly ChannelFileAdministrationReader $administrationReader,
        private readonly ChannelFileLoader $loader,
        private readonly AbstractChannelContextFactory $channelContextFactory,
        private readonly ChannelFileRequestPathResolver $requestPathResolver,
    ) {
    }

    #[Route(path: '/api/_action/channel-file/{fileFamily}/{channelId}', name: 'api.action.channel_file.list', methods: ['GET'])]
    public function list(string $fileFamily, string $channelId, Context $context): JsonResponse
    {
        $this->requestPathResolver->validateFileFamily($fileFamily);

        return new JsonResponse(['data' => $this->administrationReader->list($fileFamily, $channelId, $context)]);
    }

    // The public file name supports subfolders like `.well-known/ucp.json`; keeping it
    // as a query parameter avoids a greedy wildcard path segment for an arbitrary file path.
    #[Route(path: '/api/_action/channel-file/{fileFamily}/{channelId}/detail', name: 'api.action.channel_file.detail', methods: ['GET'])]
    public function detail(string $fileFamily, string $channelId, Request $request, Context $context): JsonResponse
    {
        $fileName = $request->query->get('fileName');
        if (!\is_string($fileName)) {
            throw ChannelException::missingChannelFileName();
        }

        $this->requestPathResolver->buildTemplatePath($fileFamily, $fileName);

        $file = $this->administrationReader->detail($fileFamily, $fileName, $channelId, $context);
        if ($file === null) {
            throw ChannelException::channelFileNotFound($fileFamily, $fileName);
        }

        return new JsonResponse(['data' => $file]);
    }

    #[Route(path: '/api/_action/channel-file/{fileFamily}/{channelId}/preview', name: 'api.action.channel_file.preview', methods: ['POST'])]
    public function preview(string $fileFamily, string $channelId, RequestDataBag $dataBag): JsonResponse
    {
        $fileName = $dataBag->get('fileName');
        if (!\is_string($fileName)) {
            throw ChannelException::missingChannelFileName();
        }

        $templatePath = $this->requestPathResolver->buildTemplatePath($fileFamily, $fileName);

        $templateOverrides = $dataBag->get('templateOverrides') ?? [];
        if ($templateOverrides instanceof RequestDataBag) {
            $templateOverrides = $templateOverrides->all();
        }

        if (!\is_array($templateOverrides)) {
            throw ChannelException::invalidChannelFileTemplateOverrides();
        }

        $channelContext = $this->channelContextFactory->create(Uuid::randomHex(), $channelId);
        $result = $this->loader->preview($templatePath, $channelContext, $templateOverrides);

        if ($result === null) {
            throw ChannelException::channelFileNotFound($fileFamily, $fileName);
        }

        return new JsonResponse([
            'fileName' => $result->fileName,
            'contentType' => $result->contentType,
            'content' => $result->content,
        ]);
    }
}
