<?php declare(strict_types=1);

namespace Contena\Core\Content\Flow\Dispatching\Action;

use Contena\Core\System\User\UserCollection;
use Contena\Core\Content\Flow\Dispatching\DelayableAction;
use Contena\Core\Content\Flow\Dispatching\StorableFlow;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Event\UserAware;
use Contena\Core\Framework\Log\Package;

/**
 * @internal
 */
class ChangeUserGroupAction extends FlowAction implements DelayableAction
{
    /**
     * @internal
     *
     * @param EntityRepository<UserCollection> $userRepository
     */
    public function __construct(private readonly EntityRepository $userRepository)
    {
    }

    public static function getName(): string
    {
        return 'action.change.user.group';
    }

    /**
     * @return list<string>
     */
    public function requirements(): array
    {
        return [UserAware::class];
    }

    public function handleFlow(StorableFlow $flow): void
    {
        if (!$flow->hasData(UserAware::CUSTOMER_ID)) {
            return;
        }

        $this->update($flow->getContext(), $flow->getConfig(), $flow->getData(UserAware::CUSTOMER_ID));
    }

    /**
     * @param array<string, mixed> $config
     */
    private function update(Context $context, array $config, string $userId): void
    {
        if (!\array_key_exists('userGroupId', $config)) {
            return;
        }

        $userGroupId = $config['userGroupId'];
        if ($userGroupId === null || $userGroupId === '') {
            return;
        }

        $this->userRepository->update([
            [
                'id' => $userId,
                'groupId' => $userGroupId,
                'requestedGroupId' => null,
            ],
        ], $context);
    }
}
