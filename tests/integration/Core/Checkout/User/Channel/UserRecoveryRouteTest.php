<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\System\User\Channel;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Contena\Core\System\User\Exception\UserNotFoundByHashException;
use Contena\Core\System\User\Channel\UserRecoveryIsExpiredRoute;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Util\Random;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\Framework\Validation\Exception\ConstraintViolationException;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Contena\Core\Test\Integration\Traits\UserTestTrait;
use Contena\Core\Test\TestDefaults;

/**
 * @internal
 */
#[Group('store-api')]
class UserRecoveryRouteTest extends TestCase
{
    use UserTestTrait;
    use IntegrationTestBehaviour;

    private string $hash;

    private string $hashId;

    protected function setUp(): void
    {
        $email = Uuid::randomHex() . '@example.com';
        $userId = $this->createUser($email);

        $this->hash = Random::getAlphanumericString(32);
        $this->hashId = Uuid::randomHex();

        static::getContainer()->get('user_recovery.repository')->create([
            [
                'id' => $this->hashId,
                'userId' => $userId,
                'hash' => $this->hash,
            ],
        ], Context::createDefaultContext());
    }

    public function testNotDecorated(): void
    {
        $userRecoveryRoute = static::getContainer()->get(UserRecoveryIsExpiredRoute::class);

        static::expectException(DecorationPatternException::class);
        $userRecoveryRoute->getDecorated();
    }

    public function testGetUserRecoveryNotFound(): void
    {
        $userRecoveryRoute = static::getContainer()->get(UserRecoveryIsExpiredRoute::class);

        $token = Uuid::randomHex();

        $context = static::getContainer()->get(ChannelContextFactory::class)->create($token, TestDefaults::CHANNEL);

        static::expectException(UserNotFoundByHashException::class);
        $userRecoveryRoute->load(new RequestDataBag(['hash' => Random::getAlphanumericString(32)]), $context);
    }

    public function testGetUserRecoveryInvalidHash(): void
    {
        $userRecoveryRoute = static::getContainer()->get(UserRecoveryIsExpiredRoute::class);

        $token = Uuid::randomHex();

        $context = static::getContainer()->get(ChannelContextFactory::class)->create($token, TestDefaults::CHANNEL);

        static::expectException(ConstraintViolationException::class);
        $userRecoveryRoute->load(new RequestDataBag(['hash' => 'ThisIsAWrongHash']), $context);
    }

    public function testGetUserRecovery(): void
    {
        $userRecoveryRoute = static::getContainer()->get(UserRecoveryIsExpiredRoute::class);

        $token = Uuid::randomHex();

        $context = static::getContainer()->get(ChannelContextFactory::class)->create($token, TestDefaults::CHANNEL);

        $userRecoveryResponse = $userRecoveryRoute->load(new RequestDataBag(['hash' => $this->hash]), $context);

        static::assertFalse($userRecoveryResponse->isExpired());
    }

    public function testGetUserRecoveryExpired(): void
    {
        $userRecoveryRoute = static::getContainer()->get(UserRecoveryIsExpiredRoute::class);

        $token = Uuid::randomHex();

        $context = static::getContainer()->get(ChannelContextFactory::class)->create($token, TestDefaults::CHANNEL);

        static::getContainer()->get(Connection::class)->update(
            'user_recovery',
            [
                'created_at' => (new \DateTime())->sub(new \DateInterval('PT3H'))->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ],
            [
                'id' => Uuid::fromHexToBytes($this->hashId),
            ]
        );

        $userRecoveryResponse = $userRecoveryRoute->load(new RequestDataBag(['hash' => $this->hash]), $context);

        static::assertTrue($userRecoveryResponse->isExpired());
    }
}
