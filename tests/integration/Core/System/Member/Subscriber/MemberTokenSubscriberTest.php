<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\System\Member\Subscriber;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Test\TestCaseBase\BasicTestDataBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\RequestStackTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Member\MemberCollection;
use Contena\Core\System\Member\MemberEntity;
use Contena\Core\Test\Generator;
use Contena\Core\Test\TestDefaults;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * @internal
 */
class MemberTokenSubscriberTest extends TestCase
{
    use BasicTestDataBehaviour;
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;
    use RequestStackTestBehaviour;

    private Connection $connection;

    /**
     * @var EntityRepository<MemberCollection>
     */
    private EntityRepository $memberRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = static::getContainer()->get(Connection::class);
        $this->memberRepository = static::getContainer()->get('member.repository');
    }

    public function testMemberTokenSubscriber(): void
    {
        $memberId = $this->createMember();

        $this->connection->insert('channel_api_context', [
            'member_id' => Uuid::fromHexToBytes($memberId),
            'token' => 'test',
            'channel_id' => Uuid::fromHexToBytes(TestDefaults::CHANNEL),
            'updated_at' => new \DateTime()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'payload' => '{"memberId": "1234"}',
        ]);

        $this->memberRepository->update([
            [
                'id' => $memberId,
                'password' => 'fooo12345',
            ],
        ], Context::createDefaultContext());

        static::assertSame(
            [
                'memberId' => null,
            ],
            json_decode((string) $this->connection->fetchOne('SELECT payload FROM channel_api_context WHERE token = "test"'), true, 512, \JSON_THROW_ON_ERROR)
        );
    }

    public function testMemberTokenSubscriberFrontendShouldStillBeLoggedIn(): void
    {
        $memberId = $this->createMember();

        $request = Request::create('/');
        $request->setSession(new Session(new MockArraySessionStorage()));

        $member = new MemberEntity();
        $member->setId($memberId);
        $context = Generator::generateChannelContext(token: 'test', member: $member);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT, $context);

        static::getContainer()->get('request_stack')->push($request);

        $this->connection->insert('channel_api_context', [
            'member_id' => Uuid::fromHexToBytes($memberId),
            'token' => 'test',
            'channel_id' => Uuid::fromHexToBytes(TestDefaults::CHANNEL),
            'updated_at' => new \DateTime()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'payload' => '{"memberId": "1234"}',
        ]);

        $this->memberRepository->update([
            [
                'id' => $memberId,
                'password' => 'fooo12345',
            ],
        ], Context::createDefaultContext());

        $newToken = $context->getToken();
        static::assertNotSame('test', $newToken);

        static::assertSame(
            [
                'memberId' => '1234',
            ],
            json_decode((string) $this->connection->fetchOne('SELECT payload FROM channel_api_context WHERE token = ?', [$newToken]), true, 512, \JSON_THROW_ON_ERROR)
        );
    }

    public function testDeleteMember(): void
    {
        $memberId = $this->createMember();

        $this->connection->insert('channel_api_context', [
            'member_id' => Uuid::fromHexToBytes($memberId),
            'token' => 'test',
            'channel_id' => Uuid::fromHexToBytes(TestDefaults::CHANNEL),
            'updated_at' => new \DateTime()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'payload' => '{"memberId": "1234"}',
        ]);

        $this->memberRepository->delete([
            [
                'id' => $memberId,
            ],
        ], Context::createDefaultContext());

        static::assertCount(0, $this->connection->fetchAllAssociative('SELECT * FROM channel_api_context WHERE token = ?', ['test']));
    }

    private function createMember(): string
    {
        $memberId = Uuid::randomHex();

        $member = [
            'id' => $memberId,
            'name' => 'Max Mustermann',
            'memberNumber' => Uuid::randomHex(),
            'email' => Uuid::randomHex() . '@example.com',
            'password' => TestDefaults::HASHED_PASSWORD,
            'groupId' => TestDefaults::FALLBACK_MEMBER_GROUP,
            'channelId' => TestDefaults::CHANNEL,
            'languageId' => Defaults::LANGUAGE_SYSTEM,
            'active' => true,
        ];

        $this->memberRepository->upsert([$member], Context::createDefaultContext());

        return $memberId;
    }
}
