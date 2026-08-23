<?php declare(strict_types=1);

namespace Contena\Frontend\Theme\ConfigLoader;

use Contena\Core\Content\Media\MediaCollection;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Frontend\Theme\Exception\ThemeException;
use Contena\Frontend\Theme\FrontendPluginConfiguration\FrontendPluginConfiguration;
use Contena\Frontend\Theme\FrontendPluginRegistry;
use Contena\Frontend\Theme\ThemeCollection;
use Contena\Frontend\Theme\ThemeConfigField;
use Contena\Frontend\Theme\ThemeEntity;

class DatabaseConfigLoader extends AbstractConfigLoader
{
    /**
     * @internal
     *
     * @param EntityRepository<ThemeCollection> $themeRepository
     * @param EntityRepository<MediaCollection> $mediaRepository
     */
    public function __construct(
        private readonly EntityRepository $themeRepository,
        private readonly FrontendPluginRegistry $extensionRegistry,
        private readonly EntityRepository $mediaRepository,
        private readonly string $baseTheme = FrontendPluginRegistry::BASE_THEME_NAME
    ) {
    }

    public function getDecorated(): AbstractConfigLoader
    {
        throw new DecorationPatternException(self::class);
    }

    public function load(string $themeId, Context $context): FrontendPluginConfiguration
    {
        $pluginConfig = $this->loadConfigByName($themeId, $context);

        if (!$pluginConfig) {
            throw ThemeException::couldNotFindThemeById($themeId);
        }

        $pluginConfig = clone $pluginConfig;
        $pluginConfig->setThemeConfig($this->loadCompileConfig($themeId, $context));

        $this->resolveMediaIds($pluginConfig, $context);

        return $pluginConfig;
    }

