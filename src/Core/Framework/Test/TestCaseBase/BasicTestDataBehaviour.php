<?php declare(strict_types=1);

namespace Contena\Core\Framework\Test\TestCaseBase;

use Doctrine\DBAL\Connection;
use Contena\Core\Content\Category\CategoryCollection;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Country\CountryCollection;
use Contena\Core\System\Language\LanguageCollection;
use Contena\Core\System\Snippet\Aggregate\SnippetSet\SnippetSetCollection;
use Contena\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateCollection;
use Contena\Core\System\Tax\TaxCollection;
use Contena\Core\Test\TestDefaults;
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

    protected function getValidPaymentMethodId(?string $channelId = null): string
    {
        /** @var EntityRepository<PaymentMethodCollection> $repository */
        $repository = static::getContainer()->get('payment_method.repository');

        $criteria = (new Criteria())
            ->setLimit(1)
            ->addFilter(new EqualsFilter('active', true));

        if ($channelId) {
            $criteria->addFilter(new EqualsFilter('channels.id', $channelId));
        }

        $id = $repository->searchIds($criteria, Context::createDefaultContext())->firstId();
        \assert($id !== null);

        return $id;
    }

    protected function getInactivePaymentMethodId(?string $channelId = null): string
    {
        /** @var EntityRepository<PaymentMethodCollection> $repository */
        $repository = static::getContainer()->get('payment_method.repository');

        $criteria = (new Criteria())
            ->setLimit(1)
            ->addFilter(new EqualsFilter('active', false));

        if ($channelId) {
            $criteria->addFilter(new EqualsFilter('channels.id', $channelId));
        }

        $id = $repository->searchIds($criteria, Context::createDefaultContext())->firstId();
        \assert($id !== null);

        return $id;
    }


    protected function getValidShippingMethodId(?string $channelId = null): string
    {
        /** @var EntityRepository<ShippingMethodCollection> $repository */
        $repository = static::getContainer()->get('shipping_method.repository');

        $criteria = (new Criteria())
            ->setLimit(1)
            ->addFilter(new EqualsFilter('active', true))
            ->addSorting(new FieldSorting('name'));

        if ($channelId) {
            $criteria->addFilter(new EqualsFilter('channels.id', $channelId));
        }

        $id = $repository->searchIds($criteria, Context::createDefaultContext())->firstId();
        \assert($id !== null);

        return $id;
    }


    protected function getValidSalutationId(): string
    {
        /** @var EntityRepository<SalutationCollection> $repository */
        $repository = static::getContainer()->get('salutation.repository');

        $criteria = (new Criteria())
            ->setLimit(1)
            ->addSorting(new FieldSorting('salutationKey'));

        $id = $repository->searchIds($criteria, Context::createDefaultContext())->firstId();
        \assert($id !== null);

        return $id;
    }

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

        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('iso', $locale))
            ->setLimit(1);

        return $repository->searchIds($criteria, Context::createDefaultContext())->firstId();
    }

    /**
     * @param string|null $channelId (null when no saleschannel filtering)
     */
    protected function getValidCountryId(?string $channelId = TestDefaults::CHANNEL): string
    {
        /** @var EntityRepository<CountryCollection> $repository */
        $repository = static::getContainer()->get('country.repository');

        $criteria = (new Criteria())->setLimit(1)
            ->addFilter(new EqualsFilter('active', true))
            ->addFilter(new EqualsFilter('shippingAvailable', true))
            ->addSorting(new FieldSorting('iso'));

        if ($channelId !== null) {
            $criteria->addFilter(new EqualsFilter('channels.id', $channelId));
        }

        $id = $repository->searchIds($criteria, Context::createDefaultContext())->firstId();
        \assert($id !== null);

        return $id;
    }

    protected function getCountryIdByIsoCode(string $isoCode): string
    {
        /** @var EntityRepository<CountryCollection> $repository */
        $repository = static::getContainer()->get('country.repository');

        $criteria = (new Criteria())->setLimit(1)
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

        $criteria = (new Criteria())
            ->setLimit(1)
            ->addSorting(new FieldSorting('level'), new FieldSorting('name'));

        $id = $repository->searchIds($criteria, Context::createDefaultContext())->firstId();
        \assert($id !== null);

        return $id;
    }

    protected function getValidTaxId(): string
    {
        /** @var EntityRepository<TaxCollection> $repository */
        $repository = static::getContainer()->get('tax.repository');

        $criteria = (new Criteria())
            ->setLimit(1)
            ->addSorting(new FieldSorting('name'));

        $id = $repository->searchIds($criteria, Context::createDefaultContext())->firstId();
        \assert($id !== null);

        return $id;
    }

    protected function getValidDocumentTypeId(): string
    {
        /** @var EntityRepository<DocumentTypeCollection> $repository */
        $repository = static::getContainer()->get('document_type.repository');

        $criteria = (new Criteria())
            ->setLimit(1)
            ->addSorting(new FieldSorting('technicalName'));

        $id = $repository->searchIds($criteria, Context::createDefaultContext())->firstId();
        \assert($id !== null);

        return $id;
    }

    protected function getStateMachineState(string $stateMachine = OrderStates::STATE_MACHINE, string $state = OrderStates::STATE_OPEN): string
    {
        /** @var EntityRepository<StateMachineStateCollection> $repository */
        $repository = static::getContainer()->get('state_machine_state.repository');

        $criteria = new Criteria();
        $criteria
            ->setLimit(1)
            ->addFilter(new EqualsFilter('technicalName', $state))
            ->addFilter(new EqualsFilter('stateMachine.technicalName', $stateMachine));

        $id = $repository->searchIds($criteria, Context::createDefaultContext())->firstId();
        \assert($id !== null);

        return $id;
    }

    protected function getCurrencyIdByIso(string $iso = 'EUR'): string
    {
        $connection = static::getContainer()->get(Connection::class);

        return Uuid::fromBytesToHex($connection->fetchOne('SELECT id FROM currency WHERE iso_code = :iso', ['iso' => $iso]));
    }
}
