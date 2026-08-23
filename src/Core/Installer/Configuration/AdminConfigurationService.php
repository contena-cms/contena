<?php declare(strict_types=1);

namespace Contena\Core\Installer\Configuration;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Contena\Core\Installer\Controller\SystemConfigurationController;
use Contena\Core\Maintenance\User\Service\UserProvisioner;
use Contena\Core\System\NumberRange\ValueGenerator\AbstractNumberRangeValueGenerator;

/**
 * @internal
 *
 * @phpstan-import-type AdminUser from SystemConfigurationController
 */
class AdminConfigurationService
{
    public function __construct(
        private readonly ClockInterface $clock,
        private readonly AbstractNumberRangeValueGenerator $numberRangeValueGenerator
    ) {
    }

    /**
     * @param AdminUser $user
     */
    public function createAdmin(array $user, Connection $connection): void
    {
        $userProvisioner = new UserProvisioner($connection, $this->clock, $this->numberRangeValueGenerator);
        $isDefaultAdmin = strcasecmp($user['username'], UserProvisioner::DEFAULT_ADMIN_USERNAME) === 0;
        $additionalData = [
            'name' => $user['name'],
            'email' => $user['email'],
            'admin' => true,
        ];

        if ($isDefaultAdmin) {
            $additionalData['roleCode'] = UserProvisioner::ADMINISTRATOR_ROLE_CODE;
        }

        $userProvisioner->provision(
            $user['username'],
            $user['password'],
            $additionalData
        );

        if ($isDefaultAdmin) {
            return;
        }

        $email = strcasecmp($user['email'], UserProvisioner::DEFAULT_ADMIN_EMAIL) === 0
            ? UserProvisioner::USER_EMAIL_FALLBACK
            : UserProvisioner::DEFAULT_ADMIN_EMAIL;

        $userProvisioner->provision(
            UserProvisioner::DEFAULT_ADMIN_USERNAME,
            $user['password'],
            [
                'name' => UserProvisioner::DEFAULT_ADMIN_USERNAME,
                'email' => $email,
                'admin' => false,
                'roleCode' => UserProvisioner::ADMINISTRATOR_ROLE_CODE,
            ]
        );
    }
}
