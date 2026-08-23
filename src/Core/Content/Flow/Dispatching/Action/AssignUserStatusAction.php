<?php declare(strict_types=1);

namespace Contena\Core\Content\Flow\Dispatching\Action;

use Contena\Core\Content\Flow\Dispatching\DelayableAction;
use Contena\Core\Content\Flow\Dispatching\StorableFlow;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Event\UserAware;
use Contena\Core\System\User\UserCollection;

/**
 * @internal
 */
class AssignUserStatusAction extends FlowAction implements DelayableAction
{
    final public const string ACTION_NAME = 'action.user.status.assign';

    /**
     * @param EntityRepository<UserCollection> $userRepository
     */
    public function __construct(private readonly EntityRepository $userRepository)
    {
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
        $active = $flow->getConfig()['active'] ?? null;
        if (!\is_string($userId) || !\is_bool($active)) {
            return;
        }

        $this->userRepository->update([['id' => $userId, 'active' => $active]], $flow->getContext());
    }
}
