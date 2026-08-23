<?php declare(strict_types=1);

namespace Contena\Core\Framework\Mcp\Tool;

use Mcp\Capability\Attribute\McpTool;
use Contena\Core\Content\Media\Upload\MediaUploadParameters;
use Contena\Core\Content\Media\Upload\MediaUploadService;
use Contena\Core\Framework\Mcp\Attribute\McpToolGroup;
use Contena\Core\Framework\Mcp\Attribute\McpToolRequires;
use Contena\Core\Framework\Mcp\Context\McpContextProvider;

#[McpTool(
    name: 'contena-media-upload',
    title: 'Media Upload',
    description: 'Upload an image or file to Contena\'s media library from a URL. url is required; fileName and mediaFolderId are optional. Returns the new mediaId.'
)]
#[McpToolGroup('media')]
#[McpToolRequires('media:create')]
class MediaUploadTool extends McpToolResponse
{
    /**
     * @internal
     */
    public function __construct(
        private readonly MediaUploadService $mediaUploadService,
        private readonly McpContextProvider $contextProvider,
    ) {
    }

    public function __invoke(
        string $url,
        string $fileName = '',
        string $mediaFolderId = '',
    ): string {
        $context = $this->contextProvider->getContext();

        if ($error = $this->requirePrivilege($context, 'media:create')) {
            return $error;
        }

        $params = new MediaUploadParameters(
            mediaFolderId: $mediaFolderId !== '' ? $mediaFolderId : null,
            fileName: $fileName !== '' ? $fileName : null,
        );

        try {
            $mediaId = $this->mediaUploadService->uploadFromURL($url, $context, $params);
        } catch (\Throwable $e) {
            return $this->error('Upload failed: ' . $e->getMessage());
        }

        return $this->success(['mediaId' => $mediaId]);
    }
}
