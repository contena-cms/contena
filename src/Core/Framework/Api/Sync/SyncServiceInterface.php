<?php declare(strict_types=1);

namespace Contena\Core\Framework\Api\Sync;

use Contena\Core\Framework\Context;
use Doctrine\DBAL\ConnectionException;

interface SyncServiceInterface
{
    /**
     * @param list<SyncOperation> $operations
     *
     * @throws ConnectionException
     */
    public function sync(array $operations, Context $context, SyncBehavior $behavior): SyncResult;
}
