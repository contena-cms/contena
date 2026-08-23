<?php declare(strict_types=1);

namespace Contena\Core\Framework\Migration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\DriverException;

/**
 * Executes DDL, retrying once with `restrict_fk_on_non_standard_key` relaxed when MySQL 8.4
 * rejects the statement with error 1553 through bug #118151. Legitimate failures still fail the
 * retry; servers without the session variable do not retry.
 *
 * @see https://bugs.mysql.com/bug.php?id=118151
 *
 * @internal Temporary workaround until the MySQL bug is fixed
 */
final class NonStandardFkGuard
{
    private const string GUARD_VARIABLE = 'restrict_fk_on_non_standard_key';

    private const int ER_DROP_INDEX_FK = 1553;

    public static function executeDdl(Connection $connection, string $sql): void
    {
        try {
            $connection->executeStatement($sql);
        } catch (DriverException $e) {
            if ($e->getCode() !== self::ER_DROP_INDEX_FK) {
                throw $e;
            }

            self::retryWithRelaxedGuard($connection, $sql, $e);
        }
    }

    private static function retryWithRelaxedGuard(Connection $connection, string $sql, DriverException $original): void
    {
        $guard = $connection->fetchAssociative(
            'SHOW SESSION VARIABLES LIKE :variable',
            ['variable' => self::GUARD_VARIABLE]
        );

        if ($guard === false || $guard['Value'] !== 'ON') {
            throw $original;
        }

        $connection->executeStatement('SET SESSION ' . self::GUARD_VARIABLE . ' = OFF');

        try {
            $connection->executeStatement($sql);
        } finally {
            $connection->executeStatement('SET SESSION ' . self::GUARD_VARIABLE . ' = ON');
        }
    }
}
