<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Channel\File\Loader;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Adapter\Cache\CacheTagCollector;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\Aggregate\ChannelFile\ChannelFileEntity;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\File\ChannelFileCacheInvalidator;
use Contena\Core\System\Channel\File\Discovery\ChannelFile;
use Contena\Core\System\Channel\File\Discovery\ChannelFileDiscovery;
use Contena\Core\System\Channel\File\Loader\ChannelFileConfigurationLoader;
use Contena\Core\System\Channel\File\Loader\ChannelFileLoader;
use Contena\Core\System\Channel\File\Rendering\ChannelFileRenderer;

/**
 * @internal
 */
#[CoversClass(ChannelFileLoader::class)]
class ChannelFileLoaderTest extends TestCase
{
    public function testItTagsRenderedFilesWithChannelFileConfigurationId(): void
    {
        $templatePath = 'files/agentic/llms.txt.twig';
        $channelId = Uuid::randomHex();
        $context = Context::createDefaultContext();
        $configurationId = Uuid::randomHex();
        $file = new ChannelFile(
            'agentic',
            'llms.txt',
            'files/agentic/llms.txt.twig',
            'text/plain; charset=utf-8',
            'files/agentic/llms.txt.twig',
            [
                'Framework' => '@Framework/files/agentic/llms.txt.twig',
            ],
        );
        $configuration = new ChannelFileEntity();
        $configuration->setId($configurationId);
        $configuration->setChannelId($channelId);
        $configuration->setFileFamily('agentic');
        $configuration->setFileName('llms.txt');
        $configuration->setEnabled(true);
        $configuration->setTemplateOverrides(['Framework' => 'merchant override']);

        $discovery = $this->createMock(ChannelFileDiscovery::class);
        $discovery
            ->expects($this->once())
            ->method('get')
            ->with($templatePath)
            ->willReturn($file);

        $configurationLoader = $this->createMock(ChannelFileConfigurationLoader::class);
        $configurationLoader
            ->expects($this->once())
            ->method('load')
            ->with('agentic', 'llms.txt', $channelId, $context)
            ->willReturn($configuration);

        $renderer = $this->createMock(ChannelFileRenderer::class);
        $renderer
            ->expects($this->once())
            ->method('render')
            ->with($file, static::isInstanceOf(ChannelContext::class), ['Framework' => 'merchant override'])
            ->willReturn('rendered content');

        $cacheTagCollector = $this->createMock(CacheTagCollector::class);
        $cacheTagCollector
            ->expects($this->once())
            ->method('addTag')
            ->with(ChannelFileCacheInvalidator::buildCacheTag($configurationId));

        $channelContext = static::createStub(ChannelContext::class);
        $channelContext->method('getChannelId')->willReturn($channelId);
        $channelContext->method('getContext')->willReturn($context);

        $result = new ChannelFileLoader($discovery, $configurationLoader, $renderer, $cacheTagCollector)->load($templatePath, $channelContext);

        static::assertNotNull($result);
        static::assertSame('llms.txt', $result->fileName);
        static::assertSame('rendered content', $result->content);
        static::assertSame('text/plain; charset=utf-8', $result->contentType);
    }

    public function testItDoesNotLoadChannelFileConfigurationForUnknownDiscoveredFile(): void
    {
        $templatePath = 'files/agentic/unknown.txt.twig';

        $discovery = $this->createMock(ChannelFileDiscovery::class);
        $discovery
            ->expects($this->once())
            ->method('get')
            ->with($templatePath)
            ->willReturn(null);

        $configurationLoader = $this->createMock(ChannelFileConfigurationLoader::class);
        $configurationLoader
            ->expects($this->never())
            ->method('load');

        $renderer = $this->createMock(ChannelFileRenderer::class);
        $renderer
            ->expects($this->never())
            ->method('render');

        $cacheTagCollector = $this->createMock(CacheTagCollector::class);
        $cacheTagCollector
            ->expects($this->never())
            ->method('addTag');

        $result = new ChannelFileLoader(
            $discovery,
            $configurationLoader,
            $renderer,
            $cacheTagCollector
        )->load($templatePath, static::createStub(ChannelContext::class));

        static::assertNull($result);
    }
}
