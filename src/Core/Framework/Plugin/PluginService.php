<?php declare(strict_types=1);

namespace Contena\Core\Framework\Plugin;

use Composer\IO\IOInterface;
use Composer\Package\AliasPackage;
use Composer\Package\CompletePackageInterface;
use Composer\Package\Version\VersionParser;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Plugin\Exception\ExceptionCollection;
use Contena\Core\Framework\Plugin\Exception\PluginComposerJsonInvalidException;
use Contena\Core\Framework\Plugin\Exception\PluginNotFoundException;
use Contena\Core\Framework\Plugin\Util\PluginFinder;
use Contena\Core\Framework\Plugin\Util\VersionSanitizer;
use Contena\Core\Framework\ContenaHttpException;
use Contena\Core\System\Language\LanguageCollection;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
class PluginService
{
    final public const string COMPOSER_AUTHOR_ROLE_MANUFACTURER = 'Manufacturer';

    /**
     * @param EntityRepository<PluginCollection> $pluginRepo
     * @param EntityRepository<LanguageCollection> $languageRepo
     */
    public function __construct(
        private readonly string $pluginDir,
        private readonly string $projectDir,
        private readonly EntityRepository $pluginRepo,
        private readonly EntityRepository $languageRepo,
        private readonly PluginFinder $pluginFinder,
        private readonly VersionSanitizer $versionSanitizer
    ) {
    }

    public function refreshPlugins(Context $contenaContext, IOInterface $composerIO): ExceptionCollection
    {
        $errors = new ExceptionCollection();
        $pluginsFromFileSystem = $this->pluginFinder->findPlugins($this->pluginDir, $this->projectDir, $errors, $composerIO);

        $installedPlugins = $this->getPlugins(new Criteria(), $contenaContext);

        $plugins = [];
        foreach ($pluginsFromFileSystem as $pluginFromFileSystem) {
            $baseClass = $pluginFromFileSystem->getBaseClass();
            $pluginPath = $pluginFromFileSystem->getPath();
            $info = $pluginFromFileSystem->getComposerPackage();

            $autoload = $info->getAutoload();
            if ($autoload === [] || (empty($autoload['psr-4']) && empty($autoload['psr-0']))) {
                $errors->add(new PluginComposerJsonInvalidException(
                    $pluginPath . '/composer.json',
                    ['Neither a PSR-4 nor PSR-0 autoload information is given.']
                ));

                continue;
            }

            $version = $info->getVersion();

            // default branches are normalized to alias internally @see https://github.com/composer/composer/blob/95dca79fc2e18c3a4e33f207c1fcaa7d5b559400/src/Composer/Package/Locker.php#L353,
            // when showing the version name, they will be normalized back to real name of the alias @see https://github.com/composer/composer/blob/95dca79fc2e18c3a4e33f207c1fcaa7d5b559400/src/Composer/Command/ShowCommand.php#L752
            if ($version === VersionParser::DEFAULT_BRANCH_ALIAS && $info instanceof AliasPackage) {
                $version = $info->getAliasOf()->getVersion();
            }

            $pluginVersion = $this->versionSanitizer->sanitizePluginVersion($version);
            $extra = $info->getExtra();
            $license = $info->getLicense();
            $pluginIconPath = $extra['plugin-icon'] ?? 'src/Resources/config/plugin.png';

            $pluginData = [
                'name' => $pluginFromFileSystem->getName(),
                'baseClass' => $baseClass,
                'composerName' => $info->getName(),
                'path' => new Filesystem()->makePathRelative($pluginPath, $this->projectDir),
                'author' => $this->getAuthors($info),
                'copyright' => $extra['copyright'] ?? null,
                'license' => implode(', ', $license),
                'version' => $pluginVersion,
                'iconRaw' => $this->getPluginIconRaw($pluginPath . '/' . $pluginIconPath),
                'autoload' => $info->getAutoload(),
                'managedByComposer' => $pluginFromFileSystem->getManagedByComposer(),
            ];

            $pluginData['translations'] = $this->getTranslations($contenaContext, $extra);

            $currentPluginEntity = $installedPlugins->filterByProperty('baseClass', $baseClass)->first();
            if ($currentPluginEntity !== null) {
                $currentPluginId = $currentPluginEntity->getId();
                $pluginData['id'] = $currentPluginId;

                $currentPluginVersion = $currentPluginEntity->getVersion();
                if (!$currentPluginEntity->getInstalledAt()) {
                    $pluginData['version'] = $pluginVersion;
                    $pluginData['upgradeVersion'] = null;
                } elseif ($this->hasPluginUpdate($pluginVersion, $currentPluginVersion)) {
                    $pluginData['version'] = $currentPluginVersion;
                    $pluginData['upgradeVersion'] = $pluginVersion;
                } else {
                    $pluginData['upgradeVersion'] = null;
                }

                $installedPlugins->remove($currentPluginId);
            }

            $plugins[] = $pluginData;
        }

        if ($plugins !== []) {
            foreach ($plugins as $plugin) {
                try {
                    $this->pluginRepo->upsert([$plugin], $contenaContext);
                } catch (ContenaHttpException $exception) {
                    $errors->set($plugin['name'], $exception);
                }
            }
        }

        // delete plugins, which are in storage but not in filesystem anymore
        $deletePluginIds = $installedPlugins->getIds();
        if ($deletePluginIds !== []) {
            $deletePlugins = [];
            foreach ($deletePluginIds as $deletePluginId) {
                $deletePlugins[] = ['id' => $deletePluginId];
            }
            $this->pluginRepo->delete($deletePlugins, $contenaContext);
        }

        return $errors;
    }

