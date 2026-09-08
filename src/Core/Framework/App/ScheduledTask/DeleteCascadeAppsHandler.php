<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\ScheduledTask;

use Contena\Core\Defaults;
use Contena\Core\Framework\Api\Acl\Role\AclRoleCollection;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Contena\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskCollection;
use Contena\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Contena\Core\System\Integration\IntegrationCollection;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * @internal
 */
#[AsMessageHandler(handles: DeleteCascadeAppsTask::class)]
final class DeleteCascadeAppsHandler extends ScheduledTaskHandler
{
    private const HARD_DELETE_AFTER_DAYS = 1;

    /**
     * @internal
     *
     * @param EntityRepository<ScheduledTaskCollection> $scheduledTaskRepository
     * @param EntityRepository<AclRoleCollection> $aclRoleRepository
     * @param EntityRepository<IntegrationCollection> $integrationRepository
     */
    public function __construct(
        EntityRepository $scheduledTaskRepository,
        LoggerInterface $logger,
        private readonly EntityRepository $aclRoleRepository,
        private readonly EntityRepository $integrationRepository,
        private readonly ClockInterface $clock,
    ) {
        parent::__construct($scheduledTaskRepository, $logger);
    }

    public function run(): void
    {
        $context = Context::createCLIContext();
        $timeExpired = $this->clock->now()->modify(\sprintf('-%d day', self::HARD_DELETE_AFTER_DAYS))->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        $criteria = new Criteria();
        $criteria->addFilter(new RangeFilter('deletedAt', [
            RangeFilter::LTE => $timeExpired,
        ]));

        $this->deleteIds($this->aclRoleRepository, $criteria, $context);
        $this->deleteIds($this->integrationRepository, $criteria, $context);
    }

    /**
     * @param EntityRepository<covariant EntityCollection<covariant Entity>> $repository
     */
    private function deleteIds(EntityRepository $repository, Criteria $criteria, Context $context): void
    {
        $ids = $repository->searchIds($criteria, $context)->getIds();
        if ($ids === []) {
            return;
        }

        $deleteIds = array_map(static fn (string $id) => ['id' => $id], $ids);

        $repository->delete($deleteIds, $context);
    }
}
