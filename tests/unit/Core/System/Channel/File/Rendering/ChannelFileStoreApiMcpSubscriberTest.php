<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Channel\File\Rendering;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Defaults;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\Aggregate\ChannelDomain\ChannelDomainCollection;
use Contena\Core\System\Channel\Aggregate\ChannelDomain\ChannelDomainEntity;
use Contena\Core\System\Channel\File\Discovery\ChannelFile;
use Contena\Core\System\Channel\File\Rendering\Extension\ChannelFileRenderParametersExtension;
use Contena\Core\System\Channel\File\Rendering\ChannelFileStoreApiMcpSubscriber;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\ChannelEntity;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * @internal
 */
#[CoversClass(ChannelFileStoreApiMcpSubscriber::class)]
class ChannelFileStoreApiMcpSubscriberTest extends TestCase
{
    public function testStoreApiMcpUrlIsAddedForHeadlessAiCatalog(): void
    {
        $currentDomain = $this->createDomain('https://headless.example.com/en/');
        $fallbackDomain = $this->createDomain('https://fallback.example.com');
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator
            ->expects($this->once())
            ->method('generate')
            ->with('store-api.mcp.endpoint', [], UrlGeneratorInterface::ABSOLUTE_PATH)
            ->willReturn('/store-api/_mcp');

        $subscriber = new ChannelFileStoreApiMcpSubscriber($urlGenerator);
        $extension = new ChannelFileRenderParametersExtension(
            $this->createChannelFile('.well-known/ai-catalog.json'),
            $this->createChannelContext($currentDomain->getId()),
            $this->createChannel(
                Defaults::CHANNEL_TYPE_API,
                new ChannelDomainCollection([$fallbackDomain, $currentDomain])
            )
        );
        $extension->result = [];

        $subscriber->addStoreApiMcpContext($extension);

        static::assertSame([
            'channelFileContext' => [
                'baseUrl' => 'https://headless.example.com/en',
                'publisher' => 'headless.example.com',
                'storeApiMcpServerUrl' => 'https://headless.example.com/en/store-api/_mcp',
            ],
        ], $extension->result);
    }

    public function testChannelFileContextIsOnlyAddedForAiCatalog(): void
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator
            ->expects($this->never())
            ->method('generate');

        $extension = new ChannelFileRenderParametersExtension(
            $this->createChannelFile('llms.txt'),
            $this->createChannelContext(null),
            $this->createChannel(
                Defaults::CHANNEL_TYPE_FRONTEND,
                new ChannelDomainCollection([$this->createDomain('https://frontend.example.com')])
            )
        );
        $extension->result = [];

        (new ChannelFileStoreApiMcpSubscriber($urlGenerator))->addStoreApiMcpContext($extension);

        static::assertSame([], $extension->result);
    }

    private function createChannelContext(?string $domainId): ChannelContext
    {
        $context = static::createStub(ChannelContext::class);
        $context->method('getDomainId')->willReturn($domainId);

        return $context;
    }

    private function createChannel(string $typeId, ChannelDomainCollection $domains): ChannelEntity
    {
        $channel = new ChannelEntity();
        $channel->setTypeId($typeId);
        $channel->setDomains($domains);

        return $channel;
    }

    private function createDomain(string $url): ChannelDomainEntity
    {
        $domain = new ChannelDomainEntity();
        $domain->setId(Uuid::randomHex());
        $domain->setUrl($url);

        return $domain;
    }

    private function createChannelFile(string $fileName): ChannelFile
    {
        return new ChannelFile(
            'agentic',
            $fileName,
            'files/agentic/' . $fileName . '.twig',
            'application/json; charset=utf-8',
            'files/agentic/' . $fileName . '.twig',
            []
        );
    }
}
