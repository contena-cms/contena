<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Member\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Member\Aggregate\MemberGroup\MemberGroupCollection;
use Contena\Core\System\Member\Aggregate\MemberGroup\MemberGroupEntity;
use Contena\Core\System\Member\Api\MemberGroupRegistrationActionController;
use Contena\Core\System\Member\Event\MemberGroupRegistrationDeclined;
use Contena\Core\System\Member\MemberCollection;
use Contena\Core\System\Member\MemberEntity;
use Contena\Core\System\Member\MemberException;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(MemberGroupRegistrationActionController::class)]
class MemberGroupRegistrationActionControllerTest extends TestCase
{
    private MemberGroupRegistrationActionController $controllerMock;

    /**
     * @var Stub&EntityRepository<MemberCollection>
     */
    private Stub&EntityRepository $memberRepositoryMock;

    /**
     * @var Stub&EntityRepository<MemberGroupCollection>
     */
    private Stub&EntityRepository $memberGroupRepositoryMock;

    private Stub&EventDispatcher $eventDispatcherMock;

    protected function setUp(): void
    {
        $this->memberRepositoryMock = static::createStub(EntityRepository::class);
        $this->memberGroupRepositoryMock = static::createStub(EntityRepository::class);
        $this->eventDispatcherMock = static::createStub(EventDispatcher::class);
        $this->controllerMock = new MemberGroupRegistrationActionController(
            $this->memberRepositoryMock,
            $this->memberGroupRepositoryMock,
            $this->eventDispatcherMock,
        );
    }

    /**
     * @param MemberEntity[] $members
     */
    #[DataProvider('groupRegistrationActionDataProvider')]
    public function testGroupRegistrationAcceptMatches(?int $expectedResCode, ?array $members, Request $request, ?\Exception $expectedException): void
    {
        $context = Context::createDefaultContext();

        if ($members !== null) {
            $memberCollection = new MemberCollection($members);
            $this->setSearchReturn($context, $memberCollection);
            $this->setMemberGroupSearchReturn($context, $memberCollection);
        }

        if ($expectedException !== null && $expectedResCode === null) {
            $this->expectExceptionObject($expectedException);
        }

        $res = $this->controllerMock->accept($request, $context);
        static::assertSame($expectedResCode, $res->getStatusCode());
    }

    /**
     * @param MemberEntity[] $members
     */
    #[DataProvider('groupRegistrationActionDataProvider')]
    public function testGroupRegistrationDeclineMatches(?int $expectedResCode, ?array $members, Request $request, ?\Exception $expectedException): void
    {
        $context = Context::createDefaultContext();

        if ($members !== null) {
            $memberCollection = new MemberCollection($members);
            $this->setSearchReturn($context, $memberCollection);
            $this->setMemberGroupSearchReturn($context, $memberCollection);
        }

        if ($expectedException !== null && $expectedResCode === null) {
            $this->expectExceptionObject($expectedException);
        }

        $res = $this->controllerMock->decline($request, $context);
        static::assertSame($expectedResCode, $res->getStatusCode());
    }

    /**
     * @return iterable<string, array{int|null, array<MemberEntity>|null, Request, \Exception|null}>
     */
    public static function groupRegistrationActionDataProvider(): iterable
    {
        $invalidMember = Uuid::randomHex();
        yield 'without user' => [null, null, self::createRequest([$invalidMember]), MemberException::membersNotFound([$invalidMember])];

        $missingMember = self::createMember();
        $missingMemberId = $missingMember->getId();
        yield 'without member' => [null, null, self::createRequest([$missingMemberId]), MemberException::membersNotFound([$missingMemberId])];

        yield 'without memberId' => [null, null, self::createRequest([]), MemberException::memberIdsParameterIsMissing()];

        $memberWithoutRequest = self::createMember(false);
        $memberWithoutRequestId = $memberWithoutRequest->getId();
        yield 'without request group' => [null, [$memberWithoutRequest], self::createRequest([$memberWithoutRequestId]), MemberException::groupRequestNotFound($memberWithoutRequestId)];

        $acceptMember = self::createMember();
        $acceptMemberId = $acceptMember->getId();
        yield 'accept/decline' => [204, [$acceptMember], self::createRequest([$acceptMemberId]), null];

        $silentMember = self::createMember(false);
        $silentMemberId = $silentMember->getId();
        yield 'accept/decline silent' => [204, [$silentMember], self::createRequest([$silentMemberId], true), null];

        $batchMemberA = self::createMember();
        $batchMemberAId = $batchMemberA->getId();
        $batchMemberB = self::createMember();
        $batchMemberBId = $batchMemberB->getId();
        yield 'in batch' => [204, [$batchMemberA, $batchMemberB], self::createRequest([$batchMemberAId, $batchMemberBId]), null];
    }

