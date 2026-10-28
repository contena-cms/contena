<?php declare(strict_types=1);

namespace Contena\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1778142666AddMcpAllowlistToUser extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1778142666;
    }

    public function update(Connection $connection): void
    {
        $this->addColumn($connection, 'user', 'mcp_allowlist', 'JSON');
    }
}
