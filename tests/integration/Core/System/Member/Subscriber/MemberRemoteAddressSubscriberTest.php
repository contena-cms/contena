<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\System\Member\Subscriber;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Member\Event\MemberLoginEvent;
use Contena\Core\System\Member\MemberCollection;
use Contena\Core\System\Member\MemberEntity;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Core\Test\Generator;
use Contena\Core\Test\TestDefaults;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
class MemberRemoteAddressSubscriberTest extends TestCase
{
    use IntegrationTestBehaviour;

    private const string STORE_PLAIN_IP_ADDRESS = 'core.loginRegistration.memberIpAddressesNotAnonymously';

    /**
     * @var EntityRepository<MemberCollection>
     */
    private EntityRepository $memberRepository;

    protected function setUp(): void
    {
        $this->memberRepository = static::getContainer()->get('member.repository');
    }

    public static function providerIPs(): \Generator
    {
        yield 'enabled, should anonymize' => [
            false,
            '94.31.83.28',
            '94.31.83.0',
        ];

        yield 'enabled, should not anonymize' => [
            true,
            '94.31.83.28',
            '94.31.83.28',
        ];
    }

    #[DataProvider('providerIPs')]
    public function testRequest(bool $storePlainIpAddress, string $clientIp, string $expectedIp): void
    {
        $member = $this->createMember();

        static::getContainer()->get(SystemConfigService::class)->set(
            self::STORE_PLAIN_IP_ADDRESS,
            $storePlainIpAddress,
        );

        $request = new Request();
        $request->server->set('REMOTE_ADDR', $clientIp);
        static::getContainer()->get('request_stack')->push($request);

        static::getContainer()->get('event_dispatcher')->dispatch(new MemberLoginEvent(
            Generator::generateChannelContext(member: $member),
            $member,
            'test',
        ));

        $updatedMember = $this->memberRepository
            ->search(new Criteria([$member->getId()]), Context::createDefaultContext())
            ->getEntities()
            ->first();

        static::assertInstanceOf(MemberEntity::class, $updatedMember);
        static::assertSame($expectedIp, $updatedMember->getRemoteAddress());
    }

    private function createMember(): MemberEntity
    {
        $memberId = Uuid::randomHex();

        $this->memberRepository->create([[
            'id' => $memberId,
            'channelId' => TestDefaults::CHANNEL,
            'languageId' => Defaults::LANGUAGE_SYSTEM,
            'groupId' => TestDefaults::FALLBACK_MEMBER_GROUP,
            'email' => Uuid::randomHex() . '@example.com',
            'password' => TestDefaults::HASHED_PASSWORD,
            'name' => 'Max Mustermann',
            'memberNumber' => Uuid::randomHex(),
            'active' => true,
        ]], Context::createDefaultContext());

        $member = $this->memberRepository
            ->search(new Criteria([$memberId]), Context::createDefaultContext())
            ->getEntities()
            ->first();

        static::assertInstanceOf(MemberEntity::class, $member);

        return $member;
    }
}
