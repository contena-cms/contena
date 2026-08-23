<?php declare(strict_types=1);

namespace Contena\Core\Content\Flow\Dispatching\Action;

use Contena\Core\Content\Flow\Dispatching\DelayableAction;
use Contena\Core\Content\Flow\Dispatching\StorableFlow;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Event\UserAware;

/**
 * @internal
 */
class RemoveUserTagAction extends FlowAction implements DelayableAction
{
    final public const string ACTION_NAME = 'action.user.tag.remove';

    /**
     * @param EntityRepository<EntityCollection<Entity>> $userTagRepository
     */
    public function __construct(private readonly EntityRepository $userTagRepository)
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

        $this->userTagRepository->delete(
            array_map(static fn (string $tagId): array => ['userId' => $userId, 'tagId' => $tagId], $tagIds),
            $flow->getContext(),
        );
    }
}
