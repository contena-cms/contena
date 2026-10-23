<?php declare(strict_types=1);

namespace Contena\Core\Service\Requirement;

use Doctrine\DBAL\Connection;
use Contena\Core\Framework\Log\Package;

/**
 * @internal
 *
 * This requirement gates the privilege granting step. When a contena account is not logged in, privileges are not granted.
 */
class ContenaAccountRequirement implements ServiceRequirement
{
    public const NAME = 'contena_account';

    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public static function getName(): string
    {
        return self::NAME;
    }

    public function getGate(): Gate
    {
        return Gate::PRIVILEGES;
    }

    public function isSatisfied(): bool
    {
        return (bool) $this->connection->fetchOne(
            'SELECT 1 FROM `admin_user` WHERE `store_token` IS NOT NULL LIMIT 1'
        );
    }

    /**
     * Account-bound services are always-on: they stay active and only their privileges follow the
     * account state.
     */
    public function permitsStateChange(): bool
    {
        return false;
    }
}
