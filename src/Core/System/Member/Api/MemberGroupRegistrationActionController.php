<?php declare(strict_types=1);

namespace Contena\Core\System\Member\Api;

use Doctrine\DBAL\Exception;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Routing\ApiRouteScope;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Member\Aggregate\MemberGroup\MemberGroupCollection;
use Contena\Core\System\Member\Event\MemberGroupRegistrationAccepted;
use Contena\Core\System\Member\Event\MemberGroupRegistrationDeclined;
use Contena\Core\System\Member\MemberCollection;
use Contena\Core\System\Member\MemberEntity;
use Contena\Core\System\Member\MemberException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
class MemberGroupRegistrationActionController
{
    /**
     * @internal
     *
     * @param EntityRepository<MemberCollection> $memberRepository
     * @param EntityRepository<MemberGroupCollection> $memberGroupRepository
     */
    public function __construct(
        private readonly EntityRepository $memberRepository,
        private readonly EntityRepository $memberGroupRepository,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * @throws Exception
     */
    #[Route(path: '/api/_action/member-group-registration/accept', name: 'api.member-group.accept', methods: ['POST'], requirements: ['version' => '\d+'])]
    public function accept(Request $request, Context $context): JsonResponse
    {
        $silentError = $request->request->getBoolean('silentError');
        $memberIds = $this->getRequestMemberIds($request);
        $members = $this->fetchMembers($memberIds, $context, $silentError);
        $requestedMemberGroups = $this->fetchRequestedMemberGroups($members, $context);

        $updateData = [];
        foreach ($members as $member) {
            $memberGroupId = $member->getRequestedGroupId();
            \assert($memberGroupId !== null);

            $memberRequestedGroup = $requestedMemberGroups->get($memberGroupId);
            if ($memberRequestedGroup === null) {
                throw MemberException::memberGroupNotFound($memberGroupId);
            }

            $updateData[] = [
                'id' => $member->getId(),
                'requestedGroupId' => null,
                'groupId' => $memberGroupId,
            ];
        }

        $this->memberRepository->update($updateData, $context);

        foreach ($members as $member) {
            $memberGroupId = $member->getRequestedGroupId();
            \assert($memberGroupId !== null);

            $memberRequestedGroup = $requestedMemberGroups->get($memberGroupId);
            if ($memberRequestedGroup === null) {
                throw MemberException::memberGroupNotFound($memberGroupId);
            }

            $member->setGroupId($memberGroupId);
            $member->setRequestedGroupId(null);

            $memberContext = $this->createMemberEventContext($context, $member);

            $this->eventDispatcher->dispatch(new MemberGroupRegistrationAccepted(
                $member,
                $memberRequestedGroup,
                $memberContext
            ));
        }

        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }

    /**
     * @throws Exception
     */
    #[Route(path: '/api/_action/member-group-registration/decline', name: 'api.member-group.decline', methods: ['POST'], requirements: ['version' => '\d+'])]
    public function decline(Request $request, Context $context): JsonResponse
    {
        $silentError = $request->request->getBoolean('silentError');

        $memberIds = $this->getRequestMemberIds($request);
        $members = $this->fetchMembers($memberIds, $context, $silentError);
        $requestedMemberGroups = $this->fetchRequestedMemberGroups($members, $context);

        $updateData = [];
        foreach ($members as $member) {
            $requestedMemberGroupId = $member->getRequestedGroupId();
            \assert($requestedMemberGroupId !== null);

            $requestedMemberGroup = $requestedMemberGroups->get($requestedMemberGroupId);
            if ($requestedMemberGroup === null) {
                throw MemberException::memberGroupNotFound($requestedMemberGroupId);
            }

            $memberContext = $this->createMemberEventContext($context, $member);

            $this->eventDispatcher->dispatch(new MemberGroupRegistrationDeclined(
                $member,
                $requestedMemberGroup,
                $memberContext
            ));

            $updateData[] = [
                'id' => $member->getId(),
                'requestedGroupId' => null,
            ];
        }

        $this->memberRepository->update($updateData, $context);

        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }

    /**
     * @return non-empty-array<string>
     */
    private function getRequestMemberIds(Request $request): array
    {
        $memberIds = $request->request->all('memberIds');

        if ($memberIds !== []) {
            $memberIds = array_unique($memberIds);
        }

        if ($memberIds === []) {
            throw MemberException::memberIdsParameterIsMissing();
        }

        return $memberIds;
    }

    /**
     * @param non-empty-array<string> $memberIds
     */
    private function fetchMembers(array $memberIds, Context $context, bool $silentError = false): MemberCollection
    {
        $criteria = new Criteria($memberIds);
        $result = $this->memberRepository->search($criteria, $context);
        if ($result->getTotal() === 0) {
            throw MemberException::membersNotFound($memberIds);
        }

        $members = new MemberCollection();

        foreach ($result->getEntities() as $member) {
            if (!$member->getRequestedGroupId()) {
                if ($silentError === false) {
                    throw MemberException::groupRequestNotFound($member->getId());
                }

                continue;
            }

            $members->add($member);
        }

        return $members;
    }

    private function fetchRequestedMemberGroups(MemberCollection $members, Context $context): MemberGroupCollection
    {
        if ($members->count() === 0) {
            return new MemberGroupCollection();
        }

        $requestedMemberGroupIds = [];
        foreach ($members as $member) {
            $requestedMemberGroupId = $member->getRequestedGroupId();

            if (!\is_string($requestedMemberGroupId)) {
                continue;
            }

            $requestedMemberGroupIds[] = $requestedMemberGroupId;
        }

        $criteria = new Criteria(\array_values(\array_unique($requestedMemberGroupIds)));

        return $this->memberGroupRepository->search($criteria, $context)->getEntities();
    }

    private function createMemberEventContext(Context $context, MemberEntity $member): Context
    {
        $memberLanguageChain = \array_values(\array_unique(\array_filter([$member->getLanguageId(), ...$context->getLanguageIdChain()])));

        $memberContext = clone $context;
        $memberContext->assign(['languageIdChain' => $memberLanguageChain]);

        return $memberContext;
    }
}
