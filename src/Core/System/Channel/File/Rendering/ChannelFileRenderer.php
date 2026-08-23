<?php declare(strict_types=1);

namespace Contena\Core\System\Channel\File\Rendering;

use Contena\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\System\Channel\ChannelCollection;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\ChannelEntity;
use Contena\Core\System\Channel\File\ChannelFileTemplateResolver;
use Contena\Core\System\Channel\File\Discovery\ChannelFile;
use Contena\Core\System\Channel\File\Rendering\Extension\ChannelFileRenderParametersExtension;
use Twig\Environment;

/**
 * @internal
 */
class ChannelFileRenderer
{
    private const USER_PROVIDED_CONTENT_OVERRIDE_KEY = 'user_provided_content';

    private const USER_PROVIDED_CONTENT_TEMPLATE = '@ChannelFileUserProvidedContent/%s';

    /**
     * @param EntityRepository<ChannelCollection> $channelRepository
     */
    public function __construct(
        private readonly Environment $twig,
        private readonly ChannelFileTemplateResolver $templateResolver,
        private readonly ChannelFileTemplateOverrideLoader $templateOverrideLoader,
        private readonly SeoUrlPlaceholderHandlerInterface $seoUrlPlaceholderHandler,
        private readonly EntityRepository $channelRepository,
        private readonly ExtensionDispatcher $extensions,
    ) {
    }

    /**
     * @param array<string, mixed> $templateOverrides
     */
    public function render(ChannelFile $file, ChannelContext $context, array $templateOverrides = []): string
    {
        $templates = $this->templateResolver->resolveTemplateChain($file, $context->getChannelId());
        $overrideTemplates = $this->buildOverrideTemplates($templates, $templateOverrides);
        $overrideTemplates += $this->buildCaseVariantAliases($file, $templates);
        $parameters = $this->buildParameters($file, $context);
        $templateName = $this->getRenderTemplateName($file, $templates);

        $userProvidedContent = $this->getUserProvidedContent($templateOverrides);
        if ($userProvidedContent !== null) {
            $parentTemplateName = $templateName;
            $templateName = \sprintf(self::USER_PROVIDED_CONTENT_TEMPLATE, $file->templatePath);
            $overrideTemplates[$templateName] = $this->buildUserProvidedContentTemplate($userProvidedContent, $parentTemplateName);
        }

        $content = $this->templateOverrideLoader->withTemplateOverrides(
            $overrideTemplates,
            fn (): string => $this->twig->render($templateName, $parameters)
        );

        return $this->seoUrlPlaceholderHandler->replace($content, '', $context);
    }

    /**
     * @param array<string, string> $templates
     */
    private function getRenderTemplateName(ChannelFile $file, array $templates): string
    {
        return array_first($templates) ?? $file->baseTemplateName;
    }

    /**
     * @param array<string, string> $templates
     * @param array<string, mixed> $templateOverrides
     *
     * @return array<string, string>
     */
    private function buildOverrideTemplates(array $templates, array $templateOverrides): array
    {
        $overrideTemplates = [];

        foreach ($templates as $twigNamespace => $templateName) {
            $override = $templateOverrides[$twigNamespace] ?? null;

            if (!\is_string($override)) {
                continue;
            }

            $overrideTemplates[$templateName] = $override;
        }

        return $overrideTemplates;
    }

    /**
     * @param array<string, string> $templates
     *
     * @return array<string, string>
     */
    private function buildCaseVariantAliases(ChannelFile $file, array $templates): array
    {
        $aliases = [];

        foreach ($templates as $twigNamespace => $templateName) {
            foreach ($file->templatePaths as $templatePath) {
                $alias = '@' . $twigNamespace . '/' . $templatePath;

                if ($alias !== $templateName) {
                    $encodedTemplateName = json_encode($templateName, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);
                    \assert(\is_string($encodedTemplateName));
                    $aliases[$alias] = \sprintf('{%% extends %s %%}', $encodedTemplateName);
                }
            }
        }

        return $aliases;
    }

    /**
     * @param array<string, mixed> $templateOverrides
     */
    private function getUserProvidedContent(array $templateOverrides): ?string
    {
        $userProvidedContent = $templateOverrides[self::USER_PROVIDED_CONTENT_OVERRIDE_KEY] ?? null;

        if (!\is_string($userProvidedContent) || trim($userProvidedContent) === '') {
            return null;
        }

        return $userProvidedContent;
    }

    private function buildUserProvidedContentTemplate(string $userProvidedContent, string $parentTemplateName): string
    {
        $encodedContent = json_encode($userProvidedContent, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);
        \assert(\is_string($encodedContent));

        // The generated override namespace is not part of the normal namespace hierarchy.
        // Resolve the render entry first, then extend that concrete template.
        return \sprintf(
            "{%% extends '%s' %%}\n\n{%% block user_provided_content %%}{{ %s|raw }}{%% endblock %%}",
            $parentTemplateName,
            $encodedContent
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function buildParameters(ChannelFile $file, ChannelContext $context): array
    {
        $channel = $this->loadChannel($context);

        return $this->extensions->publish(
            name: ChannelFileRenderParametersExtension::NAME,
            extension: new ChannelFileRenderParametersExtension($file, $context, $channel),
            function: $this->buildDefaultParameters(...),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function buildDefaultParameters(ChannelFile $file, ChannelContext $context, ChannelEntity $channel): array
    {
        return [
            'context' => $context,
            'channel' => $channel,
            'channelFile' => $file,
        ];
    }

    private function loadChannel(ChannelContext $context): ChannelEntity
    {
        $criteria = new Criteria([$context->getChannelId()]);
        $criteria->setTitle('channel-file-renderer::channel');
        $criteria->addAssociation('languages.translationCode');
        $criteria->addAssociation('domains');
        $criteria->getAssociation('languages')->addSorting(new FieldSorting('name', FieldSorting::ASCENDING));
        $criteria->getAssociation('domains')->addSorting(new FieldSorting('url', FieldSorting::ASCENDING));

        $channel = $this->channelRepository->search($criteria, $context->getContext())->getEntities()->first();

        if (!$channel instanceof ChannelEntity) {
            return $context->getChannel();
        }

        return $channel;
    }
}
