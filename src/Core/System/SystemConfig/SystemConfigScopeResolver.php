<?php declare(strict_types=1);

namespace Contena\Core\System\SystemConfig;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Uuid\Uuid;
use Doctrine\DBAL\Connection;

/**
 * Resolves the one exact data scope used by a system-config operation.
 *
 * A channel is itself scope-owned, so it can only be combined with a Context
 * carrying the same scope. Cross-scope read capability is intentionally not
 * used here because a configuration map cannot represent multiple owners.
 *
 * @internal
 */
final readonly class SystemConfigScopeResolver
{
    public function __construct(private Connection $connection)
    {
    }

    public function resolve(?string $channelId, ?Context $context): string
    {
        $context ??= Context::createDefaultContext();
        $dataScopeId = $context->getDataScopeId();

        if ($channelId === null) {
            return $dataScopeId;
        }

        $channelExistsInScope = $this->connection->fetchOne(
            'SELECT 1 FROM `channel` WHERE `id` = :channelId AND `data_scope_id` = :dataScopeId',
            [
                'channelId' => Uuid::fromHexToBytes($channelId),
                'dataScopeId' => Uuid::fromHexToBytes($dataScopeId),
            ],
        );

        if ($channelExistsInScope === false) {
            throw SystemConfigException::dataScopeContextMismatch($channelId);
        }

        return $dataScopeId;
    }
}
