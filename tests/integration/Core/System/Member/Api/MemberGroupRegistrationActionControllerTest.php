<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\System\Member\Api;

use PHPUnit\Framework\TestCase;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Test\TestCaseBase\ChannelApiTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\EventDispatcherBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Member\Aggregate\MemberGroup\MemberGroupEntity;
use Contena\Core\System\Member\Api\MemberGroupRegistrationActionController;
use Contena\Core\System\Member\Event\MemberGroupRegistrationAccepted;
use Contena\Core\System\Member\Event\MemberGroupRegistrationDeclined;
use Contena\Core\System\Member\MemberEntity;
use Contena\Core\Test\TestDefaults;
use Symfony\Component\EventDispatcher\Debug\TraceableEventDispatcher;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
class MemberGroupRegistrationActionControllerTest extends TestCase
{
    use ChannelApiTestBehaviour;
    use EventDispatcherBehaviour;
    use IntegrationTestBehaviour;

    public const string B2B_MEMBER_GROUP_NAME = 'B2B_GROUP';

    public function testAcceptAcceptedMemberGroupIsSetCorrectly(): void
    {
        $requestedMemberGroup = $this->createMemberGroup();
        $memberId = $this->createChannelApiMember(memberOverride: ['requestedGroupId' => $requestedMemberGroup->getId()]);

        $eventDispatcher = $this->getContainer()->get('event_dispatcher');
        static::assertInstanceOf(TraceableEventDispatcher::class, $eventDispatcher);
        $controller = $this->createController($eventDispatcher);

        $this->addEventListener(
            $eventDispatcher,
            MemberGroupRegistrationAccepted::class,
            function (MemberGroupRegistrationAccepted $event) use ($memberId, $requestedMemberGroup): void {
                static::assertSame($memberId, $event->getMember()->getId());
                static::assertSame($requestedMemberGroup->getId(), $event->getMemberGroup()->getId());
                static::assertSame(self::B2B_MEMBER_GROUP_NAME, $event->getMemberGroup()->getName());
            }
        );

        $request = new Request();
        $request->request->add(['memberIds' => [$memberId]]);

        $controller->accept($request, Context::createDefaultContext());

        $memberResult = $this->fetchMemberById($memberId);
        static::assertInstanceOf(MemberEntity::class, $memberResult);
        static::assertSame($requestedMemberGroup->getId(), $memberResult->getGroupId());
        static::assertNull($memberResult->getRequestedGroupId());
    }

    public function testDeclineDeclinedMemberGroupIsSetCorrectly(): void
    {
        $requestedMemberGroup = $this->createMemberGroup();
        $memberId = $this->createChannelApiMember(memberOverride: ['requestedGroupId' => $requestedMemberGroup->getId()]);

        $eventDispatcher = $this->getContainer()->get('event_dispatcher');
        static::assertInstanceOf(TraceableEventDispatcher::class, $eventDispatcher);
        $controller = $this->createController($eventDispatcher);

        $this->addEventListener(
            $eventDispatcher,
            MemberGroupRegistrationDeclined::class,
            static function (MemberGroupRegistrationDeclined $event) use ($memberId, $requestedMemberGroup): void {
                // Check requested memberGroup is set in event
                static::assertSame($memberId, $event->getMember()->getId());
                static::assertSame($requestedMemberGroup->getId(), $event->getMemberGroup()->getId());
                static::assertSame(self::B2B_MEMBER_GROUP_NAME, $event->getMemberGroup()->getName());
            }
        );

        $request = new Request();
        $request->request->add(['memberIds' => [$memberId]]);

        $controller->decline($request, Context::createDefaultContext());

        $memberResult = $this->fetchMemberById($memberId);
        static::assertInstanceOf(MemberEntity::class, $memberResult);
        static::assertNull($memberResult->getRequestedGroupId());
    }

    public function testAcceptWithInactiveMember(): void
    {
        $requestedMemberGroup = $this->createMemberGroup();
        $memberId = $this->createChannelApiMember(memberOverride: [
            'requestedGroupId' => $requestedMemberGroup->getId(),
            'active' => false,
        ]);

        $eventDispatcher = $this->getContainer()->get('event_dispatcher');
        static::assertInstanceOf(TraceableEventDispatcher::class, $eventDispatcher);
        $controller = $this->createController($eventDispatcher);

        $request = new Request();
        $request->request->add(['memberIds' => [$memberId]]);

        $controller->accept($request, Context::createDefaultContext());

        $memberResult = $this->fetchMemberById($memberId);
        static::assertInstanceOf(MemberEntity::class, $memberResult);
        static::assertFalse($memberResult->getActive());
        static::assertSame($requestedMemberGroup->getId(), $memberResult->getGroupId());
        static::assertNull($memberResult->getRequestedGroupId());
    }

