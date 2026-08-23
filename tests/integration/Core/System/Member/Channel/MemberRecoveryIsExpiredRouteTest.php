<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\System\Member\Channel;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Util\Random;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\Framework\Validation\Exception\ConstraintViolationException;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Contena\Core\System\Member\Channel\MemberRecoveryIsExpiredRoute;
use Contena\Core\System\Member\Exception\MemberNotFoundByHashException;
use Contena\Core\Test\TestDefaults;

/**
 * @internal
 */
#[Group('channel-api')]
class MemberRecoveryIsExpiredRouteTest extends TestCase
{
    use IntegrationTestBehaviour;

    private string $hash;

    private string $hashId;

    protected function setUp(): void
    {
        $memberId = Uuid::randomHex();

        static::getContainer()->get('member.repository')->create([[
            'id' => $memberId,
            'channelId' => TestDefaults::CHANNEL,
            'languageId' => Defaults::LANGUAGE_SYSTEM,
            'groupId' => TestDefaults::FALLBACK_MEMBER_GROUP,
            'email' => Uuid::randomHex() . '@example.com',
            'password' => TestDefaults::HASHED_PASSWORD,
            'name' => 'Max Member',
            'memberNumber' => Uuid::randomHex(),
            'active' => true,
        ]], Context::createDefaultContext());

        $this->hash = Random::getAlphanumericString(32);
        $this->hashId = Uuid::randomHex();

        static::getContainer()->get('member_recovery.repository')->create([
            [
                'id' => $this->hashId,
                'memberId' => $memberId,
                'hash' => $this->hash,
            ],
        ], Context::createDefaultContext());
    }

    public function testNotDecorated(): void
    {
        $memberRecoveryRoute = static::getContainer()->get(MemberRecoveryIsExpiredRoute::class);

        static::expectException(DecorationPatternException::class);
        $memberRecoveryRoute->getDecorated();
    }

    public function testGetMemberRecoveryNotFound(): void
    {
        $memberRecoveryRoute = static::getContainer()->get(MemberRecoveryIsExpiredRoute::class);

        $context = static::getContainer()->get(ChannelContextFactory::class)->create(Uuid::randomHex(), TestDefaults::CHANNEL);

        static::expectException(MemberNotFoundByHashException::class);
        $memberRecoveryRoute->load(new RequestDataBag(['hash' => Random::getAlphanumericString(32)]), $context);
    }

    public function testGetMemberRecoveryInvalidHash(): void
    {
        $memberRecoveryRoute = static::getContainer()->get(MemberRecoveryIsExpiredRoute::class);

        $context = static::getContainer()->get(ChannelContextFactory::class)->create(Uuid::randomHex(), TestDefaults::CHANNEL);

        static::expectException(ConstraintViolationException::class);
        $memberRecoveryRoute->load(new RequestDataBag(['hash' => 'ThisIsAWrongHash']), $context);
    }

    public function testGetMemberRecovery(): void
    {
        $memberRecoveryRoute = static::getContainer()->get(MemberRecoveryIsExpiredRoute::class);

        $context = static::getContainer()->get(ChannelContextFactory::class)->create(Uuid::randomHex(), TestDefaults::CHANNEL);

        $memberRecoveryResponse = $memberRecoveryRoute->load(new RequestDataBag(['hash' => $this->hash]), $context);

        static::assertFalse($memberRecoveryResponse->isExpired());
    }

    public function testGetMemberRecoveryExpired(): void
    {
        $memberRecoveryRoute = static::getContainer()->get(MemberRecoveryIsExpiredRoute::class);

        $context = static::getContainer()->get(ChannelContextFactory::class)->create(Uuid::randomHex(), TestDefaults::CHANNEL);

        static::getContainer()->get(Connection::class)->update(
            'member_recovery',
            [
                'created_at' => new \DateTime()->sub(new \DateInterval('PT3H'))->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ],
            [
                'id' => Uuid::fromHexToBytes($this->hashId),
            ]
        );

        $memberRecoveryResponse = $memberRecoveryRoute->load(new RequestDataBag(['hash' => $this->hash]), $context);

        static::assertTrue($memberRecoveryResponse->isExpired());
    }
}
