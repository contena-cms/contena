<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Api\EventListener\Authentication;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Api\EventListener\Authentication\AdminUserCredentialsChangedSubscriber;
use Contena\Core\Framework\Api\OAuth\AuthCodeRepository;
use Contena\Core\Framework\Api\OAuth\RefreshTokenRepository;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\AdminUser\AdminUserDefinition;
use Symfony\Component\Clock\MockClock;

/**
 * @internal
 */
#[CoversClass(AdminUserCredentialsChangedSubscriber::class)]
class AdminUserCredentialsChangedSubscriberTest extends TestCase
{
    private const USER_ID = '018d9dcce00a704ab034ec0694981ed5';

    public function testDeactivatingUserInvalidatesTokens(): void
    {
        $subscriber = new AdminUserCredentialsChangedSubscriber(
            $this->refreshTokenRepositoryExpectingRevocation(),
            $this->authCodeRepositoryExpectingRevocation(),
            $this->connectionExpectingTimestampUpdate(),
            new MockClock('2026-06-30 12:00:00')
        );

        $subscriber->onUserWritten($this->event([
            'id' => self::USER_ID,
            'active' => false,
        ]));
    }

    public function testActivatingUserDoesNotInvalidateTokens(): void
    {
        $refreshTokenRepository = $this->createMock(RefreshTokenRepository::class);
        $refreshTokenRepository->expects($this->never())->method('revokeRefreshTokensForUser');
        $authCodeRepository = $this->createMock(AuthCodeRepository::class);
        $authCodeRepository->expects($this->never())->method('revokeAuthCodesForUser');

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('update');

        $subscriber = new AdminUserCredentialsChangedSubscriber(
            $refreshTokenRepository,
            $authCodeRepository,
            $connection,
            new MockClock('2026-06-30 12:00:00')
        );

        $subscriber->onUserWritten($this->event([
            'id' => self::USER_ID,
            'active' => true,
        ]));
    }

    public function testChangingPasswordStillInvalidatesTokens(): void
    {
        $subscriber = new AdminUserCredentialsChangedSubscriber(
            $this->refreshTokenRepositoryExpectingRevocation(),
            $this->authCodeRepositoryExpectingRevocation(),
            $this->connectionExpectingTimestampUpdate(),
            new MockClock('2026-06-30 12:00:00')
        );

        $subscriber->onUserWritten($this->event([
            'id' => self::USER_ID,
            'password' => 'changed',
        ]));
    }

    public function testDeletingUserInvalidatesTokens(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('update');

        $subscriber = new AdminUserCredentialsChangedSubscriber(
            $this->refreshTokenRepositoryExpectingRevocation(),
            $this->authCodeRepositoryExpectingRevocation(),
            $connection,
            new MockClock('2026-06-30 12:00:00')
        );

        $subscriber->onUserDeleted(new EntityDeletedEvent(
            AdminUserDefinition::ENTITY_NAME,
            [new EntityWriteResult(self::USER_ID, [], AdminUserDefinition::ENTITY_NAME, EntityWriteResult::OPERATION_DELETE)],
            Context::createDefaultContext()
        ));
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function event(array $payload): EntityWrittenEvent
    {
        return new EntityWrittenEvent(
            AdminUserDefinition::ENTITY_NAME,
            [new EntityWriteResult(self::USER_ID, $payload, AdminUserDefinition::ENTITY_NAME, EntityWriteResult::OPERATION_UPDATE)],
            Context::createDefaultContext()
        );
    }

    private function refreshTokenRepositoryExpectingRevocation(): RefreshTokenRepository
    {
        $refreshTokenRepository = $this->createMock(RefreshTokenRepository::class);
        $refreshTokenRepository
            ->expects($this->once())
            ->method('revokeRefreshTokensForUser')
            ->with(self::USER_ID);

        return $refreshTokenRepository;
    }

    private function authCodeRepositoryExpectingRevocation(): AuthCodeRepository
    {
        $authCodeRepository = $this->createMock(AuthCodeRepository::class);
        $authCodeRepository
            ->expects($this->once())
            ->method('revokeAuthCodesForUser')
            ->with(self::USER_ID);

        return $authCodeRepository;
    }

    private function connectionExpectingTimestampUpdate(): Connection
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('update')
            ->with(
                'admin_user',
                ['last_updated_password_at' => '2026-06-30 12:00:00.000'],
                ['id' => Uuid::fromHexToBytes(self::USER_ID)]
            );

        return $connection;
    }
}
