<?php declare(strict_types=1);

namespace Contena\Core\System\Snippet\Files;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\StorageAttributes;
use Contena\Core\Framework\Bundle;
use Contena\Core\Framework\Plugin;
use Contena\Core\Kernel;
use Contena\Core\System\Snippet\Service\AbstractTranslationLoader;
use Contena\Core\System\Snippet\Struct\TranslationConfig;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\Finder;

/**
 * @description Loads frontend snippet files from the core and plugins into a SnippetFileCollection.
 */
class SnippetFileLoader implements SnippetFileLoaderInterface
{
    public const SCOPE_PLATFORM = 'Platform';

    public const SCOPE_PLUGINS = 'Plugins';

    private const ADMINISTRATION_BUNDLE_NAME = 'Administration';

    /**
     * @internal
     */
    public function __construct(
        private readonly Kernel $kernel,
        private readonly Connection $connection,
        private readonly TranslationConfig $config,
        private readonly AbstractTranslationLoader $translationLoader,
        private readonly FilesystemOperator $translationReader,
    ) {
    }

    public function loadSnippetFilesIntoCollection(SnippetFileCollection $snippetFileCollection): void
    {
        // Load snippets from private translation system
        $this->loadTranslationSnippets($snippetFileCollection);
        // Load snippets from Contena bundles and plugins
        $this->loadShippedSnippets($snippetFileCollection);
    }

    private function loadTranslationSnippets(SnippetFileCollection $snippetFileCollection): void
    {
        $exclude = $this->getInactivePluginNames();

        $localesBasePath = \mb_ltrim($this->translationLoader->getLocalesBasePath(), '/\\');

        // regular expression template that can be used for filtering or matching path parts
        $translationPathRegexpTemplate = '#^/?'
            . Path::join($localesBasePath, '(?P<locale>[a-zA-Z-0-9-_]+)', '(?P<component>%s)', '(?P<plugin>%s)')
            . '.*$#';

        $excludedPathsRegexp = array_map(
            static fn (string $path) => \sprintf($translationPathRegexpTemplate, self::SCOPE_PLUGINS, $path),
            $exclude
        );

        $excludedLocalesPattern = $this->getExcludedLocalesPatternFromConfig($localesBasePath);
        if ($excludedLocalesPattern !== null) {
            $excludedPathsRegexp[] = $excludedLocalesPattern;
        }

        $translationFiles = $this->translationReader
            ->listContents($localesBasePath, true)
            ->filter(static fn (StorageAttributes $node) => $node->isFile())
            ->filter(static fn (StorageAttributes $node) => \str_ends_with($node->path(), '.json'))
            ->filter(static fn (StorageAttributes $node) => \preg_filter($excludedPathsRegexp, 'EXCLUDED', $node->path()) !== 'EXCLUDED');

        $isPluginPathCheckRegexp = \sprintf($translationPathRegexpTemplate, self::SCOPE_PLATFORM . '|' . self::SCOPE_PLUGINS, '');
        foreach ($translationFiles as $translationFile) {
            \preg_match($isPluginPathCheckRegexp, $translationFile->path(), $pathComponents);

            // Check if the path matches the expected structure. If not, the directory was modified and the file should be skipped.
            $validityCheck = \array_intersect_key($pathComponents, array_fill_keys(['locale', 'component'], true));
            if (\count($validityCheck) !== 2 || $pathComponents['locale'] === '' || $pathComponents['component'] === '') {
                continue;
            }

            $technicalName = self::SCOPE_PLATFORM;
            if ($pathComponents['component'] === self::SCOPE_PLUGINS) {
                $technicalName = self::SCOPE_PLUGINS;
            }

            $fileInfo = new \SplFileInfo($translationFile->path());
            $fileName = $fileInfo->getBasename('.' . $fileInfo->getExtension());
            $isBase = str_contains($fileName, 'messages');

            if ($isBase) {
                $fileName = 'messages.' . $pathComponents['locale'];
            }

            $snippetFile = new RemoteSnippetFile(
                $fileName,
                $fileInfo->getPathname(),
                $pathComponents['locale'],
                'Contena',
                $isBase,
                $technicalName,
            );

            $snippetFileCollection->add($snippetFile);
        }
    }

