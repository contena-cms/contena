<?php declare(strict_types=1);

namespace Contena\Core\System\DataDictionary;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;

/**
 * @internal
 */
class DataDictionaryLoader implements DataDictionaryLoaderInterface
{
    /**
     * @param EntityRepository<DataDictionaryCollection> $repository
     */
    public function __construct(private readonly EntityRepository $repository)
    {
    }

    public function load(string $technicalName, Context $context): ?DataDictionaryEntity
    {
        $criteria = new Criteria()
            ->setLimit(1)
            ->addFilter(new EqualsFilter('technicalName', $technicalName));
        $criteria->getAssociation('items')
            ->addSorting(new FieldSorting('position'));

        return $this->repository->search($criteria, $context)->getEntities()->first();
    }
}
