<?php declare(strict_types=1);

namespace Contena\Core\Content\Sitemap\Channel;

use League\Flysystem\FilesystemOperator;
use Contena\Core\Content\Media\Exception\IllegalFileNameException;
use Contena\Core\Content\Media\Util\PathHelper;
use Contena\Core\Content\Sitemap\Extension\SitemapFileExtension;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\Routing\ChannelApiRouteScope;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ChannelApiRouteScope::ID]])]
class SitemapFileRoute
{
    /**
     * @internal
     */
    public function __construct(
        private readonly FilesystemOperator $fileSystem,
        private readonly ExtensionDispatcher $extensions
    ) {
    }

    #[Route(path: '/channel-api/sitemap/{filePath}', name: 'channel-api.sitemap.file', requirements: ['filePath' => '.+\.xml\.gz'], methods: ['GET', 'POST'])]
    public function getSitemapFile(Request $request, ChannelContext $context, string $filePath): Response
    {
        return $this->extensions->publish(
            name: SitemapFileExtension::NAME,
            extension: new SitemapFileExtension($request, $context, $filePath),
            function: $this->_getSitemapFile(...)
        );
    }

    private function _getSitemapFile(Request $request, ChannelContext $context, string $filePath): Response
    {
        $filePath = 'sitemap/' . $filePath;

        if (!$this->isRequestedFileValid($context, $filePath)) {
            return new JsonResponse(null, Response::HTTP_NO_CONTENT);
        }

        $file = $this->fileSystem->readStream($filePath);

        if (!\is_resource($file)) {
            return new JsonResponse(null, Response::HTTP_NO_CONTENT);
        }

        $fileName = basename($filePath);
        try {
            $filenameFallback = PathHelper::stripNonAsciiAndControlChars($fileName);
        } catch (IllegalFileNameException) {
            $filenameFallback = '';
        }

        $headers = [
            'Content-Disposition' => HeaderUtils::makeDisposition(
                HeaderUtils::DISPOSITION_ATTACHMENT,
                $fileName,
                // only printable ascii
                $filenameFallback
            ),
            'Content-Length' => $this->fileSystem->fileSize($filePath),
            'Content-Type' => 'application/octet-stream',
        ];

        return new StreamedResponse(static function () use ($file): void {
            fpassthru($file);
        }, Response::HTTP_OK, $headers);
    }

    /**
     * Checks if the requested file is a valid sitemap file.
     */
    private function isRequestedFileValid(ChannelContext $channelContext, string $filePath): bool
    {
        $files = $this->fileSystem->listContents('sitemap/channel-' . $channelContext->getChannelId() . '-' . $channelContext->getLanguageId());

        foreach ($files as $file) {
            if ($filePath === $file->path()) {
                return true;
            }
        }

        return false;
    }
}
