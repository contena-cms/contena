<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\User\Subscriber;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\User\Subscriber\UserSalutationSubscriber;
use Contena\Core\System\User\UserEvents;

/**
 * @internal
 */
#[CoversClass(UserSalutationSubscriber::class)]
class UserSalutationSubscriberTest extends TestCase
{
    private Connection&Stub $connection;

    private UserSalutationSubscriber $salutationSubscriber;

    protected function setUp(): void
    {
        $this->connection = static::createStub(Connection::class);

        $this->salutationSubscriber = new UserSalutationSubscriber($this->connection);
    }

    public function testGetSubscribedEvents(): void
    {
        static::assertSame([
            UserEvents::CUSTOMER_WRITTEN_EVENT => 'setDefaultSalutation',
            UserEvents::CUSTOMER_ADDRESS_WRITTEN_EVENT => 'setDefaultSalutation',
        ], $this->salutationSubscriber->getSubscribedEvents());
    }

    public function testSkip(): void
    {
        $writeResults = [
        ];

        $event = new EntityWrittenEvent(
            'user',
            $writeResults,
            Context::createDefaultContext(),
            [],
        );

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('executeStatement');

        $this->createSubscriber($connection)->setDefaultSalutation($event);
    }

    public function testDefaultSalutation(): void
    {
        $userId = Uuid::randomHex();

        $writeResults = [new EntityWriteResult('created-id', ['id' => $userId], 'user', EntityWriteResult::OPERATION_INSERT)];

        $event = new EntityWrittenEvent(
            'user',
            $writeResults,
            Context::createDefaultContext(),
            [],
        );

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('executeStatement')
            ->willReturnCallback(static function ($sql, $params) use ($userId): int {
                static::assertSame($params, [
                    'id' => Uuid::fromHexToBytes($userId),
                    'notSpecified' => 'not_specified',
                ]);

                static::assertSame('
                UPDATE `user`
                SET `salutation_id` = (
                    SELECT `id`
                    FROM `salutation`
                    WHERE `salutation_key` = :notSpecified
                    LIMIT 1
                )
                WHERE `id` = :id AND `salutation_id` is NULL
            ', $sql);

                return 1;
            });

        $this->createSubscriber($connection)->setDefaultSalutation($event);
    }

    private function createSubscriber(Connection $connection): UserSalutationSubscriber
    {
        return new UserSalutationSubscriber($connection);
    }
}
