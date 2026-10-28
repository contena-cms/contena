<?php declare(strict_types=1);

namespace Contena\Core\Migration\V6_8;

use Doctrine\DBAL\Connection;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('after-sales')]
class Migration1779977359RemoveMailTemplateTypeTemplateDataColumn extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1779977359;
    }

    public function update(Connection $connection): void
    {
    }

    public function updateDestructive(Connection $connection): void
    {
        $this->dropColumnIfExists($connection, 'mail_template_type', 'template_data');
    }
}
