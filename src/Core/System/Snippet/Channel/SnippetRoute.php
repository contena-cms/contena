<?php declare(strict_types=1);

namespace Contena\Core\System\Snippet\Channel;

use Doctrine\DBAL\Connection;
use Contena\Core\Framework\Adapter\Cache\CacheTagCollector;
use Contena\Core\Framework\Adapter\Translation\AbstractTranslator;
use Contena\Core\Framework\Adapter\Translation\Translator;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\Framework\Routing\ChannelApiRouteScope;
use Contena\Core\Framework\Util\Hasher;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Locale\LanguageLocaleCodeProvider;
use Contena\Core\System\Snippet\SnippetException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Translation\MessageCatalogueInterface;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ChannelApiRouteScope::ID]])]
class SnippetRoute extends AbstractSnippetRoute
{
    final public const MAX_PREFIXES = 50;

    /**
     * @internal
     */
    public function __construct(
        private readonly AbstractTranslator $translator,
        private readonly LanguageLocaleCodeProvider $languageLocaleProvider,
        private readonly Connection $connection,
        private readonly CacheTagCollector $cacheTagCollector,
    ) {
    }

    #[Route(
        path: '/channel-api/snippet',
        name: 'channel-api.snippet',
        methods: [Request::METHOD_GET],
        defaults: [PlatformRequest::ATTRIBUTE_HTTP_CACHE => true],
    )]
    public function load(Request $request, ChannelContext $context): SnippetRouteResponse
    {
        $prefixes = $this->normalizePrefixes($this->parseList($request->query->getString('prefixes')));
        if (\count($prefixes) > self::MAX_PREFIXES) {
            throw SnippetException::tooManyPrefixes(\count($prefixes), self::MAX_PREFIXES);
        }

        $languageIds = $this->parseList($request->query->getString('languageIds'));
        $languageIds = array_values(array_unique(array_map('mb_strtolower', $languageIds)));
        sort($languageIds);

        if ($languageIds !== []) {
            $this->validateLanguages($languageIds, $context->getChannelId());
        } else {
            $languageIds = [$context->getLanguageId()];
        }

        $results = [];
        foreach ($languageIds as $languageId) {
            $results[] = $this->loadForLanguage($languageId, $prefixes, $context);
        }

        $response = new SnippetRouteResponse(new SnippetSetResultList($results));

        $etag = \count($results) === 1
            ? $results[0]->hash
            : Hasher::hash(implode('-', array_map(static fn (SnippetSetResult $result): string => $result->hash, $results)));

        $response->setEtag($etag);
        $response->isNotModified($request);

        return $response;
    }

    protected function getDecorated(): AbstractSnippetRoute
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @param list<string> $prefixes
     */
    private function loadForLanguage(string $languageId, array $prefixes, ChannelContext $context): SnippetSetResult
    {
        $locale = $this->languageLocaleProvider->getLocaleForLanguageId($languageId);

        $this->translator->injectSettings($context->getChannelId(), $languageId, $locale, $context->getContext());

        try {
            $catalogue = $this->translator->getCatalogue($locale);
            $snippetSetId = $this->translator->getSnippetSetId($locale);
        } finally {
            $this->translator->resetInjection();
        }

        $this->cacheTagCollector->addTag(Translator::tag($snippetSetId));

        $snippets = $this->flattenCatalogue($catalogue);

        if ($prefixes !== []) {
            $snippets = array_filter(
                $snippets,
                static fn (string $key): bool => self::matchesAnyPrefix($key, $prefixes),
                \ARRAY_FILTER_USE_KEY
            );
        }

        ksort($snippets);

        $fallbackLocale = explode('-', $locale)[0];

        return new SnippetSetResult(
            languageId: $languageId,
            locale: $locale,
            fallbackLocale: $fallbackLocale !== $locale ? $fallbackLocale : null,
            snippetSetId: $snippetSetId,
            hash: Hasher::hash($snippets),
            snippets: $snippets,
        );
    }

    /**
     * Collects the messages of the whole catalogue fallback chain, most specific locale wins
     *
     * @return array<string, string>
     */
    private function flattenCatalogue(MessageCatalogueInterface $catalogue): array
    {
        $catalogues = [];
        $current = $catalogue;
        while ($current !== null) {
            $catalogues[] = $current;
            $current = $current->getFallbackCatalogue();
        }

        $snippets = [];
        foreach (array_reverse($catalogues) as $chainCatalogue) {
            $snippets = array_replace($snippets, $chainCatalogue->all('messages'));
        }

        return $snippets;
    }

    /**
     * @return list<string>
     */
    private function parseList(string $value): array
    {
        return array_values(array_filter(
            array_map('trim', explode(',', $value)),
            static fn (string $item): bool => $item !== ''
        ));
    }

    /**
     * Prefixes are matched on namespace segments, so a trailing dot is optional: `checkout` and `checkout.`
     * both match `checkout.cart.title` but never `checkoutConfirm.title`
     *
     * @param list<string> $prefixes
     *
     * @return list<string>
     */
    private function normalizePrefixes(array $prefixes): array
    {
        $prefixes = array_values(array_unique(array_filter(
            array_map(static fn (string $prefix): string => rtrim($prefix, '.'), $prefixes),
            static fn (string $prefix): bool => $prefix !== ''
        )));

        sort($prefixes);

        return $prefixes;
    }

    /**
     * @param list<string> $prefixes
     */
    private static function matchesAnyPrefix(string $key, array $prefixes): bool
    {
        foreach ($prefixes as $prefix) {
            if ($key === $prefix || str_starts_with($key, $prefix . '.')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<string> $languageIds
     */
    private function validateLanguages(array $languageIds, string $channelId): void
    {
        foreach ($languageIds as $languageId) {
            if (!Uuid::isValid($languageId)) {
                throw SnippetException::languageNotAvailableInChannel($languageId, $channelId);
            }
        }

        /** @var list<string> $availableLanguageIds */
        $availableLanguageIds = $this->connection->fetchFirstColumn(
            'SELECT LOWER(HEX(`language_id`)) FROM `channel_language` WHERE `channel_id` = :channelId',
            ['channelId' => Uuid::fromHexToBytes($channelId)]
        );

        foreach ($languageIds as $languageId) {
            if (!\in_array($languageId, $availableLanguageIds, true)) {
                throw SnippetException::languageNotAvailableInChannel($languageId, $channelId);
            }
        }
    }
}
