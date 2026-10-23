<?php declare(strict_types=1);

namespace Contena\Core\System\Channel\File;

use Contena\Core\Framework\Adapter\Twig\NamespaceHierarchy\NamespaceHierarchyBuilder;
use Contena\Core\Framework\Adapter\Twig\TemplateFinder;
use Contena\Core\System\Channel\File\Discovery\ChannelFile;
use Contena\Core\System\Channel\File\Event\ChannelFileTemplateResolveEvent;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Twig\Loader\LoaderInterface;

/**
 * @internal
 */
class ChannelFileTemplateResolver
{
    public function __construct(
        private readonly TemplateFinder $templateFinder,
        private readonly NamespaceHierarchyBuilder $namespaceHierarchyBuilder,
        private readonly LoaderInterface $loader,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function getRenderTemplateName(ChannelFile $file, ?string $channelId = null): string
    {
        $templates = $this->resolveTemplateChain($file, $channelId);

        return array_first($templates) ?? $file->baseTemplateName;
    }

    public function getBaseTemplateName(ChannelFile $file, ?string $channelId = null): string
    {
        $templates = $this->resolveTemplateChain($file, $channelId);

        return array_last($templates) ?? $file->baseTemplateName;
    }

    /**
     * @return array<string, string> Twig namespace mapped to resolved template name
     */
    public function resolveTemplateChain(ChannelFile $file, ?string $channelId = null): array
    {
        if ($channelId !== null) {
            $this->eventDispatcher->dispatch(new ChannelFileTemplateResolveEvent($channelId));
        }

        $this->templateFinder->reset();

        $templates = [];
        $templatePaths = $file->templatePaths ?: [$file->templatePath];

        foreach (array_keys($this->namespaceHierarchyBuilder->buildHierarchy()) as $twigNamespace) {
            foreach ($templatePaths as $templatePath) {
                $templateName = '@' . $twigNamespace . '/' . $templatePath;

                if (!$this->loader->exists($templateName)) {
                    continue;
                }

                $templates[$twigNamespace] = $templateName;

                break;
            }
        }

        return $templates ?: $file->templates;
    }
}
