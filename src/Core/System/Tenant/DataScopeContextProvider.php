<?php declare(strict_types=1);

namespace Contena\Core\System\Tenant;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\DataScope;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Tests\Integration\Core\System\Tenant\DataScopeContextProviderTest;
use Doctrine\DBAL\Connection;

/**
 * Provides every business-data context with the platform scope first.
 *
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see DataScopeContextProviderTest
 */
class DataScopeContextProvider
{
    private readonly int $batchSize;

    public function __construct(
        private readonly Connection $connection,
        int $batchSize = 500,
    ) {
        $this->batchSize = max(1, $batchSize);
    }

    /**
     * @return \Generator<int, Context>
     */
    public function getContexts(?Context $baseContext = null): \Generator
    {
        $baseContext ??= Context::createDefaultContext();

        yield $baseContext->createWithDataScope(DataScope::platform());

        $lastTenantId = null;
        while (true) {
            $where = '';
            $parameters = [];
            if ($lastTenantId !== null) {
                $where = 'WHERE `id` > :lastTenantId';
                $parameters['lastTenantId'] = Uuid::fromHexToBytes($lastTenantId);
            }

            $tenantIds = $this->connection->fetchFirstColumn(
                \sprintf(
                    'SELECT LOWER(HEX(`id`)) FROM `tenant` %s ORDER BY `id` LIMIT %d',
                    $where,
                    $this->batchSize,
                ),
                $parameters,
            );

            foreach ($tenantIds as $tenantId) {
                if (!\is_string($tenantId) || !Uuid::isValid($tenantId)) {
                    continue;
                }

                yield $baseContext->createWithDataScope(DataScope::tenant($tenantId));
            }

            if (\count($tenantIds) < $this->batchSize) {
                return;
            }

            $lastTenantId = end($tenantIds);
            if (!\is_string($lastTenantId) || !Uuid::isValid($lastTenantId)) {
                return;
            }
        }
    }
}
