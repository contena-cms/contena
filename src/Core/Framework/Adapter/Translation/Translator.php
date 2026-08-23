<?php declare(strict_types=1);

namespace Contena\Core\Framework\Adapter\Translation;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\ConnectionException;
use Doctrine\DBAL\Exception\DriverException;
use Contena\Core\ChannelRequest;
use Contena\Core\Defaults;
use Contena\Core\Framework\Adapter\Cache\CacheTagCollector;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Locale\LanguageLocaleCodeProvider;
use Contena\Core\System\Locale\LocaleException;
use Contena\Core\System\Snippet\SnippetService;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\CacheWarmer\WarmableInterface;
use Symfony\Component\Intl\Locale;
use Symfony\Component\Translation\Formatter\MessageFormatterInterface;
use Symfony\Component\Translation\MessageCatalogueInterface;
use Symfony\Component\Translation\Translator as SymfonyTranslator;
use Symfony\Component\Translation\TranslatorBagInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Translation\LocaleAwareInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Contracts\Translation\TranslatorTrait;

class Translator extends AbstractTranslator
{
    use TranslatorTrait;

    public const ALL_CACHE_TAG = 'translation.catalog.all';

    /**
     * @var array<string, MessageCatalogueInterface>
     */
    private array $isCustomized = [];

    private ?string $snippetSetId = null;

    private ?string $channelId = null;

    private ?string $localeBeforeInject = null;

    /**
     * @var array<string, bool>
     */
    private array $keys = ['all' => true];

    /**
     * @var array<string, array<string, bool>>
     */
    private array $traces = [];

    /**
     * @var array<string, string>
     */
    private array $snippets = [];

    private readonly string $defaultLocale;

    /**
     * @var non-empty-list<string>
     */
    private array $defaultFallbackLocales = ['en_GB', 'en'];

    private ?string $languageId = null;

    /**
     * @internal
     */
    public function __construct(
        private readonly TranslatorInterface&TranslatorBagInterface&LocaleAwareInterface $translator,
        private readonly RequestStack $requestStack,
        private readonly CacheInterface $cache,
        private readonly MessageFormatterInterface $formatter,
        private readonly Connection $connection,
        private readonly LanguageLocaleCodeProvider $languageLocaleProvider,
        private readonly SnippetService $snippetService,
        private readonly CacheTagCollector $cacheTagCollector,
    ) {
        $this->defaultLocale = $translator->getLocale();

        if (!$translator instanceof SymfonyTranslator) {
            return;
        }

        $defaultFallbackLocales = array_filter(array_map(
            static fn (string $fallbackLocale): ?string => locale_canonicalize($fallbackLocale),
            $translator->getFallbackLocales()
        ));

        if ($defaultFallbackLocales !== []) {
            $this->defaultFallbackLocales = array_values($defaultFallbackLocales);
        }
    }

    public static function buildName(string $id): string
    {
        if (\strpbrk($id, ItemInterface::RESERVED_CHARACTERS) !== false) {
            $id = \str_replace(\str_split(ItemInterface::RESERVED_CHARACTERS, 1), '_r_', $id);
        }

        return 'translator.' . $id;
    }

    public function getDecorated(): AbstractTranslator
    {
        throw new DecorationPatternException(self::class);
    }

    public function trace(string $key, \Closure $param)
    {
        $this->traces[$key] = [];
        $this->keys[$key] = true;

        $result = $param();

        unset($this->keys[$key]);

        return $result;
    }

    public function getTrace(string $key): array
    {
        $trace = isset($this->traces[$key]) ? array_keys($this->traces[$key]) : [];
        unset($this->traces[$key]);

        return $trace;
    }

