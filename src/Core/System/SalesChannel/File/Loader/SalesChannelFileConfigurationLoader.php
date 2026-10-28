<?php declare(strict_types=1);

namespace Contena\Core\System\SalesChannel\File\Loader;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\Aggregate\SalesChannelFile\SalesChannelFileCollection;
use Contena\Core\System\SalesChannel\Aggregate\SalesChannelFile\SalesChannelFileEntity;

/**
 * @internal
 */
#[Package('framework')]
class SalesChannelFileConfigurationLoader
{
    /**
     * @param EntityRepository<SalesChannelFileCollection> $repository
     */
    public function __construct(private readonly EntityRepository $repository)
    {
    }

    public function load(string $fileFamily, string $fileName, string $salesChannelId, Context $context): ?SalesChannelFileEntity
    {
        // The case-insensitive database collation and unique index guarantee at most one configuration per logical file name.
        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('salesChannelId', $salesChannelId))
            ->addFilter(new EqualsFilter('fileFamily', $fileFamily))
            ->addFilter(new EqualsFilter('fileName', $fileName))
            ->setLimit(1);

        return $this->repository->search($criteria, $context)->getEntities()->first();
    }

    /**
     * @return array<string, SalesChannelFileEntity>
     */
    public function loadForFileFamily(string $fileFamily, string $salesChannelId, Context $context): array
    {
        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('salesChannelId', $salesChannelId))
            ->addFilter(new EqualsFilter('fileFamily', $fileFamily));

        $entities = $this->repository->search($criteria, $context)->getEntities();
        $configurations = [];

        foreach ($entities as $entity) {
            $configurations[mb_strtolower($entity->getFileName())] = $entity;
        }

        return $configurations;
    }
}
