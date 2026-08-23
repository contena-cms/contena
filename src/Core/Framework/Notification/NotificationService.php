<?php declare(strict_types=1);

namespace Contena\Core\Framework\Notification;

use Contena\Core\Defaults;
use Contena\Core\Framework\Api\Context\AdminApiSource;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;

/**
 * @internal
 *
 * @phpstan-type Notification array{id: non-empty-string, status: string, message: string, adminOnly?: bool, requiredPrivileges: array<int, string>, createdByIntegrationId?: string|null, createdByUserId?: string|null}
 */
class NotificationService
{
    /**
     * @param EntityRepository<NotificationCollection> $notificationRepository
     */
    public function __construct(private readonly EntityRepository $notificationRepository)
    {
    }

    /**
     * @param Notification $data
     */
    public function createNotification(array $data, Context $context): void
    {
        $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($data): void {
            $this->notificationRepository->create([$data], $context);
        });
    }

    /**
     * @return array{notifications: NotificationCollection, timestamp: string|null}
     */
    public function getNotifications(Context $context, int $limit, ?string $latestTimestamp): array
    {
        $source = $context->getSource();
        if (!$source instanceof AdminApiSource) {
            throw NotificationException::invalidAdminSource($context->getSource()::class);
        }

        $criteria = new Criteria();
        $isAdmin = $source->isAdmin();
        if (!$isAdmin) {
            $criteria->addFilter(new EqualsFilter('adminOnly', false));
        }

        if ($latestTimestamp) {
            $criteria->addFilter(new RangeFilter('createdAt', [
                RangeFilter::GT => $latestTimestamp,
            ]));
        }

        $criteria->addSorting(new FieldSorting('createdAt', FieldSorting::ASCENDING));
        $criteria->setLimit($limit);

        $notifications = $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($criteria) {
            return $this->notificationRepository->search($criteria, $context)->getEntities();
        });

        if ($notifications->count() === 0) {
            return [
                'notifications' => new NotificationCollection(),
                'timestamp' => null,
            ];
        }

        $latestTimestamp = $notifications->last()?->getCreatedAt()?->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        if ($isAdmin) {
            return [
                'notifications' => $notifications,
                'timestamp' => $latestTimestamp,
            ];
        }

        $notifications = $this->formatNotifications($notifications, $source);

        return [
            'notifications' => $notifications,
            'timestamp' => $latestTimestamp,
        ];
    }

    private function formatNotifications(NotificationCollection $notifications, AdminApiSource $source): NotificationCollection
    {
        $responseNotifications = new NotificationCollection();

        /** @var NotificationEntity $notification */
        foreach ($notifications as $notification) {
            if ($this->isAllow($notification->getRequiredPrivileges(), $source)) {
                $responseNotifications->add($notification);
            }
        }

        return $responseNotifications;
    }

    /**
     * @param array<string> $privileges
     */
    private function isAllow(array $privileges, AdminApiSource $source): bool
    {
        return array_all($privileges, fn ($privilege) => $source->isAllowed($privilege));
    }
}
