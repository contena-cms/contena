<?php declare(strict_types=1);

namespace Contena\Frontend\Theme\ConfigLoader;

use League\Flysystem\FilesystemOperator;
use Contena\Core\Framework\Context;
use Contena\Frontend\Theme\Event\ThemeConfigChangedEvent;
use Contena\Frontend\Theme\Event\ThemeConfigResetEvent;
use Contena\Frontend\Theme\FrontendPluginConfiguration\FileCollection;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
class StaticFileConfigDumper implements EventSubscriberInterface
{
    public function __construct(
        private readonly AbstractConfigLoader $configLoader,
        private readonly AbstractAvailableThemeProvider $availableThemeProvider,
        private readonly FilesystemOperator $privateFilesystem,
        private readonly FilesystemOperator $temporaryFilesystem
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ThemeConfigChangedEvent::class => 'dumpConfigFromEvent',
            ThemeConfigResetEvent::class => 'dumpConfigFromEvent',
        ];
    }

    /**
     * @param array<string, FileCollection|string> $dump
     */
    public function dumpConfigInVar(string $filePath, array $dump): void
    {
        $this->temporaryFilesystem->write($filePath, json_encode($dump, \JSON_PRETTY_PRINT | \JSON_THROW_ON_ERROR));
    }

    public function dumpConfig(Context $context): void
    {
        $availableThemes = $this->availableThemeProvider->load($context, false);

        $themeConfigDir = \dirname(StaticFileAvailableThemeProvider::THEME_INDEX);
        if (!$this->privateFilesystem->directoryExists($themeConfigDir)) {
            $this->privateFilesystem->createDirectory($themeConfigDir);
        }

        $this->privateFilesystem->write(StaticFileAvailableThemeProvider::THEME_INDEX, json_encode($availableThemes, \JSON_THROW_ON_ERROR));

        foreach ($availableThemes as $themeId) {
            $struct = $this->configLoader->load($themeId, $context);
            $path = \sprintf('theme-config/%s.json', $themeId);

            $this->privateFilesystem->write($path, json_encode($struct->jsonSerialize(), \JSON_THROW_ON_ERROR));
        }
    }

    public function dumpConfigFromEvent(): void
    {
        $this->dumpConfig(Context::createDefaultContext());
    }
}
