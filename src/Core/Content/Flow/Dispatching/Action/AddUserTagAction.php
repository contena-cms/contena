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
class AddUserTagAction extends FlowAction implements DelayableAction
{
    final public const string ACTION_NAME = 'action.user.tag.add';

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
        $tagIds = $flow->getConfig()['tagIds'] ?? [];
        if (!\is_string($userId) || !\is_array($tagIds)) {
            return;
        }

        $tagIds = array_values(array_filter($tagIds, static fn (mixed $tagId): bool => \is_string($tagId)));
        if ($tagIds === []) {
            return;
        }

        $this->userRepository->update([[
            'id' => $userId,
            'tags' => array_map(static fn (string $tagId): array => ['id' => $tagId], $tagIds),
        ]], $flow->getContext());
    }
}