    /**
     * {@inheritdoc}
     */
    public function getCatalogue(?string $locale = null): MessageCatalogueInterface
    {
        $catalog = $this->translator->getCatalogue($locale);

        $fallbackLocale = $this->getFallbackLocale($catalog->getLocale());
        if ($this->isContenaLocaleCatalogue($catalog) && !$this->isFallbackLocaleCatalogue($catalog, $fallbackLocale)) {
            $catalog->addFallbackCatalogue($this->translator->getCatalogue($fallbackLocale));
        } else {
            /**
             * fallback locale and current locale has the same localization -> reset fallback
             * or locale is symfony style locale, so we shouldn't add contena fallbacks as it may lead to circular references
             */
            $fallbackLocale = null;
        }

        $this->addParentLanguageLocaleFallbacks($catalog);

        return $this->getCustomizedCatalogue($catalog, $fallbackLocale);
    }

    public static function tag(?string $id): string
    {
        return \sprintf('translator-%s', (string) $id);
    }

    /**
     * @param array<string, string> $parameters
     */
    public function trans(string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string
    {
        if ($domain === null) {
            $domain = 'messages';
        }

        $catalogue = $this->getCatalogue($locale);

        $this->cacheTagCollector->addTag(self::tag($this->snippetSetId));

        /**
         * The formatter expects 2 char locale or underscore locales, `Locale::getFallback()` transforms the codes
         * We use the locale from the catalogue here as that may be the fallback locale,
         * so we always format the translations in the actual locale of the catalogue
         */
        $formatLocale = Locale::getFallback($catalogue->getLocale()) ?? $catalogue->getLocale();

        while (!$catalogue->has($id, $domain) && $catalogue->getFallbackCatalogue() !== null) {
            $domain = 'frontend';
            $catalogue = $catalogue->getFallbackCatalogue();
        }

        return $this->formatter->format($catalogue->get($id, $domain), $formatLocale, $parameters);
    }

    /**
     * {@inheritdoc}
     */
    public function setLocale(string $locale): void
    {
        $this->translator->setLocale($locale);
    }

    /**
     * {@inheritdoc}
     */
    public function getLocale(): string
    {
        return $this->translator->getLocale();
    }

    public function warmUp($cacheDir): void
    {
        if ($this->translator instanceof WarmableInterface) {
            $this->translator->warmUp($cacheDir);
        }
    }

    public function reset(): void
    {
        $this->resetInjection();

        $this->isCustomized = [];
        $this->snippets = [];
        $this->traces = [];
        $this->keys = ['all' => true];
        $this->snippetSetId = null;
        $this->channelId = null;
        $this->localeBeforeInject = null;
        $this->locale = null;
        $this->translator->setLocale($this->defaultLocale);
        $this->languageId = null;
        if ($this->translator instanceof SymfonyTranslator) {
            // Reset FallbackLocale in memory cache of symfony implementation
            // set fallback values from Framework/Resources/config/translation.yaml
            $this->translator->setFallbackLocales($this->defaultFallbackLocales);
        }
    }

    /**
     * Injects temporary settings for translation which differ from Context.
     * Call resetInjection() when specific translation is done
     */
    public function injectSettings(string $channelId, string $languageId, string $locale, Context $context): void
    {
        $this->localeBeforeInject = $this->getLocale();
        $this->channelId = $channelId;
        $this->setLocale($locale);
        $this->resolveSnippetSetId($channelId, $languageId, $locale);
        $this->languageId = $languageId;
        $this->getCatalogue($locale);
    }

    public function resetInjection(): void
    {
        if ($this->localeBeforeInject === null) {
            // Nothing was injected, so no need to reset
            return;
        }

        $this->setLocale($this->localeBeforeInject);
        $this->snippetSetId = null;
        $this->channelId = null;
        $this->languageId = null;
    }

    public function getSnippetSetId(?string $locale = null): ?string
    {
        $snippetSetId = $this->snippetSetId;
        $currentRequest = $this->requestStack->getMainRequest();

        // when document is rendered from admin, ChannelRequest::ATTRIBUTE_DOMAIN_SNIPPET_SET_ID is not set thus we use snippetSetId from injectSetting method
        if ($currentRequest !== null && $currentRequest->attributes->has(ChannelRequest::ATTRIBUTE_DOMAIN_SNIPPET_SET_ID)) {
            $snippetSetId = $currentRequest->attributes->get(ChannelRequest::ATTRIBUTE_DOMAIN_SNIPPET_SET_ID);
        }

        if ($locale === null) {
            return $this->snippetSetId = $snippetSetId;
        }
        // If locale parameter is using, prioritize it over snippet set of request
        if (\array_key_exists($locale, $this->snippets)) {
            return $this->snippets[$locale];
        }

        // get snippet set by locale but in case there are more than one sets with a same locale, we should prioritize the domain's snippet set
        $snippetSetIds = $this->connection->fetchFirstColumn('SELECT LOWER(HEX(id)) FROM snippet_set WHERE iso = :iso', ['iso' => $locale]);

        if ($snippetSetIds !== []) {
            $snippetSetId = \in_array($snippetSetId, $snippetSetIds, true) ? $snippetSetId : $snippetSetIds[0];
        }

        $this->snippets[$locale] = $snippetSetId;

        return $this->snippetSetId = $snippetSetId;
    }

    /**
     * @return list<MessageCatalogueInterface>
     */
    public function getCatalogues(): array
    {
        return array_values($this->isCustomized);
    }

    private function isFallbackLocaleCatalogue(MessageCatalogueInterface $catalog, string $fallbackLocale): bool
    {
        return mb_strpos($catalog->getLocale(), $fallbackLocale) === 0;
    }

    /**
     * Contena uses dashes in all locales.
     * If the catalogue does not contain any dashes, it means it is a symfony fallback catalogue,
     * in that case we should not add the contena fallback catalogue as it would result in circular references
     */
    private function isContenaLocaleCatalogue(MessageCatalogueInterface $catalog): bool
    {
        return mb_strpos($catalog->getLocale(), '-') !== false;
    }

    private function resolveSnippetSetId(string $channelId, string $languageId, string $locale): void
    {
        $snippetSetId = $this->snippetService->findSnippetSetId($channelId, $languageId, $locale);

        $this->snippetSetId = $snippetSetId;
    }

    /**
     * Add country-specific snippets provided by the admin
     */
    private function getCustomizedCatalogue(MessageCatalogueInterface $catalogue, ?string $fallbackLocale): MessageCatalogueInterface
    {
        try {
            $snippetSetId = $this->getSnippetSetId($catalogue->getLocale());
        } catch (DriverException) {
            // this allows us to use the translator even if there's no db connection yet
            return $catalogue;
        }

        if (!$snippetSetId) {
            return $catalogue;
        }

        if (\array_key_exists($snippetSetId, $this->isCustomized)) {
            return $this->isCustomized[$snippetSetId];
        }

        $newCatalogue = $this->buildMergedCatalogue($catalogue, $snippetSetId);

        return $this->isCustomized[$snippetSetId] = $newCatalogue;
    }

    /**
     * @return array<string, string>
     */
    private function loadSnippets(MessageCatalogueInterface $catalog, string $snippetSetId, ?string $fallbackLocale): array
    {
        $this->resolveChannelId();

        $effectiveLocale = $fallbackLocale ?? $catalog->getLocale();
        $keySuffix = $effectiveLocale ? '-' . $effectiveLocale : '';
        $key = \sprintf('translation.catalog.%s.%s', $this->channelId ?: 'DEFAULT', $snippetSetId . $keySuffix);

        return $this->cache->get($key, function (ItemInterface $item) use ($catalog, $snippetSetId, $effectiveLocale) {
            $item->tag(self::ALL_CACHE_TAG);
            $item->tag(self::tag($snippetSetId));
            $item->tag(self::tag($this->channelId ?: 'DEFAULT'));

            return $this->snippetService->getFrontendSnippets($catalog, $snippetSetId, $effectiveLocale, $this->channelId);
        });
    }

    private function getFallbackLocale(?string $locale): string
    {
        // Try to use configured language inheritance from ChannelContext
        $fallbackFromInheritance = $this->getFallbackLocaleFromLanguageInheritance();
        if ($fallbackFromInheritance !== null) {
            return $fallbackFromInheritance;
        }

        // Fallback to locale prefix extraction (original behavior)
        if ($locale) {
            return explode('-', $locale)[0];
        }

        try {
            return $this->languageLocaleProvider->getLanguageLocalePrefix(Defaults::LANGUAGE_SYSTEM);
        } catch (ConnectionException|LocaleException) {
            // this allows us to use the translator even if there's no db connection or locale yet
            return 'en';
        }
    }

    /**
     * Gets the fallback locale from the configured language inheritance chain.
     *
     * When a language is configured with a parent language (e.g., Spanish inherits from English),
     * this method returns the parent language's locale as the fallback, instead of just using
     * the locale prefix (e.g., "es" from "es-ES").
     *
     * This ensures that snippet translations respect the language inheritance configured
     * in the admin under Settings > Languages.
     */
    private function getFallbackLocaleFromLanguageInheritance(): ?string
    {
        if ($this->languageId !== null) {
            return $this->languageLocaleProvider->getParentLanguageLocalesForLanguageId($this->languageId)[0] ?? null;
        }

        $request = $this->requestStack->getMainRequest();
        $channelContext = $request?->attributes->get(PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT);

        if (!$channelContext instanceof ChannelContext) {
            return null;
        }

        $languageIdChain = $channelContext->getLanguageIdChain();

        // If there's no parent language in the chain, return null to use default behavior
        if (\count($languageIdChain) < 2) {
            return null;
        }

        try {
            return $this->languageLocaleProvider->getLocaleForLanguageId($languageIdChain[1]);
        } catch (LocaleException) {
            return null;
        }
    }

    private function resolveChannelId(): void
    {
        if ($this->channelId !== null) {
            return;
        }

        $request = $this->requestStack->getMainRequest();

        if (!$request) {
            return;
        }

        $this->channelId = $request->attributes->get(PlatformRequest::ATTRIBUTE_CHANNEL_ID);
    }

    private function resolveLanguageId(): void
    {
        if ($this->languageId !== null) {
            return;
        }

        $request = $this->requestStack->getMainRequest();

        if (!$request) {
            return;
        }

        $context = $request->attributes->get(PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT);

        if (!$context instanceof ChannelContext) {
            return;
        }

        $this->languageId = $context->getLanguageId();
    }

    private function addParentLanguageLocaleFallbacks(MessageCatalogueInterface $catalogue): void
    {
        $this->resolveLanguageId();
        $parentLanguageLocales = $this->languageId !== null
            ? $this->languageLocaleProvider->getParentLanguageLocalesForLanguageId($this->languageId)
            : [];
        $existingLocales = [];
        $currentCatalogue = $catalogue;
        do {
            $existingLocales[] = $currentCatalogue->getLocale();
        } while ($currentCatalogue = $currentCatalogue->getFallbackCatalogue());

        $parentLanguageLocales = array_diff($parentLanguageLocales, $existingLocales);

        foreach ($parentLanguageLocales as $parentLanguageLocale) {
            $catalogue->addFallbackCatalogue($this->translator->getCatalogue($parentLanguageLocale));
        }
    }

    private function buildMergedCatalogue(MessageCatalogueInterface $catalogue, string $snippetSetId): MessageCatalogueInterface
    {
        $newCatalogue = clone $catalogue;

        // Recursively loading snippets for each catalogue in the fallback chain.
        // Each catalogue loads its own locale's snippets (e.g., es-ES loads Spanish, en-GB loads English).
        // The language inheritance fallback is handled by the catalogue chain itself,
        // which is set up in getCatalogue() based on the configured parent language.
        $currentCatalogue = $newCatalogue;
        do {
            $loadedSnippets = $this->loadSnippets($currentCatalogue, $snippetSetId, null);

            if ($loadedSnippets !== []) {
                $currentCatalogue->add($loadedSnippets);
            }
        } while ($currentCatalogue = $currentCatalogue->getFallbackCatalogue());

        return $newCatalogue;
    }
}
