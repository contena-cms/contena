<?php declare(strict_types=1);

namespace Contena\Frontend\Theme\Twig;

use Contena\Core\Framework\Adapter\Twig\NamespaceHierarchy\TemplateNamespaceHierarchyBuilderInterface;
use Contena\Core\Framework\Log\Package;
use Contena\Core\ChannelRequest;
use Contena\Core\System\Channel\File\Event\ChannelFileTemplateResolveEvent;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Frontend\Theme\DatabaseChannelThemeLoader;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @internal
 */
class ThemeNamespaceHierarchyBuilder implements TemplateNamespaceHierarchyBuilderInterface, EventSubscriberInterface, ResetInterface
{
    /**
     * @var array<string, bool>
     */
    private array $themes = [];

    /**
     * @internal
     */
    public function __construct(
        private readonly ThemeInheritanceBuilderInterface $themeInheritanceBuilder,
        private readonly ?DatabaseChannelThemeLoader $channelThemeLoader = null
    ) {
    }

    /**
     * @return array<string, string|array{0: string, 1: int}|list<array{0: string, 1?: int}>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => 'requestEvent',
            KernelEvents::EXCEPTION => 'requestEvent',
            ChannelFileTemplateResolveEvent::class => 'onChannelFileTemplateResolve',
        ];
    }

    public function requestEvent(RequestEvent $event): void
    {
        $request = $event->getRequest();

        $this->themes = $this->detectedThemes($request);
    }

    public function onDocumentRendering(DocumentTemplateRendererParameterEvent $event): void
    {
        $parameters = $event->getParameters();

        if (!\array_key_exists('context', $parameters)) {
            return;
        }

        /** @var ChannelContext $context */
        $context = $parameters['context'];

        $this->resolveThemesForChannel($context->getChannelId());
    }

    public function onChannelFileTemplateResolve(ChannelFileTemplateResolveEvent $event): void
    {
        $this->resolveThemesForChannel($event->channelId);
    }

    public function buildNamespaceHierarchy(array $namespaceHierarchy): array
    {
        if ($this->themes === []) {
            return $namespaceHierarchy;
        }

        return $this->themeInheritanceBuilder->build($namespaceHierarchy, $this->themes);
    }

    public function reset(): void
    {
        $this->themes = [];
    }

    /**
     * @return array<string, bool>
     */
    private function detectedThemes(Request $request): array
    {
        $themes = [];
        // get name if theme is not inherited
        $theme = $request->attributes->get(ChannelRequest::ATTRIBUTE_THEME_NAME);

        if (!$theme) {
            // get theme name from base theme because for inherited themes the name is always null
            $theme = $request->attributes->get(ChannelRequest::ATTRIBUTE_THEME_BASE_NAME);
        }

        if (!$theme) {
            return [];
        }

        $themes[$theme] = true;
        $themes['Frontend'] = true;

        return $themes;
    }

    private function resolveThemesForChannel(string $channelId): void
    {
        $themes = [];

        $theme = $this->channelThemeLoader?->load($channelId);

        if ($theme !== null && $theme !== [] && isset($theme[0])) {
            $themes[$theme[0]] = true;
        }

        $themes['Frontend'] = true;

        $this->themes = $themes;
    }
}
