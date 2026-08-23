<?php declare(strict_types=1);

namespace Contena\Core\System\Snippet\Struct;

use GuzzleHttp\Psr7\Uri;
use Contena\Core\Framework\Plugin;
use Contena\Core\Framework\Struct\Struct;
use Contena\Core\System\Snippet\DataTransfer\Language\LanguageCollection;
use Contena\Core\System\Snippet\DataTransfer\PluginMapping\PluginMappingCollection;
use Contena\Core\System\Snippet\SnippetException;

class TranslationConfig extends Struct
{
    /**
     * @param list<string> $locales
     * @param list<string> $plugins
     * @param list<string> $excludedLocales
     * @param list<string> $pseudoLocales
     *
     * @internal
     */
    public function __construct(
        public readonly Uri $repositoryUrl,
        public readonly array $locales,
        public readonly array $plugins,
        public readonly LanguageCollection $languages,
        public readonly PluginMappingCollection $pluginMapping,
        public readonly Uri $metadataUrl,
        public readonly array $excludedLocales,
        public readonly ?Uri $communityTranslationsUrl = null,
        public readonly ?string $documentationUrlSnippetKey = null,
        public readonly array $pseudoLocales = [],
        public readonly int $completenessThreshold = 90,
    ) {
    }

    public function getMappedPluginName(Plugin $plugin): string
    {
        $pluginName = $plugin->getName();

        return $this->pluginMapping->get($pluginName)->snippetName ?? $pluginName;
    }

    /**
     * Asserts that the given locales are part of the translation set Contena is configured to offer (translation.yaml).
     *
     * @param list<string> $locales
     *
     * @throws SnippetException when no locales are given or a locale is not configured
     */
    public function assertLocalesAreConfigured(array $locales): void
    {
        if ($locales === []) {
            throw SnippetException::noLocalesArgumentProvided();
        }

        $invalid = array_values(array_diff($locales, $this->locales));
        if ($invalid === []) {
            return;
        }

        throw SnippetException::invalidLocalesProvided(
            implode(', ', $invalid),
            implode(', ', $this->locales)
        );
    }
}
