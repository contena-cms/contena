<?php declare(strict_types=1);

namespace Contena\Tests\Migration\Core\V6_8;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\Framework\Util\Database\TableHelper;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Migration\V6_8\Migration1791369987BlogReviewExternalUserNotNull;

/**
 * @internal
 */
#[CoversClass(Migration1791369987BlogReviewExternalUserNotNull::class)]
class Migration1791369987BlogReviewExternalUserNotNullTest extends TestCase
{
    private Connection $connection;

    /**
     * @var list<string>
     */
    private array $reviewIds = [];

    private ?string $userId = null;

    private bool $externalUserWasNotNull;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
        $this->externalUserWasNotNull = TableHelper::getColumnOfTable($this->connection, 'blog_review', 'external_user')->isNotNull;
    }

    protected function tearDown(): void
    {
        foreach ($this->reviewIds as $reviewId) {
            $this->connection->delete('blog_review', ['id' => $reviewId]);
        }

        if ($this->userId !== null) {
            $this->connection->delete('user', ['id' => $this->userId]);
        }

        $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS=1');

        if (!$this->externalUserWasNotNull) {
            $this->connection->executeStatement(
                'ALTER TABLE `blog_review` MODIFY COLUMN `external_user` VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL'
            );
        }
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1791369987, (new Migration1791369987BlogReviewExternalUserNotNull())->getCreationTimestamp());
    }

    public function testUpdateKeepsMissingAuthorsAndTheNullableColumn(): void
    {
        $this->connection->executeStatement(
            'ALTER TABLE `blog_review` MODIFY COLUMN `external_user` VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL'
        );
        $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS=0');

        $guestReview = $this->insertReview(null, null);

        (new Migration1791369987BlogReviewExternalUserNotNull())->update($this->connection);

        static::assertNull($this->fetchExternalUser($guestReview));
        static::assertFalse(TableHelper::getColumnOfTable($this->connection, 'blog_review', 'external_user')->isNotNull);
    }

    public function testUpdateDestructiveFillsMissingAuthorsAndMakesTheColumnNotNull(): void
    {
        $this->connection->executeStatement(
            'ALTER TABLE `blog_review` MODIFY COLUMN `external_user` VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL'
        );
        $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS=0');

        $this->userId = Uuid::randomBytes();
        $this->connection->insert('user', [
            'id' => $this->userId,
            'user_group_id' => Uuid::randomBytes(),
            'channel_id' => Uuid::randomBytes(),
            'language_id' => Uuid::randomBytes(),
            'default_billing_address_id' => Uuid::randomBytes(),
            'default_shipping_address_id' => Uuid::randomBytes(),
            'user_number' => 'review-migration-test',
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'review-migration-test@example.com',
            'created_at' => '2026-01-01 00:00:00.000',
        ]);

        $userReview = $this->insertReview(null, $this->userId);
        $guestReview = $this->insertReview(null, null);
        $namedReview = $this->insertReview('Max', $this->userId);

        $migration = new Migration1791369987BlogReviewExternalUserNotNull();
        $migration->updateDestructive($this->connection);
        $migration->updateDestructive($this->connection);

        static::assertSame('Jane', $this->fetchExternalUser($userReview));
        static::assertSame('', $this->fetchExternalUser($guestReview));
        static::assertSame('Max', $this->fetchExternalUser($namedReview));
        static::assertTrue(TableHelper::getColumnOfTable($this->connection, 'blog_review', 'external_user')->isNotNull);
    }

    private function insertReview(?string $externalUser, ?string $userId): string
    {
        $id = Uuid::randomBytes();
        $this->connection->insert('blog_review', [
            'id' => $id,
            'blog_id' => Uuid::randomBytes(),
            'blog_version_id' => Uuid::randomBytes(),
            'user_id' => $userId,
            'external_user' => $externalUser,
            'points' => 4.0,
            'created_at' => '2026-01-01 00:00:00.000',
        ]);
        $this->reviewIds[] = $id;

        return $id;
    }

    private function fetchExternalUser(string $reviewId): mixed
    {
        return $this->connection->fetchOne('SELECT `external_user` FROM `blog_review` WHERE `id` = :id', ['id' => $reviewId]);
    }
}
