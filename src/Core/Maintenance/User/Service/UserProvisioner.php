<?php declare(strict_types=1);

namespace Contena\Core\Maintenance\User\Service;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Field\PasswordField;
use Contena\Core\Framework\DataAbstractionLayer\FieldSerializer\PasswordFieldSerializer;
use Contena\Core\Framework\Util\Random;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Maintenance\MaintenanceException;
use Contena\Core\System\NumberRange\ValueGenerator\AbstractNumberRangeValueGenerator;

/**
 * @internal
 */
class UserProvisioner
{
    final public const string USER_EMAIL_FALLBACK = 'user@example.com';

    final public const string DEFAULT_ADMIN_USERNAME = 'admin';

    final public const string DEFAULT_ADMIN_EMAIL = 'admin@contena.local';

    final public const string ADMINISTRATOR_ROLE_CODE = 'administrator';

    /**
     * @internal
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly ClockInterface $clock,
        private readonly AbstractNumberRangeValueGenerator $numberRangeValueGenerator,
    ) {
    }

    /**
     * @param array{name?: string, phoneNumber?: string, email?: string, localeId?: string, admin?: bool, roleCode?: string} $additionalData
     */
    public function provision(string $username, ?string $password = null, array $additionalData = []): string
    {
        if ($this->userExists($username)) {
            throw MaintenanceException::userAlreadyExists($username);
        }

        $minPasswordLength = $this->getAdminPasswordMinLength();

        $password ??= Random::getAlphanumericString(max($minPasswordLength, 8));

        if (\strlen($password) < $minPasswordLength) {
            throw MaintenanceException::passwordTooShort($minPasswordLength);
        }

        $roleId = isset($additionalData['roleCode']) ? $this->getRoleId($additionalData['roleCode']) : null;
        $createdAt = $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        $userPayload = [
            'id' => Uuid::randomBytes(),
            'user_code' => $this->numberRangeValueGenerator->getValue('user', Context::createDefaultContext()),
            'name' => $additionalData['name'] ?? $username,
            'phone_number' => $additionalData['phoneNumber'] ?? null,
            'email' => $additionalData['email'] ?? self::USER_EMAIL_FALLBACK,
            'username' => $username,
            'password' => password_hash($password, \PASSWORD_BCRYPT),
            'locale_id' => $additionalData['localeId'] ?? $this->getLocaleOfSystemLanguage(),
            'active' => true,
            'admin' => (int) ($additionalData['admin'] ?? true),
            'time_zone' => Defaults::DEFAULT_TIME_ZONE,
            'created_at' => $createdAt,
        ];

        $this->connection->insert('user', $userPayload);

        if ($roleId !== null) {
            $this->connection->insert('acl_user_role', [
                'user_id' => $userPayload['id'],
                'acl_role_id' => $roleId,
                'created_at' => $createdAt,
            ]);
        }

        return $password;
    }

    private function getRoleId(string $roleCode): string
    {
        $roleId = $this->connection->fetchOne(
            'SELECT `id` FROM `acl_role` WHERE `code` = :roleCode',
            ['roleCode' => $roleCode]
        );

        if (!\is_string($roleId)) {
            throw MaintenanceException::couldNotGetId('acl_role');
        }

        return $roleId;
    }

    private function userExists(string $username): bool
    {
        $builder = $this->connection->createQueryBuilder();

        return $builder->select('1')
            ->from('user')
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
            'SELECT configuration_value
             FROM system_config
             WHERE configuration_key = :configKey AND tenant_id IS NULL AND channel_id IS NULL;',
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
