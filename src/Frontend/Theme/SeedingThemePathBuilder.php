<?php declare(strict_types=1);

namespace Contena\Frontend\Theme;

use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\Framework\Util\Hasher;
use Contena\Core\System\SystemConfig\SystemConfigService;

class SeedingThemePathBuilder extends AbstractThemePathBuilder
{
    /**
     * Each theme's seed lives under its own key ("<prefix><themeId>") so saving is a single-row
     * write; a shared map would need a racy read-modify-write across concurrent compilations.
     */
    private const string SYSTEM_CONFIG_KEY_PREFIX = 'frontend.themeSeeds.';

    /**
     * Legacy channel-wide seed (one string for all themes), kept as a fallback until each theme is
     * recompiled. Distinct prefixes ("themeSeeds" vs "themeSeed") avoid key-nesting clashes.
     */
    private const string LEGACY_SYSTEM_CONFIG_KEY = 'frontend.themeSeed';

    /**
     * @internal
     */
    public function __construct(
        private readonly SystemConfigService $systemConfigService,
    ) {
    }

    public function assemblePath(string $channelId, string $themeId): string
    {
        return $this->generateNewPath($channelId, $themeId, $this->getSeed($channelId, $themeId));
    }

    public function generateNewPath(string $channelId, string $themeId, string $seed): string
    {
        return Hasher::hash($themeId . $channelId . $seed);
    }

    public function saveSeed(string $channelId, string $themeId, string $seed): void
    {
        $this->systemConfigService->set(self::SYSTEM_CONFIG_KEY_PREFIX . $themeId, $seed, $channelId, false);
    }

    public function getDecorated(): AbstractThemePathBuilder
    {
        throw new DecorationPatternException(self::class);
    }

    private function getSeed(string $channelId, string $themeId): string
    {
        $seed = $this->systemConfigService->get(self::SYSTEM_CONFIG_KEY_PREFIX . $themeId, $channelId);

        if (\is_string($seed) && $seed !== '') {
            return $seed;
        }

        // Fallback for themes still on the legacy channel-wide seed.
        $legacy = $this->systemConfigService->get(self::LEGACY_SYSTEM_CONFIG_KEY, $channelId);

        return \is_string($legacy) ? $legacy : '';
    }
}
