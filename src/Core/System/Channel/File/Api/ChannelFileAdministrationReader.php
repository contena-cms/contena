<?php declare(strict_types=1);

namespace Contena\Core\System\Channel\File\Api;

use Contena\Core\Framework\Context;
use Contena\Core\System\Channel\Aggregate\ChannelFile\ChannelFileEntity;
use Contena\Core\System\Channel\File\ChannelFileTemplateResolver;
use Contena\Core\System\Channel\File\Discovery\ChannelFile;
use Contena\Core\System\Channel\File\Discovery\ChannelFileDiscovery;
use Contena\Core\System\Channel\File\Loader\ChannelFileConfigurationLoader;
use Twig\Environment;
use Twig\Error\LoaderError;

/**
 * @internal
 */
class ChannelFileAdministrationReader
{
    private const USER_PROVIDED_CONTENT_BLOCK = 'user_provided_content';

    public function __construct(
        private readonly ChannelFileDiscovery $discovery,
        private readonly ChannelFileConfigurationLoader $configurationLoader,
        private readonly Environment $twig,
        private readonly ChannelFileTemplateResolver $templateResolver,
    ) {
    }

    /**
     * @return list<ChannelFileAdministrationListItem>
     */
    public function list(string $fileFamily, string $channelId, Context $context): array
    {
        $configurations = $this->configurationLoader->loadForFileFamily($fileFamily, $channelId, $context);
        $files = [];

        foreach ($this->discovery->discover($fileFamily) as $file) {
            $configuration = $configurations[mb_strtolower($file->fileName)] ?? null;

            $files[] = new ChannelFileAdministrationListItem(
                $file->fileFamily,
                $file->fileName,
                $file->contentType,
                $configuration === null ? null : $this->serializeConfiguration($configuration),
            );
        }

        return $files;
    }

    public function detail(string $fileFamily, string $fileName, string $channelId, Context $context): ?ChannelFileAdministrationDetail
    {
        $file = $this->discovery->discover($fileFamily)[mb_strtolower($fileName)] ?? null;
        if (!$file instanceof ChannelFile) {
            return null;
        }

        $configuration = $this->configurationLoader->load($fileFamily, $fileName, $channelId, $context);
        $templates = $this->templateResolver->resolveTemplateChain($file, $channelId);

        return new ChannelFileAdministrationDetail(
            $file->fileFamily,
            $file->fileName,
            $file->templatePath,
            $file->contentType,
            $this->serializeTemplates($templates, $file->baseTemplateName),
            $this->supportsUserProvidedContent($templates),
            $configuration === null ? null : $this->serializeConfiguration($configuration),
        );
    }

    private function serializeConfiguration(ChannelFileEntity $configuration): ChannelFileAdministrationConfiguration
    {
        return new ChannelFileAdministrationConfiguration(
            $configuration->getId(),
            $configuration->isEnabled(),
            $configuration->getTemplateOverrides(),
        );
    }

    /**
     * @param array<string, string> $templates Twig namespace mapped to resolved template name
     *
     * @return list<ChannelFileAdministrationTemplate>
     */
    private function serializeTemplates(array $templates, string $baseTemplateName): array
    {
        $serialized = [];
        $baseTemplateName = array_last($templates) ?? $baseTemplateName;

        foreach ($templates as $twigNamespace => $templateName) {
            $serialized[] = new ChannelFileAdministrationTemplate(
                $twigNamespace,
                $templateName,
                $this->loadTemplateContent($templateName),
                $templateName === $baseTemplateName ? 'base' : 'extension',
            );
        }

        return $serialized;
    }

    private function loadTemplateContent(string $templateName): string
    {
        try {
            return $this->twig->getLoader()->getSourceContext($templateName)->getCode();
        } catch (LoaderError) {
            return '';
        }
    }

    /**
     * @param array<string, string> $templates Twig namespace mapped to resolved template name
     */
    private function supportsUserProvidedContent(array $templates): bool
    {
        foreach ($templates as $templateName) {
            $source = $this->loadTemplateContent($templateName);

            if (preg_match('/{%-?\s*block\s+' . preg_quote(self::USER_PROVIDED_CONTENT_BLOCK, '/') . '\b/', $source) === 1) {
                return true;
            }
        }

        return false;
    }
}