    public function testAcceptDispatchesMemberLanguageContext(): void
    {
        $languageId = $this->createLanguage();
        $requestedMemberGroup = $this->createMemberGroup();
        $memberId = $this->createChannelApiMember(memberOverride: [
            'requestedGroupId' => $requestedMemberGroup->getId(),
            'languageId' => $languageId,
        ]);

        $eventDispatcher = $this->getContainer()->get('event_dispatcher');
        static::assertInstanceOf(TraceableEventDispatcher::class, $eventDispatcher);
        $controller = $this->createController($eventDispatcher);

        $this->addEventListener(
            $eventDispatcher,
            MemberGroupRegistrationAccepted::class,
            function (MemberGroupRegistrationAccepted $event) use ($languageId): void {
                static::assertSame($languageId, $event->getContext()->getLanguageId());
            }
        );

        $request = new Request();
        $request->request->add(['memberIds' => [$memberId]]);

        $controller->accept($request, Context::createDefaultContext());
    }

    public function testDeclineWithInactiveMember(): void
    {
        $requestedMemberGroup = $this->createMemberGroup();
        $memberId = $this->createChannelApiMember(memberOverride: [
            'requestedGroupId' => $requestedMemberGroup->getId(),
            'active' => false,
        ]);

        $eventDispatcher = $this->getContainer()->get('event_dispatcher');
        static::assertInstanceOf(TraceableEventDispatcher::class, $eventDispatcher);
        $controller = $this->createController($eventDispatcher);

        $request = new Request();
        $request->request->add(['memberIds' => [$memberId]]);

        $controller->decline($request, Context::createDefaultContext());

        $memberResult = $this->fetchMemberById($memberId);
        static::assertInstanceOf(MemberEntity::class, $memberResult);
        static::assertFalse($memberResult->getActive());
        static::assertNull($memberResult->getRequestedGroupId());
    }

    public function testDeclineDispatchesMemberLanguageContext(): void
    {
        $languageId = $this->createLanguage();
        $requestedMemberGroup = $this->createMemberGroup();
        $memberId = $this->createChannelApiMember(memberOverride: [
            'requestedGroupId' => $requestedMemberGroup->getId(),
            'languageId' => $languageId,
        ]);

        $eventDispatcher = $this->getContainer()->get('event_dispatcher');
        static::assertInstanceOf(TraceableEventDispatcher::class, $eventDispatcher);
        $controller = $this->createController($eventDispatcher);

        $this->addEventListener(
            $eventDispatcher,
            MemberGroupRegistrationDeclined::class,
            function (MemberGroupRegistrationDeclined $event) use ($languageId): void {
                static::assertSame($languageId, $event->getContext()->getLanguageId());
            }
        );

        $request = new Request();
        $request->request->add(['memberIds' => [$memberId]]);

        $controller->decline($request, Context::createDefaultContext());
    }

    private function createMemberGroup(): MemberGroupEntity
    {
        $memberGroup = new MemberGroupEntity();
        $memberGroup->setId(Uuid::randomHex());
        $memberGroup->setName(self::B2B_MEMBER_GROUP_NAME);
        $memberGroup->setRegistrationActive(true);
        $this->getContainer()->get('member_group.repository')
            ->create([$memberGroup->jsonSerialize()], Context::createDefaultContext());

        return $memberGroup;
    }

    private function createController(TraceableEventDispatcher $eventDispatcher): MemberGroupRegistrationActionController
    {
        return new MemberGroupRegistrationActionController(
            $this->getContainer()->get('member.repository'),
            $this->getContainer()->get('member_group.repository'),
            $eventDispatcher,
        );
    }

    private function fetchMemberById(string $memberId): ?MemberEntity
    {
        $criteria = new Criteria([$memberId]);

        $member = $this->getContainer()->get('member.repository')
            ->search($criteria, Context::createDefaultContext())
            ->getEntities()
            ->first();

        \assert($member === null || $member instanceof MemberEntity);

        return $member;
    }

    private function createLanguage(): string
    {
        $languageId = Uuid::randomHex();

        $this->getContainer()->get('language.repository')->create(
            [[
                'id' => $languageId,
                'name' => \sprintf('test-language-%s', $languageId),
                'localeId' => $this->getLocaleIdOfSystemLanguage(),
                'parentId' => Defaults::LANGUAGE_SYSTEM,
                'active' => true,
                'channels' => [
                    ['id' => TestDefaults::CHANNEL],
                ],
                'channelDefaultAssignments' => [
                    ['id' => TestDefaults::CHANNEL],
                ],
            ]],
            Context::createDefaultContext()
        );

        return $languageId;
    }
}
