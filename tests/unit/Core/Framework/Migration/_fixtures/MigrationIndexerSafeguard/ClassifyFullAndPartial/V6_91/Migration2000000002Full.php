<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Migration\_fixtures\MigrationIndexerSafeguard\ClassifyFullAndPartial\V6_91;

use Contena\Core\Framework\Migration\MigrationStep;
use Doctrine\DBAL\Connection;

/**
 * Fixture for MigrationIndexerSafeguardTest — regex-parsed, never executed.
 *
 * @internal
 */
final class Migration2000000002Full extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 2000000002;
    }

    public function update(Connection $connection): void
    {
        $this->registerIndexer($connection, 'fixture.indexer');
    }
}
