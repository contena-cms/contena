<?php declare(strict_types=1);

namespace Contena\Frontend\Theme;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Frontend\Theme\Exception\InvalidThemeConfigException;
use Contena\Frontend\Theme\Exception\ThemeException;

/**
 * @internal
 */
class ThemeMergedConfigBuilder
{
    private ThemeCollection $themes;

    /**
     * @internal
     *
     * @param EntityRepository<ThemeCollection> $themeRepository
     */
    public function __construct(
        private readonly FrontendPluginRegistry $extensionRegistry,
        private readonly EntityRepository $themeRepository,
    ) {
    }

    /**
     * @throws InvalidThemeConfigException
     * @throws ThemeException
     * @throws InconsistentCriteriaIdsException
     *
     * @return array<string, mixed>
     */
    public function getPlainThemeConfiguration(string $themeId, Context $context): array
    {
        $criteria = new Criteria()
            ->setTitle('theme-service::load-config');

        $this->themes = $this->themeRepository->search($criteria, $context)->getEntities();

        $theme = $this->themes->get($themeId);
        if (!$theme instanceof ThemeEntity) {
            throw ThemeException::couldNotFindThemeById($themeId);
        }

        $baseTheme = $this->themes->filter(static fn (ThemeEntity $themeEntry) => $themeEntry->getTechnicalName() === FrontendPluginRegistry::BASE_THEME_NAME)->first();
        if ($baseTheme === null) {
            throw ThemeException::couldNotFindThemeByName(FrontendPluginRegistry::BASE_THEME_NAME);
        }

        $baseThemeConfig = $this->mergeStaticConfig($baseTheme);

        $themeConfigFieldFactory = new ThemeConfigFieldFactory();
        $configFields = [];

        if ($theme->getParentThemeId()) {
            foreach ($this->getParentThemes($this->themes, $theme) as $parentTheme) {
                $configuredParentTheme = $this->mergeStaticConfig($parentTheme);
                $baseThemeConfig = array_replace_recursive($baseThemeConfig, $configuredParentTheme);
            }
        }

        $configuredTheme = $this->mergeStaticConfig($theme);
        $themeConfig = array_replace_recursive($baseThemeConfig, $configuredTheme);

        foreach ($themeConfig['fields'] ?? [] as $name => $item) {
            $configFields[$name] = $themeConfigFieldFactory->create($name, $item);
            if (
                isset($item['value'], $configuredTheme['fields'])
                && \is_array($item['value'])
                && \array_key_exists($name, $configuredTheme['fields'])
            ) {
                $configFields[$name]->setValue($configuredTheme['fields'][$name]['value']);
            }
        }

        $configFields = json_decode((string) json_encode($configFields, \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR);

        // Check if the theme is a database copy of a physical theme.
        // If so, use the technical name of the parent theme.
        if ($theme->getTechnicalName() === null && $theme->getParentThemeId() !== null) {
            $parentTheme = $this->themes->filter(static fn (ThemeEntity $themeEntry) => $themeEntry->getId() === $theme->getParentThemeId())->first();

            if ($parentTheme instanceof ThemeEntity) {
                $themeConfig['themeTechnicalName'] = $parentTheme->getTechnicalName();
            }
        } else {
            $themeConfig['themeTechnicalName'] = $theme->getTechnicalName();
        }

        $themeConfig['configInheritance'] = $this->getConfigInheritance($theme);
        $themeConfig['fields'] = $configFields;
        $themeConfig['currentFields'] = [];
        $themeConfig['baseThemeFields'] = [];

        foreach ($themeConfig['fields'] as $field => $fieldItem) {
            $isInherited = $this->fieldIsInherited($field, $configuredTheme);
            $themeConfig['currentFields'][$field]['isInherited'] = $isInherited;

            if ($isInherited) {
                $themeConfig['currentFields'][$field]['value'] = null;
            } elseif (\array_key_exists('value', $fieldItem)) {
                $themeConfig['currentFields'][$field]['value'] = $fieldItem['value'];
            }

            $isInherited = $this->fieldIsInherited($field, $baseThemeConfig);
            $themeConfig['baseThemeFields'][$field]['isInherited'] = $isInherited;

            if ($isInherited) {
                $themeConfig['baseThemeFields'][$field]['value'] = null;
            } elseif (\array_key_exists('value', $fieldItem) && isset($baseThemeConfig['fields'][$field]['value'])) {
                $themeConfig['baseThemeFields'][$field]['value'] = $baseThemeConfig['fields'][$field]['value'];
            }
        }

        if (isset($themeConfig['blocks'])) {
            foreach ($themeConfig['blocks'] as &$block) {
                unset($block['label']);
            }
            unset($block);
        }

        if (isset($themeConfig['fields'])) {
            foreach ($themeConfig['fields'] as &$field) {
                unset($field['label']);
                unset($field['helpText']);
            }
            unset($field);
        }

        return $themeConfig;
    }

    /**
     * @return array<string, mixed>
     */
    public function getThemeConfigurationFieldStructure(string $themeId, Context $context): array
    {
        $themeConfig = $this->getPlainThemeConfiguration($themeId, $context);

        $themeTechnicalName = (string) $themeConfig['themeTechnicalName'];
        $mergedFieldConfig = $themeConfig['fields'];

        $outputStructure = [];

        foreach ($mergedFieldConfig as $fieldName => $fieldConfig) {
            $tab = $this->getTab($fieldConfig);
            $block = $this->getBlock($fieldConfig);
            $section = $this->getSection($fieldConfig);

            $outputStructure = $this->addTranslations($outputStructure, $themeTechnicalName, $tab, $block, $section);

            $custom = $this->buildCustom($fieldConfig['custom'], $themeTechnicalName, $tab, $block, $section, $fieldName);

            $outputStructure['tabs'][$tab]['blocks'][$block]['sections'][$section]['fields'][$fieldName] =
                $this->buildField($fieldConfig, $custom, $themeTechnicalName, $tab, $block, $section, $fieldName);
        }

        $outputStructure['themeTechnicalName'] = $themeTechnicalName;
        $outputStructure['configInheritance'] = $themeConfig['configInheritance'];

        return $outputStructure;
    }

    /**
     * @param array<string, mixed> $fieldConfig
     * @param array<string, mixed>|null $custom
     *
     * @return array<string, mixed>
     */
    private function buildField(array $fieldConfig, ?array $custom, string $themeTechnicalName, string $tab, string $block, string $section, string $fieldName): array
    {
        return [
            'labelSnippetKey' => $this->buildSnippetKey(
                $themeTechnicalName,
                false,
                $tab,
                $block,
                $section,
                $fieldName,
            ),
            'helpTextSnippetKey' => $this->buildSnippetKey(
                $themeTechnicalName,
                true,
                $tab,
                $block,
                $section,
                $fieldName,
            ),
            'type' => $fieldConfig['type'] ?? null,
            'custom' => $custom,
            'fullWidth' => $fieldConfig['fullWidth'],
        ];
    }

    /**
     * @param array<string, ThemeEntity> $parentThemes
     *
     * @return array<string, ThemeEntity>
     */
    private function getParentThemes(ThemeCollection $themes, ThemeEntity $mainTheme, array $parentThemes = []): array
    {
        foreach ($this->getConfigInheritance($mainTheme) as $parentThemeName) {
            $parentTheme = $themes->filter(static fn (ThemeEntity $themeEntry) => $themeEntry->getTechnicalName() === str_replace('@', '', (string) $parentThemeName))->first();

            if ($parentTheme instanceof ThemeEntity && !\array_key_exists($parentTheme->getId(), $parentThemes)) {
                $parentThemes[$parentTheme->getId()] = $parentTheme;

                if ($parentTheme->getParentThemeId()) {
                    $parentThemes = $this->getParentThemes($themes, $mainTheme, $parentThemes);
                }
            }
        }

        if ($mainTheme->getParentThemeId()) {
            $parentTheme = $themes->filter(static fn (ThemeEntity $themeEntry) => $themeEntry->getId() === $mainTheme->getParentThemeId())->first();

            if ($parentTheme instanceof ThemeEntity && !\array_key_exists($parentTheme->getId(), $parentThemes)) {
                $parentThemes[$parentTheme->getId()] = $parentTheme;
                if ($parentTheme->getParentThemeId()) {
                    $parentThemes = $this->getParentThemes($themes, $mainTheme, $parentThemes);
                }
            }
        }

        return $parentThemes;
    }

    /**
     * @return array<int, string>
     */
    private function getConfigInheritance(ThemeEntity $mainTheme): array
    {
        $baseConfig = $mainTheme->getBaseConfig();

        $inheritanceConfig = $baseConfig['configInheritance'] ?? [];
        if ($inheritanceConfig !== []) {
            return $inheritanceConfig;
        }

        // For database copies (child themes), inherit config from parent theme.
        if (
            $baseConfig === null
            && $mainTheme->getTechnicalName() === null
            && $mainTheme->getParentThemeId() !== null
        ) {
            $parentId = $mainTheme->getParentThemeId();
            $parentTheme = $this->themes->get($parentId);

            if ($parentTheme instanceof ThemeEntity) {
                $parentConfigInheritance = $this->getConfigInheritance($parentTheme);
                if ($parentConfigInheritance !== []) {
                    return $parentConfigInheritance;
                }
            }
        }

        // Fallback: ensure every theme (except base theme) inherits from Frontend by default
        if ($mainTheme->getTechnicalName() !== FrontendPluginRegistry::BASE_THEME_NAME) {
            return [
                '@' . FrontendPluginRegistry::BASE_THEME_NAME,
            ];
        }

        return [];
    }

    /**
     * @return array<string, mixed>
     */
    private function mergeStaticConfig(ThemeEntity $theme): array
    {
        $configuredTheme = [];

        $pluginConfig = null;
        if ($theme->getTechnicalName()) {
            $pluginConfig = $this->extensionRegistry->getConfigurations()->getByTechnicalName($theme->getTechnicalName());
        }

        if ($pluginConfig !== null) {
            $configuredTheme = $pluginConfig->getThemeConfig();
        }

        if ($theme->getBaseConfig() !== null) {
            $configuredTheme = array_replace_recursive($configuredTheme ?? [], $theme->getBaseConfig());
        }

        if ($theme->getConfigValues() !== null) {
            foreach ($theme->getConfigValues() as $fieldName => $configValue) {
                if (\array_key_exists('value', $configValue)) {
                    $configuredTheme['fields'][$fieldName]['value'] = $configValue['value'];
                }
            }
        }

        return $configuredTheme ?: [];
    }

    /**
     * @param array<string, mixed> $fieldConfig
     */
    private function getTab(array $fieldConfig): string
    {
        $tab = 'default';

        if (isset($fieldConfig['tab'])) {
            $tab = $fieldConfig['tab'];
        }

        return $tab;
    }

    /**
     * @param array<string, mixed> $fieldConfig
     */
    private function getBlock(array $fieldConfig): string
    {
        $block = 'default';

        if (isset($fieldConfig['block'])) {
            $block = $fieldConfig['block'];
        }

        return $block;
    }

    /**
     * @param array<string, mixed> $fieldConfig
     */
    private function getSection(array $fieldConfig): string
    {
        $section = 'default';

        if (isset($fieldConfig['section'])) {
            $section = $fieldConfig['section'];
        }

        return $section;
    }

    /**
     * @param array<string, mixed> $configuration
     */
    private function fieldIsInherited(string $fieldName, array $configuration): bool
    {
        if (!isset($configuration['fields'])) {
            return true;
        }

        if (!\is_array($configuration['fields'])) {
            return true;
        }

        if (!\array_key_exists($fieldName, $configuration['fields'])) {
            return true;
        }

        return false;
    }

    private function buildSnippetKey(string $themeTechnicalName, bool $isHelpText, string ...$parts): string
    {
        return implode(
            '.',
            [
                ...$parts,
                $isHelpText ? 'helpText' : 'label',
            ],
        );
    }

    /**
     * @param array<string,mixed>|null $custom
     * @param string $themeTechnicalName
     *
     * @return ?array<string, mixed>
     */
    private function buildCustom(
        ?array $custom,
        mixed $themeTechnicalName,
        string $tab,
        string $block,
        string $section,
        string $fieldName
    ): ?array {
        if (\is_array($custom) && isset($custom['options']) && \is_array($custom['options'])) {
            foreach ($custom['options'] as $optionIndex => &$option) {
                $option['labelSnippetKey'] = $this->buildSnippetKey(
                    $themeTechnicalName,
                    false,
                    $tab,
                    $block,
                    $section,
                    $fieldName,
                    (string) $optionIndex,
                );
            }
            unset($option);
        }

        return $custom;
    }

    /**
     * @param array<string, mixed> $outputStructure
     *
     * @return array<string, mixed>
     */
    private function addTranslations(
        array $outputStructure,
        string $themeTechnicalName,
        string $tab,
        string $block,
        string $section,
    ): array {
        $tabSnippetKey = $this->buildSnippetKey($themeTechnicalName, false, $tab);
        $blockSnippetKey = $this->buildSnippetKey($themeTechnicalName, false, $tab, $block);
        $sectionSnippetKey = $this->buildSnippetKey($themeTechnicalName, false, $tab, $block, $section);

        $outputStructure['tabs'][$tab]['labelSnippetKey'] = $tabSnippetKey;
        $outputStructure['tabs'][$tab]['blocks'][$block]['labelSnippetKey'] = $blockSnippetKey;
        $outputStructure['tabs'][$tab]['blocks'][$block]['sections'][$section]['labelSnippetKey'] = $sectionSnippetKey;

        return $outputStructure;
    }
}