    /**
     * @throws PluginNotFoundException
     */
    public function getPluginByName(string $pluginName, Context $context): PluginEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', $pluginName));

        $pluginEntity = $this->getPlugins($criteria, $context)->first();
        if ($pluginEntity === null) {
            throw PluginException::notFound($pluginName);
        }

        return $pluginEntity;
    }

    private function getPlugins(Criteria $criteria, Context $context): PluginCollection
    {
        return $this->pluginRepo->search($criteria, $context)->getEntities();
    }

    private function hasPluginUpdate(string $updateVersion, string $currentVersion): bool
    {
        return version_compare($updateVersion, $currentVersion, '>');
    }

    private function getLanguageIdForLocale(string $locale, Context $context): string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('language.translationCode.code', $locale));
        $languageEntity = $this->languageRepo->search($criteria, $context)->getEntities()->first();

        if ($languageEntity === null) {
            return '';
        }

        return $languageEntity->getId();
    }

    private function getPluginIconRaw(string $pluginIconPath): ?string
    {
        if (!is_file($pluginIconPath)) {
            return null;
        }

        $rawContent = file_get_contents($pluginIconPath);

        if (!\is_string($rawContent)) {
            return null;
        }

        return $rawContent;
    }

    private function getAuthors(CompletePackageInterface $info): string
    {
        $composerAuthors = $info->getAuthors();

        $manufacturerAuthors = array_filter($composerAuthors, static fn (array $author): bool => ($author['role'] ?? '') === self::COMPOSER_AUTHOR_ROLE_MANUFACTURER);

        if ($manufacturerAuthors === []) {
            $manufacturerAuthors = $composerAuthors;
        }

        $authorNames = array_column($manufacturerAuthors, 'name');

        return implode(', ', $authorNames);
    }

    /**
     * @param array<string, mixed> $extra
     *
     * @return array<string, array<string, string>>
     */
    private function getTranslations(Context $context, array $extra): array
    {
        $properties = ['label', 'description', 'manufacturerLink', 'supportLink'];

        $localeMapping = [];
        $translations = [];

        /*
         * @example payload
         * {
         *     "contena-plugin-class":"Ct\\MyDemoData\\MyDemoData",
         *     "label":{
         *         "zh-CN":"MyDemoData plugin label in Chinese",
         *         "en-GB":"Label for the plugin MyDemoData"
         *     },
         *     "description":{
         *         "zh-CN":"MyDemoData plugin description in Chinese",
         *         "en-GB":"Description for the plugin MyDemoData"
         *     }
         * }
         */
        foreach ($extra as $property => $propertyTranslations) {
            if (!\in_array($property, $properties, true)) {
                continue;
            }

            foreach ($propertyTranslations as $locale => $translation) {
                $languageId = $this->getLanguageIdForLocale($locale, $context);

                // build a mapping based on locales, which is used for translation fallback later
                $localeMapping[$locale][$property] = $translation;

                if ($languageId === '') {
                    continue;
                }
                $translations[$languageId][$property] = $translation;
            }
        }

        // validate that the plugin is translated for the system language
        if (isset($translations[Defaults::LANGUAGE_SYSTEM])) {
            return $translations;
        }

        // if the plugin has no system translation, check if en-GB can be used as fallback
        if (isset($localeMapping['en-GB'])) {
            $translations[Defaults::LANGUAGE_SYSTEM] = $localeMapping['en-GB'];

            return $translations;
        }

        // if the plugin has no translation for en-gb, use the first translation of the plugin as default translation
        if (!isset($translations[Defaults::LANGUAGE_SYSTEM])) {
            $translations[Defaults::LANGUAGE_SYSTEM] = array_values($localeMapping)[0];
        }

        return $translations;
    }
}
