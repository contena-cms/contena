<?php declare(strict_types=1);

namespace Contena\Core\Framework\App;

use Doctrine\DBAL\Connection;
use Contena\Core\Framework\Log\Package;

/**
 * Resolves an app's current secret by name, read fresh on each call so a rotation
 * (AppSecretRotationService) is used immediately instead of a stale value.
 *
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Contena\Tests\Integration\Core\Framework\App\AppSecretResolverTest
 */
readonly class AppSecretResolver
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    public function resolve(string $appName): ?string
    {
        $secret = $this->connection->fetchOne('SELECT `app_secret` FROM `app` WHERE `name` = :name', ['name' => $appName]);

        return \is_string($secret) ? $secret : null;
    }
}