    public function testDeclineMemberRequestedGroupIsSetCorrectly(): void
    {
        $context = Context::createDefaultContext();

        $assignedMemberGroup = new MemberGroupEntity();
        $assignedMemberGroup->setId(Uuid::randomHex());

        $requestedMemberGroup = new MemberGroupEntity();
        $requestedMemberGroup->setId(Uuid::randomHex());

        $member = new MemberEntity();
        $member->setId(Uuid::randomHex());
        $member->setLanguageId(Defaults::LANGUAGE_SYSTEM);
        $member->setRequestedGroupId($requestedMemberGroup->getId());
        $member->setRequestedGroup($requestedMemberGroup);
        $member->setGroupId($assignedMemberGroup->getId());

        $request = self::createRequest([$member->getId()]);

        $this->setSearchReturn($context, new MemberCollection([$member]));

        $this->memberGroupRepositoryMock->method('search')->willReturn(
            new EntitySearchResult(
                1,
                new MemberGroupCollection([$requestedMemberGroup]),
                null,
                new Criteria(),
                $context,
            )
        );

        // test case to ensure the event contains the declined requested member group
        $this->eventDispatcherMock->method('dispatch')->willReturnCallback(static function (MemberGroupRegistrationDeclined $memberGroupRegistrationDeclined) use ($member, $requestedMemberGroup) {
            static::assertSame($member, $memberGroupRegistrationDeclined->getMember());
            static::assertSame($requestedMemberGroup, $memberGroupRegistrationDeclined->getMemberGroup());

            return $memberGroupRegistrationDeclined;
        });

        $this->controllerMock->decline($request, $context);
    }

    private static function createMember(bool $requestedGroup = true): MemberEntity
    {
        $member = new MemberEntity();
        $member->setId(Uuid::randomHex());
        $member->setActive(true);
        $member->setLanguageId(Defaults::LANGUAGE_SYSTEM);

        if ($requestedGroup) {
            $memberGroup = new MemberGroupEntity();
            $memberGroup->setId(Uuid::randomHex());
            $member->setRequestedGroup($memberGroup);
            $member->setRequestedGroupId($memberGroup->getId());
        }

        return $member;
    }

    /**
     * @param string[] $memberId
     */
    private static function createRequest(array $memberId, bool $silentError = false): Request
    {
        $request = new Request();
        $request->request->add(['memberIds' => $memberId, 'silentError' => $silentError]);

        return $request;
    }

    private function setSearchReturn(Context $context, ?MemberCollection $collection = null): void
    {
        if (!$collection instanceof MemberCollection) {
            $collection = new MemberCollection();
        }
        $criteria = new Criteria(array_values($collection->getIds()));

        $this->memberRepositoryMock->method('search')
            ->willReturnOnConsecutiveCalls(
                new EntitySearchResult(
                    $collection->count(),
                    $collection,
                    null,
                    $criteria,
                    $context
                ),
            );
    }

    private function setMemberGroupSearchReturn(Context $context, MemberCollection $members): void
    {
        $memberGroups = [];
        foreach ($members as $member) {
            $requestedGroupId = $member->getRequestedGroupId();
            if ($requestedGroupId === null || isset($memberGroups[$requestedGroupId])) {
                continue;
            }

            $memberGroup = new MemberGroupEntity();
            $memberGroup->setId($requestedGroupId);
            $memberGroups[$requestedGroupId] = $memberGroup;
        }

        $collection = new MemberGroupCollection(\array_values($memberGroups));

        $this->memberGroupRepositoryMock->method('search')->willReturn(
            new EntitySearchResult(
                $collection->count(),
                $collection,
                null,
                new Criteria(),
                $context,
            )
        );
    }
}
