<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Mcp\Tool;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Media\Upload\MediaUploadService;
use Contena\Core\Framework\Api\Context\AdminApiSource;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Mcp\Context\McpContextProvider;
use Contena\Core\Framework\Mcp\Tool\MediaUploadTool;

/**
 * @internal
 */
#[CoversClass(MediaUploadTool::class)]
class MediaUploadToolTest extends TestCase
{
    public function testUploadFromUrlReturnsMediaId(): void
    {
        $mediaId = 'generated-media-id';

        $uploadService = $this->createMock(MediaUploadService::class);
        $uploadService->expects($this->once())
            ->method('uploadFromURL')
            ->willReturn($mediaId);

        $contextProvider = static::createStub(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn($this->createAdminContext(['media:create']));

        $registry = static::createStub(DefinitionInstanceRegistry::class);

        $tool = new MediaUploadTool($uploadService, $contextProvider, $registry);
        $output = $tool('https://example.com/image.jpg');

        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);
        static::assertTrue($data['success']);
        static::assertSame($mediaId, $data['data']['mediaId']);
    }

    public function testUploadWithBlogAssignment(): void
    {
        $mediaId = 'generated-media-id';
        $blogId = 'blog-id';

        $uploadService = static::createStub(MediaUploadService::class);
        $uploadService->method('uploadFromURL')->willReturn($mediaId);

        $contextProvider = static::createStub(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn($this->createAdminContext(['media:create', 'blog:update']));

        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->once())->method('upsert');

        $registry = static::createStub(DefinitionInstanceRegistry::class);
        $registry->method('getRepository')->willReturn($repository);

        $tool = new MediaUploadTool($uploadService, $contextProvider, $registry);
        $output = $tool('https://example.com/image.jpg', blogId: $blogId);

        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);
        static::assertTrue($data['success']);
        static::assertSame($mediaId, $data['data']['mediaId']);
        static::assertSame($blogId, $data['data']['blogId']);
        static::assertTrue($data['data']['assignedAsCover']);
    }

    public function testUploadExceptionReturnsError(): void
    {
        $uploadService = static::createStub(MediaUploadService::class);
        $uploadService->method('uploadFromURL')->willThrowException(new \RuntimeException('Download failed'));

        $contextProvider = static::createStub(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn($this->createAdminContext(['media:create']));

        $tool = new MediaUploadTool($uploadService, $contextProvider, static::createStub(DefinitionInstanceRegistry::class));
        $output = $tool('https://example.com/broken.jpg');

        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);
        static::assertFalse($data['success']);
        static::assertStringContainsString('Upload failed', $data['error']);
        static::assertStringContainsString('Download failed', $data['error']);
    }

    public function testBlogAssignmentExceptionReturnsError(): void
    {
        $mediaId = 'generated-media-id';

        $uploadService = static::createStub(MediaUploadService::class);
        $uploadService->method('uploadFromURL')->willReturn($mediaId);

        $contextProvider = static::createStub(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn($this->createAdminContext(['media:create', 'blog:update']));

        $repository = static::createStub(EntityRepository::class);
        $repository->method('upsert')->willThrowException(new \RuntimeException('Blog not found'));

        $registry = static::createStub(DefinitionInstanceRegistry::class);
        $registry->method('getRepository')->willReturn($repository);

        $tool = new MediaUploadTool($uploadService, $contextProvider, $registry);
        $output = $tool('https://example.com/image.jpg', blogId: 'bad-blog');

        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);
        static::assertFalse($data['success']);
        static::assertStringContainsString('blog assignment failed', $data['error']);
        static::assertStringContainsString($mediaId, $data['error']);
    }

    public function testMissingAclReturnsError(): void
    {
        $uploadService = static::createStub(MediaUploadService::class);

        $contextProvider = static::createStub(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn($this->createAdminContext([]));

        $registry = static::createStub(DefinitionInstanceRegistry::class);

        $tool = new MediaUploadTool($uploadService, $contextProvider, $registry);
        $output = $tool('https://example.com/image.jpg');

        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);
        static::assertFalse($data['success']);
    }

    /**
     * @param list<string> $privileges
     */
    private function createAdminContext(array $privileges): Context
    {
        $source = new AdminApiSource(null, null);
        $source->setPermissions($privileges);

        return new Context($source);
    }
}
