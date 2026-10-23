<?php declare(strict_types=1);

namespace Contena\Core\System\User\Api;

use Contena\Core\System\User\Aggregate\UserGroup\UserGroupCollection;
use Contena\Core\System\User\UserCollection;
use Contena\Core\System\User\UserEntity;
use Contena\Core\System\User\UserException;
use Contena\Core\System\User\Event\UserGroupRegistrationAccepted;
use Contena\Core\System\User\Event\UserGroupRegistrationDeclined;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Routing\ApiRouteScope;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\Context\ChannelContextRestorer;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
class UserGroupRegistrationActionController
{
    /**
     * @param EntityRepository<UserCollection> $userRepository
     * @param EntityRepository<UserGroupCollection> $userGroupRepository
     *
     * @internal
     *
     * @param EntityRepository<UserCollection> $userRepository
     * @param EntityRepository<UserGroupCollection> $userGroupRepository
     */
    public function __construct(
        private readonly EntityRepository $userRepository,
        private readonly EntityRepository $userGroupRepository,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly ChannelContextRestorer $restorer,
    ) {
    }

    /**
     * @throws Exception
     */
    #[Route(path: '/api/_action/user-group-registration/accept', name: 'api.user-group.accept', methods: ['POST'])]
    public function accept(Request $request, Context $context): JsonResponse
    {
        $silentError = $request->request->getBoolean('silentError');
        $userIds = $this->getRequestUserIds($request);
        $users = $this->fetchUsers($userIds, $context, $silentError);
        $requestedUserGroups = $this->fetchRequestedUserGroups($users, $context);

        $updateData = [];
        foreach ($users as $user) {
            $userGroupId = $user->getRequestedGroupId();
            \assert($userGroupId !== null);

            $userRequestedGroup = $requestedUserGroups->get($userGroupId);
            if ($userRequestedGroup === null) {
                throw UserException::userGroupNotFound($userGroupId);
            }

            $updateData[] = [
                'id' => $user->getId(),
                'requestedGroupId' => null,
                'groupId' => $userGroupId,
            ];
        }

        $this->userRepository->update($updateData, $context);

        foreach ($users as $user) {
            $userGroupId = $user->getRequestedGroupId();
            \assert($userGroupId !== null);

            $userRequestedGroup = $requestedUserGroups->get($userGroupId);
            if ($userRequestedGroup === null) {
                throw UserException::userGroupNotFound($userGroupId);
            }

            $user->setGroupId($userGroupId);
            $user->setRequestedGroupId(null);

            $userContext = $this->createUserEventContext($context, $user);

            $this->eventDispatcher->dispatch(new UserGroupRegistrationAccepted(
                $user,
                $userRequestedGroup,
                $userContext
            ));
        }

        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }

    /**
     * @throws Exception
     */
    #[Route(path: '/api/_action/user-group-registration/decline', name: 'api.user-group.decline', methods: ['POST'])]
    public function decline(Request $request, Context $context): JsonResponse
    {
        $silentError = $request->request->getBoolean('silentError');

        $userIds = $this->getRequestUserIds($request);
        $users = $this->fetchUsers($userIds, $context, $silentError);
        $requestedUserGroups = $this->fetchRequestedUserGroups($users, $context);

        $updateData = [];
        foreach ($users as $user) {
            $requestedUserGroupId = $user->getRequestedGroupId();
            \assert($requestedUserGroupId !== null);

            $requestedUserGroup = $requestedUserGroups->get($requestedUserGroupId);
            if ($requestedUserGroup === null) {
                throw UserException::userGroupNotFound($requestedUserGroupId);
            }

            $userContext = $this->createUserEventContext($context, $user);

            $this->eventDispatcher->dispatch(new UserGroupRegistrationDeclined(
                $user,
                $requestedUserGroup,
                $userContext
            ));

            $updateData[] = [
                'id' => $user->getId(),
                'requestedGroupId' => null,
            ];
        }

        $this->userRepository->update($updateData, $context);

        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }

    /**
     * @return non-empty-array<string>
     */
    private function getRequestUserIds(Request $request): array
    {
        $userIds = $request->request->all('userIds');

        if ($userIds !== []) {
            $userIds = array_unique($userIds);
        }

        if ($userIds === []) {
            throw UserException::userIdsParameterIsMissing();
        }

        return $userIds;
    }

    /**
     * @param non-empty-array<string> $userIds
     */
    private function fetchUsers(array $userIds, Context $context, bool $silentError = false): UserCollection
    {
        $criteria = new Criteria($userIds);
        $result = $this->userRepository->search($criteria, $context);
        if ($result->getTotal() === 0) {
            throw UserException::usersNotFound($userIds);
        }

        $users = new UserCollection();

        foreach ($result->getEntities() as $user) {
            if (!$user->getRequestedGroupId()) {
                if ($silentError === false) {
                    throw UserException::groupRequestNotFound($user->getId());
                }

                continue;
            }

            $users->add($user);
        }

        return $users;
    }

    private function fetchRequestedUserGroups(UserCollection $users, Context $context): UserGroupCollection
    {
        if ($users->count() === 0) {
            return new UserGroupCollection();
        }

        $requestedUserGroupIds = [];
        foreach ($users as $user) {
            $requestedUserGroupId = $user->getRequestedGroupId();

            if (!\is_string($requestedUserGroupId)) {
                continue;
            }

            $requestedUserGroupIds[] = $requestedUserGroupId;
        }

        $criteria = new Criteria(\array_values(\array_unique($requestedUserGroupIds)));

        return $this->userGroupRepository->search($criteria, $context)->getEntities();
    }

    private function createUserEventContext(Context $context, UserEntity $user): Context
    {
        if (!Feature::isActive('v6.8.0.0') && $user->getActive()) {
            Feature::triggerDeprecationOrThrow(
                'v6.8.0.0',
                \sprintf(
                    'Using a ChannelContext for user-group registration events is deprecated and will be removed in 6.8.0.0. Use the event Context and the user/user-group payload of the event instead (%s).',
                    self::class
                )
            );

            return $this->restorer->restoreByUser($user->getId(), $context)->getContext();
        }

        $userLanguageChain = \array_values(\array_unique(\array_filter([$user->getLanguageId(), ...$context->getLanguageIdChain()])));

        $userContext = clone $context;
        $userContext->assign(['languageIdChain' => $userLanguageChain]);

        return $userContext;
    }
}
