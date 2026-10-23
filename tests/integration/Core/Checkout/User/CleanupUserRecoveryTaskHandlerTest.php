<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\System\User;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Contena\Core\System\User\CleanupUserRecoveryTaskHandler;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Test\Integration\Builder\User\UserBuilder;
use Contena\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
class CleanupUserRecoveryTaskHandlerTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    private CleanupUserRecoveryTaskHandler $handler;

    private Connection $connection;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        parent::setUp();
        /** @var CleanupUserRecoveryTaskHandler $handler */
        $handler = static::getContainer()->get(CleanupUserRecoveryTaskHandler::class);
        $this->handler = $handler;
        $this->connection = static::getContainer()->get(Connection::class);
        $this->ids = new IdsCollection();
    }

    public function testExpiredRecoveryIsDeleted(): void
    {
        $userId = $this->createUser();

        $expiredAt = new \DateTime();
        $expiredAt->modify('-50 hour');
        $this->createUserRecovery($userId, $expiredAt);

        $this->handler->run();

        $count = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM user_recovery WHERE user_id = :userId',
            ['userId' => Uuid::fromHexToBytes($userId)]
        );

        static::assertSame('0', (string) $count);
    }

    public function testNonExpiredRecoveryIsKept(): void
    {
        $userId = $this->createUser();

        $recentAt = new \DateTime();
        $recentAt->modify('-1 hour');
        $this->createUserRecovery($userId, $recentAt);

        $this->handler->run();

        $count = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM user_recovery WHERE user_id = :userId',
            ['userId' => Uuid::fromHexToBytes($userId)]
        );

        static::assertSame('1', (string) $count);
    }

    public function testMixedRecordsOnlyDeletesExpired(): void
    {
        $expiredUserId = $this->createUser();
        $recentUserId = $this->createUser();

        $expiredAt = new \DateTime();
        $expiredAt->modify('-50 hour');
        $this->createUserRecovery($expiredUserId, $expiredAt);

        $recentAt = new \DateTime();
        $recentAt->modify('-30 minutes');
        $this->createUserRecovery($recentUserId, $recentAt);

        $this->handler->run();

        $expiredCount = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM user_recovery WHERE user_id = :userId',
            ['userId' => Uuid::fromHexToBytes($expiredUserId)]
        );
        static::assertSame('0', (string) $expiredCount);

        $recentCount = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM user_recovery WHERE user_id = :userId',
            ['userId' => Uuid::fromHexToBytes($recentUserId)]
        );
        static::assertSame('1', (string) $recentCount);
    }

    private function createUser(): string
    {
        $context = Context::createDefaultContext();
        $userRepository = static::getContainer()->get('user.repository');

        $userNumber = 'TEST-' . Uuid::randomHex();

        $user = (new UserBuilder($this->ids, $userNumber))
            ->add('email', $userNumber . '@example.com');

        $userRepository->create([$user->build()], $context);

        return $this->ids->get($userNumber);
    }

    private function createUserRecovery(string $userId, \DateTime $createdAt): void
    {
        $this->connection->insert('user_recovery', [
            'id' => Uuid::randomBytes(),
            'user_id' => Uuid::fromHexToBytes($userId),
            'hash' => Uuid::randomHex(),
            'created_at' => $createdAt->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }
}
