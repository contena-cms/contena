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
class Migration1777449602IntegrationMcpAllowlist extends MigrationStep
{
    use AddColumnTrait;

    public function getCreationTimestamp(): int
    {
        return 1777449602;
    }

    public function update(Connection $connection): void
    {
        $this->addColumn(
            $connection,
            'integration',
            'mcp_allowlist',
            'JSON',
        );
    }
}
