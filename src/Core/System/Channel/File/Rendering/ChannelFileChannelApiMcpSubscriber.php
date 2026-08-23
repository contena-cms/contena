<?php declare(strict_types=1);

namespace Contena\Core\System\Channel\File\Rendering;

use Contena\Core\Defaults;
use Contena\Core\System\Channel\Aggregate\ChannelDomain\ChannelDomainEntity;
use Contena\Core\System\Channel\File\Discovery\ChannelFile;
use Contena\Core\System\Channel\File\Rendering\Extension\ChannelFileRenderParametersExtension;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * @internal
 */
final class ChannelFileChannelApiMcpSubscriber implements EventSubscriberInterface
{
    private const CHANNEL_API_MCP_ROUTE = 'channel-api.mcp.endpoint';

    public function __construct(private readonly UrlGeneratorInterface $urlGenerator)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ChannelFileRenderParametersExtension::onPost() => 'addChannelApiMcpContext',
        ];
    }

    public function addChannelApiMcpContext(ChannelFileRenderParametersExtension $extension): void
    {
        if ($extension->file->fileFamily !== ChannelFile::DEFAULT_FILE_FAMILY
            || $extension->file->fileName !== '.well-known/ai-catalog.json'
            || !\is_array($extension->result)
        ) {
            return;
        }

        $baseUrl = $this->resolveBaseUrl($extension);
        if ($baseUrl === null) {
            return;
        }

        $context = [
            'baseUrl' => $baseUrl,
            'publisher' => $this->extractPublisher($baseUrl),
        ];

        if ($extension->channel->getTypeId() === Defaults::CHANNEL_TYPE_API) {
            $path = $this->urlGenerator->generate(self::CHANNEL_API_MCP_ROUTE, [], UrlGeneratorInterface::ABSOLUTE_PATH);
            $context['channelApiMcpServerUrl'] = rtrim($baseUrl, '/') . $path;
        }

        $extension->result['channelFileContext'] = $context;
    }

    private function resolveBaseUrl(ChannelFileRenderParametersExtension $extension): ?string
    {
        $domains = $extension->channel->getDomains();
        if ($domains === null || $domains->count() === 0) {
            return null;
        }

        $domainId = $extension->context->getDomainId();
        if ($domainId !== null) {
            $domain = $domains->get($domainId);

            if ($domain instanceof ChannelDomainEntity) {
                return rtrim($domain->getUrl(), '/');
            }
        }

        $domain = $domains->first();

        return $domain instanceof ChannelDomainEntity ? rtrim($domain->getUrl(), '/') : null;
    }

    private function extractPublisher(string $baseUrl): ?string
    {
        $host = parse_url($baseUrl, \PHP_URL_HOST);

        return \is_string($host) && $host !== '' ? strtolower($host) : null;
    }
}
