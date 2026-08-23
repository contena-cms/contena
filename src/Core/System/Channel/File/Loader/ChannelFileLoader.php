<?php declare(strict_types=1);

namespace Contena\Core\System\Channel\File\Loader;

use Contena\Core\Framework\Adapter\Cache\CacheTagCollector;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\File\ChannelFileCacheInvalidator;
use Contena\Core\System\Channel\File\Discovery\ChannelFileDiscovery;
use Contena\Core\System\Channel\File\Rendering\ChannelFileRenderer;
use Contena\Core\System\Channel\File\Rendering\ChannelFileRenderResult;

/**
 * @internal
 */
class ChannelFileLoader
{
    public function __construct(
        private readonly ChannelFileDiscovery $discovery,
        private readonly ChannelFileConfigurationLoader $configurationLoader,
        private readonly ChannelFileRenderer $renderer,
        private readonly CacheTagCollector $cacheTagCollector,
    ) {
    }

    public function load(string $templatePath, ChannelContext $context): ?ChannelFileRenderResult
    {
        $file = $this->discovery->get($templatePath);
        if ($file === null) {
            return null;
        }

        $configuration = $this->configurationLoader->load(
            $file->fileFamily,
            $file->fileName,
            $context->getChannelId(),
            $context->getContext()
        );
        if ($configuration === null || !$configuration->isEnabled()) {
            return null;
        }

        $this->cacheTagCollector->addTag(
            ChannelFileCacheInvalidator::buildCacheTag($configuration->getId()),
        );

        return new ChannelFileRenderResult(
            $file->fileName,
            $this->renderer->render($file, $context, $configuration->getTemplateOverrides()),
            $file->contentType,
        );
    }

    /**
     * @param array<string, mixed> $templateOverrides
     */
    public function preview(string $templatePath, ChannelContext $context, array $templateOverrides): ?ChannelFileRenderResult
    {
        $file = $this->discovery->get($templatePath);
        if ($file === null) {
            return null;
        }

        return new ChannelFileRenderResult(
            $file->fileName,
            $this->renderer->render($file, $context, $templateOverrides),
            $file->contentType,
        );
    }
}
