<?php declare(strict_types=1);

namespace Contena\Core\Framework\Mcp\Tool;

use Mcp\Capability\Attribute\McpTool;
use Contena\Core\Content\Media\Upload\MediaUploadParameters;
use Contena\Core\Content\Media\Upload\MediaUploadService;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\Mcp\Attribute\McpToolGroup;
use Contena\Core\Framework\Mcp\Attribute\McpToolRequires;
use Contena\Core\Framework\Mcp\Context\McpContextProvider;
use Contena\Core\Framework\Uuid\Uuid;

/**
 * @experimental stableVersion:v6.8.0
 */
#[McpTool(
    name: 'contena-media-upload',
    title: 'Media Upload',
    description: 'Upload any image or file — including blog cover images — to Contena\'s media library from a URL. url is the only required parameter; blogId, fileName, and mediaFolderId are all optional. Call this tool immediately with just the URL whenever the admin_user asks to upload, import, or add an image. Returns the new mediaId.'
)]
#[McpToolGroup('media')]
#[McpToolRequires('media:create')]
#[McpToolRequires('blog:update')]
class MediaUploadTool extends McpToolResponse
{
    /**
     * @internal
     */
    public function __construct(
        private readonly MediaUploadService $mediaUploadService,
        private readonly McpContextProvider $contextProvider,
        private readonly DefinitionInstanceRegistry $registry,
    ) {
    }

    public function __invoke(
        string $url,
        string $fileName = '',
        string $mediaFolderId = '',
        string $blogId = '',
    ): string {
        $context = $this->contextProvider->getContext();

        $requiredPrivileges = ['media:create'];
        if ($blogId !== '') {
            $requiredPrivileges[] = 'blog:update';
        }

        if ($error = $this->requirePrivilege($context, ...$requiredPrivileges)) {
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

        $result = ['mediaId' => $mediaId];

        if ($blogId !== '') {
            try {
                $this->assignToBlog($mediaId, $blogId, $context);
                $result['blogId'] = $blogId;
                $result['assignedAsCover'] = true;
            } catch (\Throwable $e) {
                return $this->error('Media uploaded (ID: ' . $mediaId . ') but blog assignment failed: ' . $e->getMessage());
            }
        }

        return $this->success($result);
    }

    private function assignToBlog(string $mediaId, string $blogId, Context $context): void
    {
        $blogMediaId = Uuid::randomHex();

        $this->registry->getRepository('blog')->upsert([
            [
                'id' => $blogId,
                'media' => [
                    [
                        'id' => $blogMediaId,
                        'mediaId' => $mediaId,
                    ],
                ],
                'coverId' => $blogMediaId,
            ],
        ], $context);
    }
}
