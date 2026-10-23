<?php declare(strict_types=1);

namespace Contena\Tests\Migration\Core\V6_6;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Defaults;
use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Migration\V6_6\Migration1663402950SetDoubleOptinUserActive;
use Contena\Core\Test\TestDefaults;

/**
 * @internal
 */
#[CoversClass(Migration1663402950SetDoubleOptinUserActive::class)]
class Migration1663402950SetDoubleOptinUserActiveTest extends TestCase
{
    use MigrationTestTrait;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1663402950, (new Migration1663402950SetDoubleOptinUserActive())->getCreationTimestamp());
    }

    public function testMigration(): void
    {
        $userId = Uuid::randomBytes();
        $countAffectedRows = $this->addUserWithDoubleOptInButNotConfirmed($userId);
        static::assertSame(1, $countAffectedRows);
        static::assertFalse($this->checkUserIsActive($userId));

        $migration = new Migration1663402950SetDoubleOptinUserActive();
        $migration->update($this->connection);
        static::assertTrue($this->checkUserIsActive($userId));
        static::assertSame(1663402950, $migration->getCreationTimestamp());
    }

    public function testCanBeExecutedMultipleTimes(): void
    {
        $this->testMigration();

        $migration = new Migration1663402950SetDoubleOptinUserActive();
        $migration->update($this->connection);
    }

    private function addUserWithDoubleOptInButNotConfirmed(string $userId): int|string
    {
        $userAddressId = Uuid::randomBytes();
        $now = new \DateTimeImmutable();

        $data = [
            'id' => $userId,
            'user_group_id' => Uuid::fromHexToBytes(TestDefaults::FALLBACK_CUSTOMER_GROUP),
            'channel_id' => Uuid::fromHexToBytes(TestDefaults::CHANNEL),
            'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
            'default_billing_address_id' => $userAddressId,
            'default_shipping_address_id' => $userAddressId,
            'user_number' => '123',
            'first_name' => 'Bar',
            'last_name' => 'Foo',
            'email' => 'foo@bar.com',
            'active' => 0,
            'double_opt_in_registration' => 1,
            'double_opt_in_email_sent_date' => $now->format('Y-m-d H:i:s'),
            'guest' => 0,
            'created_at' => $now->format('Y-m-d H:i:s'),
        ];

        return $this->connection->insert('user', $data);
    }

    private function checkUserIsActive(string $userId): bool
    {
        return (bool) $this->connection->fetchOne(
            'SELECT active FROM  user WHERE id = :userId',
            ['userId' => $userId]
        );
    }
}
