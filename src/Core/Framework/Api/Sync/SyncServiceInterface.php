<?php declare(strict_types=1);

namespace Contena\Core\Framework\Api\Sync;

use Doctrine\DBAL\ConnectionException;
use Contena\Core\Framework\Context;

interface SyncServiceInterface
{
    /**
     * @param list<SyncOperation> $operations
     *
     * @throws ConnectionException
     */
    public function sync(array $operations, Context $context, SyncBehavior $behavior): SyncResult;
}
