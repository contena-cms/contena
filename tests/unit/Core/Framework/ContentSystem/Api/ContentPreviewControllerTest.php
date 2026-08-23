<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\ContentSystem\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\ContentSystem\Api\ContentPreviewController;
use Contena\Core\Framework\ContentSystem\Api\ContentPreviewPageBuilder;
use Contena\Core\Framework\ContentSystem\Api\ContentPreviewPayloadStore;
use Contena\Core\Framework\ContentSystem\Api\ContentPreviewRequest;
use Contena\Core\Framework\ContentSystem\Channel\ContentRouteResponse;
use Contena\Core\Framework\ContentSystem\Output\Format\AbstractResponseFactory;
use Contena\Core\Framework\ContentSystem\Output\Struct\ContentPage;
use Contena\Core\Framework\Context;
use Contena\Core\Test\Generator;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(ContentPreviewController::class)]
class ContentPreviewControllerTest extends TestCase
{
    #[TestDox('preview delegates to the page builder and wraps its content page in the factory response')]
    public function testPreviewWrapsPageBuilderResultInFactoryResponse(): void
    {
        $payload = $this->request();
        $context = Context::createDefaultContext();
        $contentPage = new ContentPage('preview-layout', [], 'preview', null);
        $response = new ContentRouteResponse($contentPage);

        $pageBuilder = static::createMock(ContentPreviewPageBuilder::class);
        $pageBuilder->expects($this->once())
            ->method('build')
            ->with(static::identicalTo($payload), static::identicalTo($context))
            ->willReturn(['contentPage' => $contentPage, 'channelContext' => Generator::generateChannelContext()]);

        $responseFactory = static::createMock(AbstractResponseFactory::class);
        $responseFactory->expects($this->once())
            ->method('createResponse')
            ->with(static::identicalTo($contentPage))
            ->willReturn($response);

        $controller = new ContentPreviewController(
            $pageBuilder,
            $responseFactory,
            static::createStub(ContentPreviewPayloadStore::class),
        );

        static::assertSame($response, $controller->preview($payload, $context));
    }

    #[TestDox('previewUrl stores the serialized payload and returns a URL embedding the minted token')]
    public function testPreviewUrlReturnsUrlForStoredToken(): void
    {
        $payload = $this->request();

        $payloadStore = static::createMock(ContentPreviewPayloadStore::class);
        $payloadStore->expects($this->once())
            ->method('store')
            ->with([
                'layout' => [['id' => 'e1', 'component' => 'CT:Content:Heading']],
                'entityType' => 'blog',
                'entityId' => 'blog-1',
                'channelId' => 'sc-1',
                'languageId' => null,
                'domainId' => null,
                'memberId' => null,
                'queryParameters' => [],
            ])
            ->willReturn('preview-token-123');

        $controller = new ContentPreviewController(
            static::createStub(ContentPreviewPageBuilder::class),
            static::createStub(AbstractResponseFactory::class),
            $payloadStore,
        );

        $request = Request::create('https://admin.example.com/api/_action/content-system/preview/entity/url');

        $response = $controller->previewUrl($payload, $request);

        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(
            ['url' => 'https://admin.example.com/content-system/preview/preview-token-123'],
            $body,
        );
    }

    private function request(): ContentPreviewRequest
    {
        return new ContentPreviewRequest(
            layout: [['id' => 'e1', 'component' => 'CT:Content:Heading']],
            entityType: 'blog',
            entityId: 'blog-1',
            channelId: 'sc-1',
        );
    }
}
