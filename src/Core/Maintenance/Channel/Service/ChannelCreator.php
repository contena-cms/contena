<?php declare(strict_types=1);

namespace Contena\Core\Maintenance\Channel\Service;

use Contena\Core\Content\Category\CategoryCollection;
use Contena\Core\Defaults;
use Contena\Core\Framework\Api\Util\AccessKeyHelper;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Contena\Core\Maintenance\MaintenanceException;
use Contena\Core\System\Channel\ChannelCollection;
use Contena\Core\System\Country\CountryCollection;
use Contena\Core\System\Member\Aggregate\MemberGroup\MemberGroupDefinition;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Contena\Tests\Integration\Core\Maintenance\Channel\Service\ChannelCreatorTest
 */
class ChannelCreator
{
    /**
     * @param EntityRepository<ChannelCollection> $channelRepository
     * @param EntityRepository<CountryCollection> $countryRepository
     * @param EntityRepository<CategoryCollection> $categoryRepository
     */
    public function __construct(
        private readonly DefinitionInstanceRegistry $definitionRegistry,
        private readonly EntityRepository $channelRepository,
        private readonly EntityRepository $countryRepository,
        private readonly EntityRepository $categoryRepository
    ) {
    }

    /**
     * @param list<string>|null $languages
     * @param list<string>|null $countries
     * @param array<string, mixed> $overwrites
     */
    public function createChannel(
        string $id,
        string $name,
        string $typeId,
        ?string $languageId = null,
        ?string $countryId = null,
        ?string $memberGroupId = null,
        ?string $navigationCategoryId = null,
        ?array $languages = null,
        ?array $countries = null,
        array $overwrites = []
    ): string {
        $context = Context::createDefaultContext();
        $languageId ??= Defaults::LANGUAGE_SYSTEM;
        $countryId ??= $this->getFirstActiveCountryId($context);

        $data = [
            'id' => $id,
            'name' => $name,
            'typeId' => $typeId,
            'accessKey' => AccessKeyHelper::generateAccessKey('channel'),
            'languageId' => $languageId,
            'countryId' => $countryId,
            'memberGroupId' => $memberGroupId ?? $this->getMemberGroupId($context),
            'navigationCategoryId' => $navigationCategoryId ?? $this->getRootCategoryId($context),
            'languages' => $this->formatToMany($languages, $languageId, 'language', $context),
            'countries' => $this->formatToMany($countries, $countryId, 'country', $context),
        ];

        $data = array_replace_recursive($data, $overwrites);
        $this->channelRepository->create([$data], $context);

        return $data['accessKey'];
    }

    private function getFirstActiveCountryId(Context $context): string
    {
        $criteria = new Criteria()
            ->setLimit(1)
            ->addFilter(new EqualsFilter('active', true))
            ->addSorting(new FieldSorting('position'));

        $countryId = $this->countryRepository->searchIds($criteria, $context)->firstId();
        if (!\is_string($countryId)) {
            throw MaintenanceException::couldNotGetId('first active country');
        }

        return $countryId;
    }

    private function getRootCategoryId(Context $context): string
    {
        $criteria = new Criteria();
        $criteria->setLimit(1);
        $criteria->addFilter(new EqualsFilter('category.parentId', null));
        $criteria->addSorting(new FieldSorting('category.createdAt', FieldSorting::ASCENDING));

        $categoryId = $this->categoryRepository->searchIds($criteria, $context)->firstId();
        if (!\is_string($categoryId)) {
            throw MaintenanceException::couldNotGetId('root category');
        }

        return $categoryId;
    }

    /**
     * @return array<array{id: string}>
     */
    private function getAllIdsOf(string $entity, Context $context): array
    {
        $ids = $this->definitionRegistry->getRepository($entity)->searchIds(new Criteria(), $context)->getIds();

        return array_map(static fn (string $id): array => ['id' => $id], $ids);
    }

    private function getMemberGroupId(Context $context): string
    {
        $criteria = new Criteria();
        $criteria->setLimit(1);
        $id = $this->definitionRegistry->getRepository(MemberGroupDefinition::ENTITY_NAME)->searchIds($criteria, $context)->firstId();
        if ($id === null) {
            throw MaintenanceException::couldNotGetId('member group');
        }

        return $id;
    }

    /**
     * @param list<string>|null $values
     *
     * @return array<array{id: string}>
     */
    private function formatToMany(?array $values, string $default, string $entity, Context $context): array
    {
        if (!\is_array($values)) {
            return $this->getAllIdsOf($entity, $context);
        }

        $values = array_unique(array_merge($values, [$default]));

        return array_map(static fn (string $id): array => ['id' => $id], $values);
    }
}
