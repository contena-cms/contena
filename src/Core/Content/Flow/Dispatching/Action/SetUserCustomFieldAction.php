<?php declare(strict_types=1);

namespace Contena\Core\Content\Flow\Dispatching\Action;

use Doctrine\DBAL\Connection;
use Contena\Core\Content\Flow\Dispatching\DelayableAction;
use Contena\Core\Content\Flow\Dispatching\StorableFlow;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Event\UserAware;
use Contena\Core\System\User\UserCollection;

/**
 * @internal
 */
class SetUserCustomFieldAction extends FlowAction implements DelayableAction
{
    use CustomFieldActionTrait;

    final public const string ACTION_NAME = 'action.user.custom.field.set';

    /**
     * @param EntityRepository<UserCollection> $userRepository
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly EntityRepository $userRepository,
    ) {
    }

    public static function getName(): string
    {
        return self::ACTION_NAME;
    }

    public function requirements(): array
    {
        return [UserAware::class];
    }

    public function handleFlow(StorableFlow $flow): void
    {
        $userId = $flow->getData(UserAware::USER_ID);
        if (!\is_string($userId)) {
            return;
        }

        $user = $this->userRepository->search(new Criteria([$userId]), $flow->getContext())->getEntities()->first();
        $customFields = $this->getCustomFieldForUpdating($user?->getCustomFields(), $flow->getConfig());
        if ($customFields === null) {
            return;
        }

        $this->userRepository->update([[
            'id' => $userId,
            'customFields' => $customFields === [] ? null : $customFields,
        ]], $flow->getContext());
    }
}
