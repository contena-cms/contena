<?php declare(strict_types=1);

namespace Contena\Core\System\SalesChannel\File\Loader;

use Contena\Core\Framework\Adapter\Cache\CacheTagCollector;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\File\Discovery\SalesChannelFileDiscovery;
use Contena\Core\System\SalesChannel\File\Rendering\SalesChannelFileRenderer;
use Contena\Core\System\SalesChannel\File\Rendering\SalesChannelFileRenderResult;
use Contena\Core\System\SalesChannel\File\SalesChannelFileCacheInvalidator;
use Contena\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[Package('framework')]
class SalesChannelFileLoader
{
    public function __construct(
        private readonly SalesChannelFileDiscovery $discovery,
        private readonly SalesChannelFileConfigurationLoader $configurationLoader,
        private readonly SalesChannelFileRenderer $renderer,
        private readonly CacheTagCollector $cacheTagCollector,
    ) {
    }

    public function load(string $templatePath, SalesChannelContext $context): ?SalesChannelFileRenderResult
    {
        $file = $this->discovery->get($templatePath);
        if ($file === null) {
            return null;
        }

        $configuration = $this->configurationLoader->load(
            $file->fileFamily,
            $file->fileName,
            $context->getSalesChannelId(),
            $context->getContext()
        );
        if ($configuration === null || !$configuration->isEnabled()) {
            return null;
        }

        $this->cacheTagCollector->addTag(
            SalesChannelFileCacheInvalidator::buildCacheTag($configuration->getId()),
        );

        return new SalesChannelFileRenderResult(
            $file->fileName,
            $this->renderer->render($file, $context, $configuration->getTemplateOverrides()),
            $file->contentType,
        );
    }

    /**
     * @param array<string, mixed>|null $templateOverrides
     */
    public function preview(string $templatePath, SalesChannelContext $context, ?array $templateOverrides = null): ?SalesChannelFileRenderResult
    {
        $file = $this->discovery->get($templatePath);
        if ($file === null) {
            return null;
        }

        if ($templateOverrides === null) {
            $configuration = $this->configurationLoader->load(
                $file->fileFamily,
                $file->fileName,
                $context->getSalesChannelId(),
                $context->getContext(),
            );
            $templateOverrides = $configuration?->getTemplateOverrides() ?? [];
        }

        return new SalesChannelFileRenderResult(
            $file->fileName,
            $this->renderer->render($file, $context, $templateOverrides),
            $file->contentType,
        );
    }
}
