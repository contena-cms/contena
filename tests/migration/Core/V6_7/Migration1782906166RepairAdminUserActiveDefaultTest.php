<?php declare(strict_types=1);

namespace Contena\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Defaults;
use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\Framework\Util\Database\TableHelper;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Migration\V6_7\Migration1782906166RepairUserActiveDefault;

/**
 * @internal
 */
#[CoversClass(Migration1782906166RepairUserActiveDefault::class)]
class Migration1782906166RepairUserActiveDefaultTest extends TestCase
{
    private Connection $connection;


    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
        $this->migration = new Migration1782906166RepairUserActiveDefault();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1782906166, $this->migration->getCreationTimestamp());
    }

    public function testMigrationRepairsInactiveUsersWhenDefaultIsFalse(): void
    {
        $originalDefault = $this->activeColumnDefault();
        $inactiveUserIds = $this->fetchInactiveUserIds();
        $adminUserId = null;

        try {
            $this->setActiveColumnDefault(0);
            $adminUserId = $this->createUser();

            static::assertSame('0', $this->activeColumnDefault());
            static::assertFalse($this->isUserActive($adminUserId));

            $this->migration->update($this->connection);
            $this->migration->update($this->connection);

            static::assertSame('1', $this->activeColumnDefault());
            static::assertTrue($this->isUserActive($adminUserId));
        } finally {
            $this->restoreInactiveUsers($inactiveUserIds);

            if ($adminUserId !== null) {
                $this->deleteUser($adminUserId);
            }

            $this->setActiveColumnDefault((int) $originalDefault);
        }
    }

    public function testMigrationDoesNothingWhenDefaultIsAlreadyTrue(): void
    {
        $originalDefault = $this->activeColumnDefault();
        $adminUserId = null;

        try {
            $this->setActiveColumnDefault(1);
            $adminUserId = $this->createUser(active: 0);

            $this->migration->update($this->connection);

            static::assertSame('1', $this->activeColumnDefault());
            static::assertFalse($this->isUserActive($adminUserId));
        } finally {
            if ($adminUserId !== null) {
                $this->deleteUser($adminUserId);
            }

            $this->setActiveColumnDefault((int) $originalDefault);
        }
    }

    private function activeColumnDefault(): string
    {
        return (string) TableHelper::getColumnOfTable($this->connection, 'admin_user', 'active')->defaultValue;
    }

    private function setActiveColumnDefault(int $default): void
    {
        $this->connection->executeStatement(
            \sprintf('ALTER TABLE `admin_user` MODIFY COLUMN `active` TINYINT(1) NOT NULL DEFAULT %d', $default)
        );
    }

    /**
     * @return list<string>
     */
    private function fetchInactiveUserIds(): array
    {
        $ids = $this->connection->fetchFirstColumn('SELECT LOWER(HEX(`id`)) FROM `admin_user` WHERE `active` = 0');

        foreach ($ids as $id) {
            static::assertIsString($id);
        }

        /** @var list<string> $ids */
        return $ids;
    }

    /**
     * @param list<string> $ids
     */
    private function restoreInactiveUsers(array $ids): void
    {
        foreach ($ids as $id) {
            $this->connection->update('admin_user', ['active' => 0], ['id' => Uuid::fromHexToBytes($id)]);
        }
    }

    private function createUser(?int $active = null): string
    {
        $id = Uuid::randomBytes();
        $suffix = Uuid::randomHex();
        $data = [
            'id' => $id,
            'username' => 'migration-active-' . $suffix,
            'password' => password_hash('password123', \PASSWORD_DEFAULT),
            'first_name' => 'Migration',
            'last_name' => 'Active',
            'email' => 'migration-active-' . $suffix . '@example.com',
            'locale_id' => $this->fetchLocaleId(),
            'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ];

        if ($active !== null) {
            $data['active'] = $active;
        }

        $this->connection->insert('admin_user', $data);

        return $id;
    }

    private function fetchLocaleId(): string
    {
        $localeId = $this->connection->fetchOne('SELECT `id` FROM `locale` LIMIT 1');

        static::assertIsString($localeId);

        return $localeId;
    }

    private function isUserActive(string $adminUserId): bool
    {
        return (bool) $this->connection->fetchOne(
            'SELECT `active` FROM `admin_user` WHERE `id` = :id',
            ['id' => $adminUserId]
        );
    }

    private function deleteUser(string $adminUserId): void
    {
        $this->connection->delete('admin_user', ['id' => $adminUserId]);
    }
}
