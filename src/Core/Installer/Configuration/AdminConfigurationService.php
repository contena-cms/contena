<?php declare(strict_types=1);

namespace Contena\Core\Installer\Configuration;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Contena\Core\Maintenance\AdminUser\Service\AdminUserProvisioner;

/**
 * @internal
 */
class AdminConfigurationService
{
    public function __construct(private readonly ClockInterface $clock)
    {
    }

    /**
     * @param array{
     *     username: string,
     *     password: string,
     *     firstName?: string,
     *     lastName?: string,
     *     email?: string,
     *     localeId?: string,
     *     admin?: bool
     * } $admin_user
     */
    public function createAdmin(array $admin_user, Connection $connection): void
    {
        $admin_userProvisioner = new AdminUserProvisioner($connection, $this->clock);
        $admin_userName = $admin_user['username'];
        $password = $admin_user['password'];
        unset($admin_user['username'], $admin_user['password']);

        $admin_userProvisioner->provision(
            $admin_userName,
            $password,
            $admin_user,
        );
    }
}
