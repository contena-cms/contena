<?php declare(strict_types=1);

namespace Contena\Core\Framework\Test\TestCaseBase;

use Contena\Core\Content\Category\CategoryCollection;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Contena\Core\System\Country\CountryCollection;
use Contena\Core\System\Language\LanguageCollection;
use Contena\Core\System\Snippet\Aggregate\SnippetSet\SnippetSetCollection;
use Symfony\Component\DependencyInjection\ContainerInterface;

trait BasicTestDataBehaviour
{
    public function getDeDeLanguageId(): string
    {
        /** @var EntityRepository<LanguageCollection> $repository */
        $repository = static::getContainer()->get('language.repository');

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('language.translationCode.code', 'de-DE'));

        $id = $repository->searchIds($criteria, Context::createDefaultContext())->firstId();
        \assert($id !== null);

        return $id;
    }

    abstract protected static function getContainer(): ContainerInterface;

    protected function getLocaleIdOfSystemLanguage(): string
    {
        /** @var EntityRepository<LanguageCollection> $repository */
        $repository = static::getContainer()->get('language.repository');

        $language = $repository->search(new Criteria([Defaults::LANGUAGE_SYSTEM]), Context::createDefaultContext())->getEntities()->first();
        \assert($language !== null);

        return $language->getLocaleId();
    }

    protected function getSnippetSetIdForLocale(string $locale): ?string
    {
        /** @var EntityRepository<SnippetSetCollection> $repository */
        $repository = static::getContainer()->get('snippet_set.repository');

        $criteria = new Criteria()
            ->addFilter(new EqualsFilter('iso', $locale))
            ->setLimit(1);

        return $repository->searchIds($criteria, Context::createDefaultContext())->firstId();
    }

    protected function getValidCountryId(?string $unusedScopeId = null): string
    {
        /** @var EntityRepository<CountryCollection> $repository */
        $repository = static::getContainer()->get('country.repository');

        $criteria = new Criteria()
            ->setLimit(1)
            ->addFilter(new EqualsFilter('active', true))
            ->addSorting(new FieldSorting('iso'));

        $id = $repository->searchIds($criteria, Context::createDefaultContext())->firstId();
        \assert($id !== null);

        return $id;
    }

    protected function getCountryIdByIsoCode(string $isoCode): string
    {
        /** @var EntityRepository<CountryCollection> $repository */
        $repository = static::getContainer()->get('country.repository');

        $criteria = new Criteria()
            ->setLimit(1)
            ->addFilter(new EqualsFilter('iso', $isoCode));

        $id = $repository->searchIds($criteria, Context::createDefaultContext())->firstId();
        \assert($id !== null);

        return $id;
    }

    protected function getDeCountryId(): string
    {
        return $this->getCountryIdByIsoCode('DE');
    }

    protected function getValidCategoryId(): string
    {
        /** @var EntityRepository<CategoryCollection> $repository */
        $repository = static::getContainer()->get('category.repository');

        $criteria = new Criteria()
            ->setLimit(1)
            ->addSorting(new FieldSorting('level'), new FieldSorting('name'));

        $id = $repository->searchIds($criteria, Context::createDefaultContext())->firstId();
        \assert($id !== null);

        return $id;
    }
}
