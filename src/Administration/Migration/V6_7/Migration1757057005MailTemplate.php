<?php declare(strict_types=1);

namespace Contena\Administration\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
class Migration1757057005MailTemplate extends MigrationStep
{
    use UpdateMailTrait;
use Contena\Core\Framework\Migration\MigrationStep;

    public function getCreationTimestamp(): int
    {
        return 1757057005;
    }

    public function update(Connection $connection): void
    {
        $mailUpdateData = $this->getMailData();

        $this->updateMail($mailUpdateData, $connection);
    }

}