    /**
     * @return array<string, mixed>
     */
    private function loadCompileConfig(string $themeId, Context $context): array
    {
        $config = $this->loadRecursiveConfig($themeId, $context);
        $field = new ThemeConfigField();

        foreach ($config['fields'] as $name => $item) {
            $clone = clone $field;
            $clone->setName($name);
            $clone->assign($item);
            $config[$name] = $clone;
        }

        return json_decode(json_encode($config, \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<string, mixed>
     */
    private function loadRecursiveConfig(string $themeId, Context $context, bool $withBase = true): array
    {
        $criteria = new Criteria();
        $criteria->setTitle('theme-service::load-config');

        $themes = $this->themeRepository->search($criteria, $context)->getEntities();
        $theme = $themes->get($themeId);
        if (!$theme) {
            throw ThemeException::couldNotFindThemeById($themeId);
        }

        $baseThemeConfig = [];
        if ($withBase) {
            $baseTheme = $themes->filter(fn (ThemeEntity $themeEntry) => $themeEntry->getTechnicalName() === $this->baseTheme)->first();
            \assert($baseTheme !== null);

            $baseThemeConfig = $this->mergeStaticConfig($baseTheme);
        }

        if ($theme->getParentThemeId()) {
            foreach ($this->getParentThemeIds($themes, $theme) as $parentTheme) {
                $baseThemeConfig = array_replace_recursive($baseThemeConfig, $this->mergeStaticConfig($parentTheme));
            }
        }

        return array_replace_recursive($baseThemeConfig, $this->mergeStaticConfig($theme));
    }

    /**
     * @param array<string, ThemeEntity> $parentThemes
     *
     * @return array<string, ThemeEntity>
     */
    private function getParentThemeIds(ThemeCollection $themes, ThemeEntity $mainTheme, array $parentThemes = []): array
    {
        foreach ($this->getConfigInheritance($mainTheme) as $parentThemeName) {
            $parentTheme = $themes->filter(static fn (ThemeEntity $themeEntry) => $themeEntry->getTechnicalName() === str_replace('@', '', $parentThemeName))->first();

            if (!$parentTheme instanceof ThemeEntity || \array_key_exists($parentTheme->getId(), $parentThemes)) {
                continue;
            }

            $parentThemes[$parentTheme->getId()] = $parentTheme;
            if ($parentTheme->getParentThemeId()) {
                $parentThemes = $this->getParentThemeIds($themes, $mainTheme, $parentThemes);
            }
        }

        if ($mainTheme->getParentThemeId() === null) {
            return $parentThemes;
        }

        $parentTheme = $themes->filter(static fn (ThemeEntity $themeEntry) => $themeEntry->getId() === $mainTheme->getParentThemeId())->first();

        if (!$parentTheme instanceof ThemeEntity || \array_key_exists($parentTheme->getId(), $parentThemes)) {
            return $parentThemes;
        }

        $parentThemes[$parentTheme->getId()] = $parentTheme;
        if ($parentTheme->getParentThemeId()) {
            $parentThemes = $this->getParentThemeIds($themes, $mainTheme, $parentThemes);
        }

        return $parentThemes;
    }

    private function loadConfigByName(string $themeId, Context $context): ?FrontendPluginConfiguration
    {
        $theme = $this->themeRepository->search(new Criteria([$themeId]), $context)->getEntities()->first();
        if (!$theme) {
            return $this->extensionRegistry->getConfigurations()->getByTechnicalName($this->baseTheme);
        }

        $pluginConfig = null;
        if ($theme->getTechnicalName() !== null) {
            $pluginConfig = $this->extensionRegistry->getConfigurations()->getByTechnicalName($theme->getTechnicalName());
        }

        if ($pluginConfig !== null) {
            return $pluginConfig;
        }

        if ($theme->getParentThemeId() !== null) {
            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('id', $theme->getParentThemeId()));

            $parentTheme = $this->themeRepository->search($criteria, $context)->getEntities()->first();
            \assert($parentTheme instanceof ThemeEntity);

            if (!\is_string($parentTheme->getTechnicalName())) {
                return $this->extensionRegistry->getConfigurations()->getByTechnicalName($this->baseTheme);
            }

            return $this->extensionRegistry->getConfigurations()->getByTechnicalName($parentTheme->getTechnicalName());
        }

        return $this->extensionRegistry->getConfigurations()->getByTechnicalName($this->baseTheme);
    }

    /**
     * @return array<int|string, mixed>
     */
    private function mergeStaticConfig(ThemeEntity $theme): array
    {
        $configuredTheme = [];
        $pluginConfig = null;
        if ($theme->getTechnicalName()) {
            $pluginConfig = $this->extensionRegistry->getConfigurations()->getByTechnicalName($theme->getTechnicalName());
        }

        if ($pluginConfig !== null) {
            $configuredTheme = $pluginConfig->getThemeConfig() ?? [];
        }

        if ($theme->getBaseConfig() !== null) {
            $configuredTheme = array_replace_recursive($configuredTheme, $theme->getBaseConfig());
        }

        if ($theme->getConfigValues() === null) {
            return $configuredTheme;
        }

        foreach ($theme->getConfigValues() as $fieldName => $configValue) {
            if (\array_key_exists('value', $configValue)) {
                $configuredTheme['fields'][$fieldName]['value'] = $configValue['value'];
            }
        }

        return $configuredTheme;
    }

    private function resolveMediaIds(FrontendPluginConfiguration $pluginConfig, Context $context): void
    {
        $config = $pluginConfig->getThemeConfig();
        if (!\is_array($config)) {
            return;
        }

        $ids = [];
        foreach ($config['fields'] as $data) {
            if (!isset($data['value'])
                || $data['value'] === ''
                || !\is_string($data['value'])
                || (\array_key_exists('scss', $data) && $data['scss'] === false)
                || (isset($data['type']) && $data['type'] !== 'media')
                || !Uuid::isValid($data['value'])
            ) {
                continue;
            }

            $ids[] = $data['value'];
        }

        if ($ids === []) {
            return;
        }

        $mediaResult = $this->mediaRepository->search(new Criteria($ids), $context)->getEntities();

        foreach ($config['fields'] as $key => $data) {
            if (!isset($data['value']) || !\is_string($data['value'])) {
                continue;
            }

            if ($data['value'] === ''
                || (\array_key_exists('scss', $data) && $data['scss'] === false)
                || (isset($data['type']) && $data['type'] !== 'media')
                || !Uuid::isValid($data['value'])
                || !$mediaResult->has($data['value'])
            ) {
                continue;
            }

            $config['fields'][$key]['value'] = $mediaResult->get($data['value'])->getUrl();
        }

        $pluginConfig->setThemeConfig($config);
    }

    /**
     * @return list<string>
     */
    private function getConfigInheritance(ThemeEntity $mainTheme): array
    {
        $baseConfig = $mainTheme->getBaseConfig();
        $inheritanceConfig = $baseConfig['configInheritance'] ?? [];
        if ($inheritanceConfig !== []) {
            return $inheritanceConfig;
        }

        if ($baseConfig === null && $mainTheme->getTechnicalName() === null && $mainTheme->getParentThemeId() !== null) {
            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('id', $mainTheme->getParentThemeId()));

            $parentTheme = $this->themeRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();
            if ($parentTheme instanceof ThemeEntity) {
                $parentConfigInheritance = $this->getConfigInheritance($parentTheme);
                if ($parentConfigInheritance !== []) {
                    return $parentConfigInheritance;
                }
            }
        }

        if ($mainTheme->getTechnicalName() !== FrontendPluginRegistry::BASE_THEME_NAME) {
            return ['@' . FrontendPluginRegistry::BASE_THEME_NAME];
        }

        return [];
    }
}
