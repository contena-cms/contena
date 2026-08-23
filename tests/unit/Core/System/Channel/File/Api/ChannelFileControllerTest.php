<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Channel\File\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\ChannelException;
use Contena\Core\System\Channel\Context\AbstractChannelContextFactory;
use Contena\Core\System\Channel\File\Api\ChannelFileAdministrationDetail;
use Contena\Core\System\Channel\File\Api\ChannelFileAdministrationListItem;
use Contena\Core\System\Channel\File\Api\ChannelFileAdministrationReader;
use Contena\Core\System\Channel\File\Api\ChannelFileController;
use Contena\Core\System\Channel\File\ChannelFileRequestPathResolver;
use Contena\Core\System\Channel\File\Loader\ChannelFileLoader;
use Contena\Core\System\Channel\File\Rendering\ChannelFileRenderResult;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(ChannelFileController::class)]
class ChannelFileControllerTest extends TestCase
{
    public function testListDelegatesToAdministrationReader(): void
    {
        $context = Context::createDefaultContext();
        $channelId = Uuid::randomHex();
        $files = [new ChannelFileAdministrationListItem('agentic', 'llms.txt', 'text/plain; charset=utf-8', null)];

        $administrationReader = $this->createMock(ChannelFileAdministrationReader::class);
        $administrationReader
            ->expects($this->once())
            ->method('list')
            ->with('agentic', $channelId, $context)
            ->willReturn($files);

        $response = $this->createController($administrationReader)->list('agentic', $channelId, $context);

        static::assertSame(200, $response->getStatusCode());
        static::assertSame([
            'data' => [
                [
                    'fileFamily' => 'agentic',
                    'fileName' => 'llms.txt',
                    'contentType' => 'text/plain; charset=utf-8',
                    'configuration' => null,
                ],
            ],
        ], $this->decodeResponse($response->getContent()));
    }

    public function testDetailDelegatesToAdministrationReader(): void
    {
        $context = Context::createDefaultContext();
        $channelId = Uuid::randomHex();
        $file = new ChannelFileAdministrationDetail(
            'agentic',
            '.well-known/ucp.json',
            'files/agentic/.well-known/ucp.json.twig',
            'application/json; charset=utf-8',
            [],
            false,
            null,
        );

        $administrationReader = $this->createMock(ChannelFileAdministrationReader::class);
        $administrationReader
            ->expects($this->once())
            ->method('detail')
            ->with('agentic', '.well-known/ucp.json', $channelId, $context)
            ->willReturn($file);

        $response = $this->createController($administrationReader)->detail(
            'agentic',
            $channelId,
            new Request(['fileName' => '.well-known/ucp.json']),
            $context
        );

        static::assertSame(200, $response->getStatusCode());
        static::assertSame([
            'data' => [
                'fileFamily' => 'agentic',
                'fileName' => '.well-known/ucp.json',
                'templatePath' => 'files/agentic/.well-known/ucp.json.twig',
                'contentType' => 'application/json; charset=utf-8',
                'templates' => [],
                'supportsUserProvidedContent' => false,
                'configuration' => null,
            ],
        ], $this->decodeResponse($response->getContent()));
    }

    public function testDetailThrowsNotFoundForUnknownFile(): void
    {
        $context = Context::createDefaultContext();
        $channelId = Uuid::randomHex();
        $expected = ChannelException::channelFileNotFound('agentic', 'missing.txt');

        $administrationReader = $this->createMock(ChannelFileAdministrationReader::class);
        $administrationReader
            ->expects($this->once())
            ->method('detail')
            ->with('agentic', 'missing.txt', $channelId, $context)
            ->willReturn(null);

        $this->expectExceptionObject($expected);

        $this->createController($administrationReader)->detail(
            'agentic',
            $channelId,
            new Request(['fileName' => 'missing.txt']),
            $context
        );
    }

    public function testPreviewRendersUnsavedTemplateOverridesForChannel(): void
    {
        $channelId = Uuid::randomHex();
        $channelContext = static::createStub(ChannelContext::class);

        $contextFactory = $this->createMock(AbstractChannelContextFactory::class);
        $contextFactory
            ->expects($this->once())
            ->method('create')
            ->with(
                static::callback(static fn (string $token): bool => Uuid::isValid($token)),
                $channelId,
            )
            ->willReturn($channelContext);

        $loader = $this->createMock(ChannelFileLoader::class);
        $loader
            ->expects($this->once())
            ->method('preview')
            ->with(
                'files/agentic/llms.txt.twig',
                $channelContext,
                ['Framework' => 'Unsaved override']
            )
            ->willReturn(new ChannelFileRenderResult('llms.txt', 'Rendered preview', 'text/plain; charset=utf-8'));

        $controller = $this->createController(
            channelFileLoader: $loader,
            channelContextFactory: $contextFactory,
        );

        $response = $controller->preview('agentic', $channelId, new RequestDataBag([
            'fileName' => 'llms.txt',
            'templateOverrides' => new RequestDataBag([
                'Framework' => 'Unsaved override',
            ]),
        ]));

        static::assertSame(200, $response->getStatusCode());
        static::assertSame([
            'fileName' => 'llms.txt',
            'contentType' => 'text/plain; charset=utf-8',
            'content' => 'Rendered preview',
        ], $this->decodeResponse($response->getContent()));
    }

    private function createController(
        ?ChannelFileAdministrationReader $administrationReader = null,
        ?ChannelFileLoader $channelFileLoader = null,
        ?AbstractChannelContextFactory $channelContextFactory = null,
    ): ChannelFileController {
        return new ChannelFileController(
            $administrationReader ?? static::createStub(ChannelFileAdministrationReader::class),
            $channelFileLoader ?? static::createStub(ChannelFileLoader::class),
            $channelContextFactory ?? static::createStub(AbstractChannelContextFactory::class),
            new ChannelFileRequestPathResolver(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeResponse(string|false $content): array
    {
        static::assertIsString($content);

        $data = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($data);

        return $data;
    }
}
