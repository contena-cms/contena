<?php declare(strict_types=1);

namespace Contena\Core\Service;

use Contena\Core\Framework\App\AppCollection;
use Contena\Core\Framework\App\AppEntity;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Service\DTO\Service;

/**
 * @internal
 */
#[Package('framework')]
class ServiceStorage
{
    /**
     * @param EntityRepository<AppCollection> $repository
     */
    public function __construct(private readonly EntityRepository $repository)
    {
    }

    public function findByName(string $name, Context $context): ?Service
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('selfManaged', true));
        $criteria->addAssociation('aclRole');
        $criteria->addFilter(new EqualsFilter('name', $name));
        $criteria->setLimit(1);

        return $this->wrap($this->repository->search($criteria, $context)->getEntities()->first());
    }

    public function findByNameAndIntegrationId(string $name, string $integrationId, Context $context): ?Service
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('selfManaged', true));
        $criteria->addAssociation('aclRole');
        $criteria->addFilter(new EqualsFilter('name', $name));
        $criteria->addFilter(new EqualsFilter('integrationId', $integrationId));
        $criteria->setLimit(1);

        return $this->wrap($this->repository->search($criteria, $context)->getEntities()->first());
    }

    public function findByIntegrationId(string $integrationId, Context $context): ?Service
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('selfManaged', true));
        $criteria->addAssociation('aclRole');
        $criteria->addFilter(new EqualsFilter('integrationId', $integrationId));
        $criteria->setLimit(1);

        return $this->wrap($this->repository->search($criteria, $context)->getEntities()->first());
    }

    /**
     * @return list<Service>
     */
    public function findAll(Context $context): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('selfManaged', true));
        $criteria->addAssociation('aclRole');

        return array_values($this->repository->search($criteria, $context)->getEntities()->map(
            static fn (AppEntity $app): Service => Service::fromApp($app)
        ));
    }

    private function wrap(?AppEntity $app): ?Service
    {
        if ($app === null) {
            return null;
        }

        return Service::fromApp($app);
    }
}
