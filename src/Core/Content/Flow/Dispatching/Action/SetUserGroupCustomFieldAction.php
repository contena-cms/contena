<?php declare(strict_types=1);

namespace Contena\Core\Content\Flow\Dispatching\Action;

use Doctrine\DBAL\Connection;
use Contena\Core\Content\Flow\Dispatching\DelayableAction;
use Contena\Core\Content\Flow\Dispatching\StorableFlow;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Event\UserGroupAware;
use Contena\Core\System\User\Aggregate\UserGroup\UserGroupCollection;

/**
 * @internal
 */
class SetUserGroupCustomFieldAction extends FlowAction implements DelayableAction
{
    use CustomFieldActionTrait;

    /**
     * @internal
     *
     * @param EntityRepository<UserGroupCollection> $userGroupRepository
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly EntityRepository $userGroupRepository
    ) {
    }

    public static function getName(): string
    {
        return 'action.set.user.group.custom.field';
    }

    /**
     * @return list<string>
     */
    public function requirements(): array
    {
        return [UserGroupAware::class];
    }

    public function handleFlow(StorableFlow $flow): void
    {
        if (!$flow->hasData(UserGroupAware::CUSTOMER_GROUP_ID)) {
            return;
        }

        $this->update($flow->getContext(), $flow->getConfig(), $flow->getData(UserGroupAware::CUSTOMER_GROUP_ID));
    }

    /**
     * @param array<string, mixed> $config
     */
    private function update(Context $context, array $config, string $userGroupId): void
    {
        $userGroup = $this->userGroupRepository->search(new Criteria([$userGroupId]), $context)->getEntities()->first();

        $customFields = $this->getCustomFieldForUpdating($userGroup?->getCustomFields(), $config);
        if ($customFields === null) {
            return;
        }

        $customFields = $customFields === [] ? null : $customFields;

        $this->userGroupRepository->update([
            [
                'id' => $userGroupId,
                'customFields' => $customFields,
            ],
        ], $context);
    }
}