    /**
     * @return array<int<0, max>, string>
     */
    private function getInactivePluginNames(): array
    {
        $plugins = $this->kernel->getPluginLoader()->getPluginInstances()->getActives();

        $activeNames = [];
        foreach ($plugins as $plugin) {
            $activeNames[] = $this->config->getMappedPluginName($plugin);
        }

        return array_diff($this->config->plugins, $activeNames);
    }

    private function loadShippedSnippets(SnippetFileCollection $snippetFileCollection): void
    {
        try {
            /** @var array<string, string> $authors */
            $authors = $this->connection->fetchAllKeyValue('
                SELECT `base_class` AS `baseClass`, `author`
                FROM `plugin`
            ');
        } catch (Exception) {
            // to get it working in setup without a database connection
            $authors = [];
        }

        foreach ($this->kernel->getBundles() as $name => $bundle) {
            // skip Administration bundle because we are in the frontend scope
            if (!$bundle instanceof Bundle || $name === self::ADMINISTRATION_BUNDLE_NAME) {
                continue;
            }

            $snippetDir = $bundle->getPath() . '/Resources/snippet';

            if (!is_dir($snippetDir)) {
                continue;
            }

            foreach ($this->loadSnippetFilesInDir($snippetDir, $bundle, $authors) as $snippetFile) {
                if ($snippetFileCollection->hasFileForPath($snippetFile->getPath())) {
                    continue;
                }

                // skip plugin file if a core translation for this specific locale already exists
                if (
                    $bundle instanceof Plugin
                    && $this->translationLoader->pluginTranslationExistsForLocale($bundle, $snippetFile->getIso())
                ) {
                    continue;
                }

                $snippetFileCollection->add($snippetFile);
            }
        }
    }

    /**
     * @param array<string, string> $authors
     *
     * @return AbstractSnippetFile[]
     */
    private function loadSnippetFilesInDir(string $snippetDir, Bundle $bundle, array $authors): array
    {
        $finder = new Finder();
        $finder->in($snippetDir)
            ->files()
            ->name('*.json');

        $snippetFiles = [];

        foreach ($finder->getIterator() as $fileInfo) {
            $nameParts = explode('.', $fileInfo->getFilenameWithoutExtension());

            $snippetFile = null;
            switch (\count($nameParts)) {
                case 2:
                    $snippetFile = new GenericSnippetFile(
                        implode('.', $nameParts),
                        $fileInfo->getPathname(),
                        $nameParts[1],
                        $this->getAuthorFromBundle($bundle, $authors),
                        false,
                        $bundle->getName(),
                    );

                    break;
                case 3:
                    $snippetFile = new GenericSnippetFile(
                        implode('.', [$nameParts[0], $nameParts[1]]),
                        $fileInfo->getPathname(),
                        $nameParts[1],
                        $this->getAuthorFromBundle($bundle, $authors),
                        $nameParts[2] === 'base',
                        $bundle->getName(),
                    );

                    break;
            }

            if ($snippetFile) {
                $snippetFiles[] = $snippetFile;
            }
        }

        return $snippetFiles;
    }

    /**
     * @param array<string, string> $authors
     */
    private function getAuthorFromBundle(Bundle $bundle, array $authors): string
    {
        if (!$bundle instanceof Plugin) {
            return 'Contena';
        }

        return $authors[$bundle::class] ?? '';
    }

    private function getExcludedLocalesPatternFromConfig(string $path): ?string
    {
        $excludedLocales = $this->config->excludedLocales;

        if ($excludedLocales === []) {
            return null;
        }

        $localePattern = implode('|', $excludedLocales);

        return '#^/?' . Path::join($path, '(' . $localePattern . ')', '*') . '.*$#';
    }
}
