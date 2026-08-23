<?php declare(strict_types=1);

namespace Contena\Frontend\Theme\Twig;

use Contena\Core\ChannelRequest;
use Contena\Core\Framework\Adapter\Twig\NamespaceHierarchy\TemplateNamespaceHierarchyBuilderInterface;
use Contena\Core\System\Channel\File\Event\ChannelFileTemplateResolveEvent;
use Contena\Frontend\Theme\DatabaseChannelThemeLoader;
use Contena\Frontend\Theme\FrontendPluginRegistry;
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
        private readonly ?DatabaseChannelThemeLoader $channelThemeLoader = null,
    ) {
    }

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
        $this->themes = $this->detectedThemes($event->getRequest());
    }

    public function onChannelFileTemplateResolve(ChannelFileTemplateResolveEvent $event): void
    {
        $this->resolveThemesForChannel($event->channelId);
    }

    /**
     * @param array<string, int> $namespaceHierarchy
     *
     * @return array<string, int>
     */
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
        $theme = $request->attributes->get(ChannelRequest::ATTRIBUTE_THEME_NAME);

        if (!$theme) {
            $theme = $request->attributes->get(ChannelRequest::ATTRIBUTE_THEME_BASE_NAME);
        }

        if (!\is_string($theme) || $theme === '') {
            return [];
        }

        $themes[$theme] = true;
        $themes[FrontendPluginRegistry::BASE_THEME_NAME] = true;

        return $themes;
    }

    private function resolveThemesForChannel(string $channelId): void
    {
        $themes = [];

        $theme = $this->channelThemeLoader?->load($channelId);

        if ($theme !== null && $theme !== [] && isset($theme[0])) {
            $themes[$theme[0]] = true;
        }

        $themes[FrontendPluginRegistry::BASE_THEME_NAME] = true;

        $this->themes = $themes;
    }
}
