<?php declare(strict_types=1);

namespace Contena\Administration\Snippet;

use Contena\Core\Framework\Adapter\Cache\CacheInvalidator;
use Contena\Core\Framework\App\AppEntity;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Locale\LocaleCollection;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
readonly class AppAdministrationSnippetPersister
{
    /**
     * @param EntityRepository<AppAdministrationSnippetCollection> $appAdministrationSnippetRepository
     * @param EntityRepository<LocaleCollection> $localeRepository
     */
    public function __construct(
        private EntityRepository $appAdministrationSnippetRepository,
        private EntityRepository $localeRepository,
        private CacheInvalidator $cacheInvalidator,
        private Filesystem $filesystem,
    ) {
    }

    /**
     * @param array<string, string> $snippets
     */
    public function updateSnippets(AppEntity $app, array $snippets, Context $context): void
    {
        $newOrUpdatedSnippets = [];
        $existingAppSnippets = $this->getExistingAppSnippets($app->getId(), $context);
        $coreSnippets = $this->getCoreAdministrationSnippets();
        $firstLevelSnippetKeys = [];
        foreach ($snippets as $snippetString) {
            $decodedSnippets = json_decode($snippetString, true, 512, \JSON_THROW_ON_ERROR);
            $firstLevelSnippetKeys = array_keys($decodedSnippets);
        }

        if ($duplicatedKeys = array_values(array_intersect(array_keys($coreSnippets), $firstLevelSnippetKeys))) {
            throw SnippetException::extendOrOverwriteCore($duplicatedKeys);
        }
        if (!\array_key_exists('en-GB', $snippets) && $snippets !== []) {
            throw SnippetException::defaultLanguageNotGiven('en-GB');
        }

        $localeCodeToIdMapping = $this->mapLocaleCodesToIds(array_keys($snippets), $context);
        $existingLocales = [];
        foreach ($existingAppSnippets as $snippetEntity) {
            $existingLocales[$snippetEntity->getLocaleId()] = $snippetEntity->getId();
        }

        foreach ($snippets as $snippetLocale => $snippet) {
            if (!\array_key_exists($snippetLocale, $localeCodeToIdMapping)) {
                continue;
            }
            $localeId = $localeCodeToIdMapping[$snippetLocale];
            $id = $existingLocales[$localeId] ?? Uuid::randomHex();
            unset($existingLocales[$localeId]);
            $newOrUpdatedSnippets[] = ['id' => $id, 'value' => $snippet, 'appId' => $app->getId(), 'localeId' => $localeId];
        }

        $this->appAdministrationSnippetRepository->upsert($newOrUpdatedSnippets, $context);
        $this->deleteSnippets(array_values($existingLocales), $context);
        $this->cacheInvalidator->invalidate([CachedSnippetFinder::CACHE_TAG], true);
    }

    private function getExistingAppSnippets(string $appId, Context $context): AppAdministrationSnippetCollection
    {
        return $this->appAdministrationSnippetRepository->search(
            new Criteria()->addFilter(new EqualsFilter('appId', $appId)),
            $context,
        )->getEntities();
    }

    /**
     * @return array<string, mixed>
     */
    private function getCoreAdministrationSnippets(): array
    {
        $path = __DIR__ . '/../Resources/app/administration/src/app/snippet/en.json';
        try {
            $snippets = $this->filesystem->readFile($path);
        } catch (IOException) {
            return [];
        }

        return json_decode($snippets, true, 512, \JSON_THROW_ON_ERROR);
    }

    /**
     * @param list<string> $ids
     */
    private function deleteSnippets(array $ids, Context $context): void
    {
        $this->appAdministrationSnippetRepository->delete(array_map(static fn (string $id): array => ['id' => $id], $ids), $context);
    }

    /**
     * @param list<string> $localeCodes
     *
     * @return array<string, string>
     */
    private function mapLocaleCodesToIds(array $localeCodes, Context $context): array
    {
        $locales = $this->localeRepository->search(
            new Criteria()->addFilter(new EqualsAnyFilter('code', $localeCodes))->addFields(['id', 'code']),
            $context,
        )->getEntities()->getElements();

        return array_column($locales, 'id', 'code');
    }
}
