<?php declare(strict_types=1);

namespace Contena\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1743151679AddContextGatewayUrl extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1743151679;
    }

    public function update(Connection $connection): void
    {
        $this->addColumn($connection, 'app', 'context_gateway_url', 'VARCHAR(255) NULL');
    }
}
