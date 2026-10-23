<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\Sso\Helper;

use Doctrine\DBAL\Connection;
use Contena\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
class FakeUserInstaller
{
    public function __construct(
        private readonly Connection $connection
    ) {
    }

    public function installBaseUserData(string $id, string $email): void
    {
        $byteLocaleId = $this->connection->fetchOne('SELECT `id` FROM `locale` WHERE `code` = :code', ['code' => 'en-GB']);

        $id = Uuid::fromHexToBytes($id);

        $sql = 'INSERT INTO `admin_user` (`id`, `username`, `password`, `first_name`, `last_name`, `title`, `email`, `active`, `admin`, `avatar_id`, `locale_id`, `store_token`, `last_updated_password_at`, `time_zone`, `custom_fields`, `created_at`, `updated_at`) VALUES
                (?, ?, \'$53CR3D9422W0RD\', ?, ?, \'Baz\', ?, 0, 1, NULL, ?, NULL, \'2024-01-01 08:00:00.000\', \'Europe/Berlin\', NULL, \'2024-01-01 08:00:00.000\', NULL);';
        $this->connection->executeStatement($sql, [$id, $email, $email, $email, $email, $byteLocaleId]);
    }

    public function installTokenUser(string $admin_userId, string $subject): void
    {
        $id = Uuid::randomBytes();
        $admin_userId = Uuid::fromHexToBytes($admin_userId);

        $sql = 'INSERT INTO `oauth_user` (`id`, `admin_user_id`, `admin_user_sub`, `token`, `expiry`, `created_at`, `updated_at`) VALUES
                (?, ?, ?, \'{"token": "invalid", "refreshToken": "invalid"}\', \'2024-01-01 08:00:00.000\', \'2024-01-01 08:00:00.000\', NULL);';
        $this->connection->executeStatement($sql, [$id, $admin_userId, $subject]);
    }
}
