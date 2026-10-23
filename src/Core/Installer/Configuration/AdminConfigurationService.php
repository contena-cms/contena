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
     * } $adminUser
     */
    public function createAdmin(array $adminUser, Connection $connection): void
    {
        $adminUserProvisioner = new AdminUserProvisioner($connection, $this->clock);
        $adminUserName = $adminUser['username'];
        $password = $adminUser['password'];
        unset($adminUser['username'], $adminUser['password']);

        $adminUserProvisioner->provision(
            $adminUserName,
            $password,
            $adminUser,
        );
    }
}
