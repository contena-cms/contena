<?php declare(strict_types=1);

namespace Contena\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Entity;

use Contena\Core\Content\ImportExport\Struct\Config;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\System\Country\CountryCollection;
use Contena\Core\System\Country\CountryDefinition;
use Symfony\Contracts\Service\ResetInterface;

class CountrySerializer extends EntitySerializer implements ResetInterface
{
    /**
     * @var array<string, string|null>
     */
    private array $cacheCountries = [];

    /**
     * @internal
     *
     * @param EntityRepository<CountryCollection> $countryRepository
     */
    public function __construct(private readonly EntityRepository $countryRepository)
    {
    }

    public function deserialize(Config $config, EntityDefinition $definition, $entity)
    {
        $deserialized = parent::deserialize($config, $definition, $entity);

        $deserialized = \is_array($deserialized) ? $deserialized : iterator_to_array($deserialized);

        if (!isset($deserialized['id']) && isset($deserialized['iso'])) {
            $id = $this->getCountryId($deserialized['iso']);

            if ($id) {
                $deserialized['id'] = $id;
            }
        }

        yield from $deserialized;
    }

    public function supports(string $entity): bool
    {
        return $entity === CountryDefinition::ENTITY_NAME;
    }

    public function reset(): void
    {
        $this->cacheCountries = [];
    }

    private function getCountryId(string $iso): ?string
    {
        if (\array_key_exists($iso, $this->cacheCountries)) {
            return $this->cacheCountries[$iso];
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('iso', $iso));

        $this->cacheCountries[$iso] = $this->countryRepository->searchIds(
            $criteria,
            Context::createDefaultContext()
        )->firstId();

        return $this->cacheCountries[$iso];
    }
}
