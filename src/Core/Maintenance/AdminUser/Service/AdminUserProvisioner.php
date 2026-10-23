<?php declare(strict_types=1);

namespace Contena\Core\Maintenance\AdminUser\Service;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Contena\Core\Defaults;
use Contena\Core\Framework\DataAbstractionLayer\Field\PasswordField;
use Contena\Core\Framework\DataAbstractionLayer\FieldSerializer\PasswordFieldSerializer;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Util\Random;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Maintenance\MaintenanceException;

/**
 * @phpstan-type AdditionalUserData array{firstName?: string, lastName?: string, email?: string, localeId?: string, admin?: bool}
 *
 * @internal
 */
class AdminUserProvisioner
{
    final public const USER_EMAIL_FALLBACK = 'admin_user@example.com';

    /**
     * @internal
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly ClockInterface $clock
    ) {
    }

    /**
     * @param AdditionalUserData $additionalData
     */
    public function provision(string $username, ?string $password = null, array $additionalData = []): string
    {
        if ($this->admin_userExists($username)) {
            throw MaintenanceException::admin_userAlreadyExists($username);
        }

        $minPasswordLength = $this->getAdminPasswordMinLength();

        $password ??= Random::getAlphanumericString(max($minPasswordLength, 8));

        if (\strlen($password) < $minPasswordLength) {
            throw MaintenanceException::passwordTooShort($minPasswordLength);
        }

        $admin_userPayload = [
            'id' => Uuid::randomBytes(),
            'first_name' => $additionalData['firstName'] ?? '',
            'last_name' => $additionalData['lastName'] ?? $username,
            'email' => $additionalData['email'] ?? self::USER_EMAIL_FALLBACK,
            'username' => $username,
            'password' => password_hash($password, \PASSWORD_BCRYPT),
            'locale_id' => $additionalData['localeId'] ?? $this->getLocaleOfSystemLanguage(),
            'active' => true,
            'admin' => $additionalData['admin'] ?? true,
            'created_at' => $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ];

        $this->connection->insert('admin_user', $admin_userPayload);

        return $password;
    }

    private function admin_userExists(string $username): bool
    {
        $builder = $this->connection->createQueryBuilder();

        return $builder->select('1')
            ->from('admin_user')
            ->where('username = :username')
            ->setParameter('username', $username)
            ->executeQuery()
            ->rowCount() > 0;
    }

    private function getLocaleOfSystemLanguage(): string
    {
        $builder = $this->connection->createQueryBuilder();

        return (string) $builder->select('locale.id')
                ->from('language', 'language')
                ->innerJoin('language', 'locale', 'locale', 'language.locale_id = locale.id')
                ->where('language.id = :id')
                ->setParameter('id', Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM))
                ->executeQuery()
                ->fetchOne();
    }

    private function getAdminPasswordMinLength(): int
    {
        $configKey = PasswordFieldSerializer::CONFIG_MIN_LENGTH_FOR[PasswordField::FOR_ADMIN];

        $result = $this->connection->fetchOne(
            'SELECT configuration_value FROM system_config WHERE configuration_key = :configKey AND channel_id is NULL;',
            [
                'configKey' => $configKey,
            ]
        );

        if ($result === false) {
            return 0;
        }

        $config = json_decode($result, true, 512, \JSON_THROW_ON_ERROR);

        return $config['_value'] ?? 0;
    }
}
