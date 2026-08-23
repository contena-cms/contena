<?php declare(strict_types=1);

namespace Contena\Core\Migration\V6_8;

use Doctrine\DBAL\Connection;
use Contena\Core\Framework\Migration\AddColumnTrait;
use Contena\Core\Framework\Migration\MigrationStep;

/**
 * Stores optional per-principal MCP capability restrictions.
 *
 * @internal
 */
class Migration1785335178AddMcpAllowlist extends MigrationStep
{
    use AddColumnTrait;

    public function getCreationTimestamp(): int
    {
        return 1785335178;
    }

    public function update(Connection $connection): void
    {
        $this->addColumn($connection, 'integration', 'mcp_allowlist', 'JSON');
        $this->addColumn($connection, 'user', 'mcp_allowlist', 'JSON');
    }
}
