<?php declare(strict_types=1);

namespace Contena\Frontend\Theme\Subscriber;

use Contena\Core\System\Snippet\Event\SnippetsThemeResolveEvent;
use Contena\Frontend\Theme\DatabaseChannelThemeLoader;
use Contena\Frontend\Theme\FrontendPluginRegistry;
use Contena\Frontend\Theme\ThemeRuntimeConfigService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
class ThemeSnippetsSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly ThemeRuntimeConfigService $themeRuntimeConfigService,
        private readonly DatabaseChannelThemeLoader $channelThemeLoader
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            SnippetsThemeResolveEvent::class => 'onSnippetsThemeResolve',
        ];
    }

    public function onSnippetsThemeResolve(SnippetsThemeResolveEvent $event): void
    {
        $channelId = $event->getChannelId();

        $usedThemes = $this->getUsedThemes($channelId);
        $unusedThemes = $this->getUnusedThemes($usedThemes);

        $event->setUsedThemes($usedThemes);
        $event->setUnusedThemes($unusedThemes);
    }

    /**
     * @return list<string>
     */
    private function getUsedThemes(?string $channelId = null): array
    {
        $usedThemes = [];

        // Load used themes
        if ($channelId !== null) {
            $usedThemes = $this->channelThemeLoader->load($channelId);
        }

        return array_values(array_unique([
            ...$usedThemes,
            FrontendPluginRegistry::BASE_THEME_NAME, // Frontend snippets should always be loaded
        ]));
    }

    /**
     * @param list<string> $usingThemes
     *
     * @return list<string>
     */
    private function getUnusedThemes(array $usingThemes = []): array
    {
        $availableThemes = $this->themeRuntimeConfigService->getActiveThemeNames();
        $unusedThemes = array_diff($availableThemes, $usingThemes);

        return array_values($unusedThemes);
    }
}
