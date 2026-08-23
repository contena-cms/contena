<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\System\Member\Channel;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Routing\RoutingException;
use Contena\Core\Framework\Test\TestCaseBase\ChannelApiTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Member\Event\MemberDeletedEvent;
use Contena\Core\System\Member\MemberCollection;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Contena\Core\Test\TestDefaults;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Contracts\EventDispatcher\Event;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Group('channel-api')]
class DeleteMemberRouteTest extends TestCase
{
    use ChannelApiTestBehaviour;
    use IntegrationTestBehaviour;

    private KernelBrowser $browser;

    private IdsCollection $ids;

    /**
     * @var EntityRepository<MemberCollection>
     */
    private EntityRepository $memberRepository;

    /**
     * @var callable
     */
    private $callbackFn;

    /**
     * @var array<class-string, Event>
     */
    private array $events;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();

        $this->browser = $this->createCustomChannelBrowser([
            'id' => $this->ids->create('channel'),
        ]);

        $this->memberRepository = static::getContainer()->get('member.repository');

        $this->callbackFn = function (Event $event): void {
            $this->events[$event::class] = $event;
        };

        $this->events = [];
    }

    public function testNotLoggedIn(): void
    {
        $this->browser
            ->request(
                'DELETE',
                '/channel-api/account/member',
                [
                ]
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('errors', $response);
        static::assertSame(RoutingException::CHANNEL_MEMBER_NOT_LOGGED_IN, $response['errors'][0]['code']);
    }

    public function testDeleteAValidMember(): void
    {
        $dispatcher = static::getContainer()->get(EventDispatcherInterface::class);
        $dispatcher->addListener(MemberDeletedEvent::class, $this->callbackFn);

        static::assertArrayNotHasKey(
            MemberDeletedEvent::class,
            $this->events,
            'MemberDeletedEvent was dispatched but should not yet.'
        );

        $email = Uuid::randomHex() . '@example.com';
        $id = $this->createMember($email);

        $this->browser
            ->request(
                'POST',
                '/channel-api/account/login',
                [
                    'email' => $email,
                    'password' => 'contenaAdmin',
                ]
            );

        $response = $this->browser->getResponse();

        // After login successfully, the context token will be set in the header
        $contextToken = $response->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN) ?? '';
        static::assertNotEmpty($contextToken);

        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $contextToken);

        $this->browser
            ->request(
                'DELETE',
                '/channel-api/account/member',
                [
                ]
            );

        static::assertSame(204, $this->browser->getResponse()->getStatusCode());

        $criteria = new Criteria([$id]);
        $member = $this->memberRepository->searchIds($criteria, Context::createDefaultContext())->firstId();
        static::assertNull($member);

        static::assertArrayHasKey(MemberDeletedEvent::class, $this->events);
        $memberDeletedEvent = $this->events[MemberDeletedEvent::class];
        static::assertInstanceOf(MemberDeletedEvent::class, $memberDeletedEvent);

        $dispatcher->removeListener(MemberDeletedEvent::class, $this->callbackFn);
    }

    private function createMember(string $email): string
    {
        $memberId = Uuid::randomHex();

        $member = [
            'id' => $memberId,
            'channelId' => $this->ids->get('channel'),
            'languageId' => Defaults::LANGUAGE_SYSTEM,
            'groupId' => TestDefaults::FALLBACK_MEMBER_GROUP,
            'email' => $email,
            'password' => TestDefaults::HASHED_PASSWORD,
            'name' => 'Max Member',
            'memberNumber' => $memberId,
            'active' => true,
        ];

        $this->memberRepository->create([$member], Context::createDefaultContext());

        return $memberId;
    }
}
