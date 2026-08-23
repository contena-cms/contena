<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\System\Member\Subscriber;

use PHPUnit\Framework\TestCase;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Member\Event\MemberDeletedEvent;
use Contena\Core\System\Member\MemberCollection;
use Contena\Core\Test\TestDefaults;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @internal
 */
class MemberBeforeDeleteSubscriberTest extends TestCase
{
    use AdminFunctionalTestBehaviour;

    /**
     * @var EntityRepository<MemberCollection>
     */
    private EntityRepository $memberRepository;

    protected function setUp(): void
    {
        $this->memberRepository = static::getContainer()->get('member.repository');
    }

    public function testMemberDeletedEventDispatched(): void
    {
        $email1 = Uuid::randomHex() . '@example.com';
        $email2 = Uuid::randomHex() . '@example.com';

        $memberId1 = $this->createMember($email1);
        $memberId2 = $this->createMember($email2);

        $context = Context::createDefaultContext();

        $caughtEvents = [];

        $listenerClosure = static function (Event $event) use (&$caughtEvents): void {
            $caughtEvents[] = $event;
        };

        static::getContainer()->get('event_dispatcher')->addListener(MemberDeletedEvent::class, $listenerClosure);

        $this->memberRepository->delete([
            ['id' => $memberId1],
            ['id' => $memberId2],
        ], $context);

        static::assertCount(2, $caughtEvents);

        foreach ($caughtEvents as $event) {
            static::assertInstanceOf(MemberDeletedEvent::class, $event);
            static::assertContains($event->getMemberId(), [$memberId1, $memberId2]);
        }
    }

    private function createMember(string $email): string
    {
        $memberId = Uuid::randomHex();

        $member = [
            'id' => $memberId,
            'channelId' => TestDefaults::CHANNEL,
            'languageId' => Defaults::LANGUAGE_SYSTEM,
            'groupId' => TestDefaults::FALLBACK_MEMBER_GROUP,
            'email' => $email,
            'password' => TestDefaults::HASHED_PASSWORD,
            'name' => 'Max Mustermann',
            'memberNumber' => Uuid::randomHex(),
            'active' => true,
        ];

        $this->memberRepository->create([$member], Context::createDefaultContext());

        return $memberId;
    }
}
