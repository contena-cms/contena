<?php declare(strict_types=1);

namespace Contena\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Migration\AddColumnTrait;
use Contena\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1777020096AppMcpToolRequiredPrivileges extends MigrationStep
{
    use AddColumnTrait;

    public function getCreationTimestamp(): int
    {
        return 1777020096;
    }

    public function update(Connection $connection): void
    {
        $this->addColumn($connection, 'app_mcp_tool', 'required_privileges', 'JSON');
    }
}
