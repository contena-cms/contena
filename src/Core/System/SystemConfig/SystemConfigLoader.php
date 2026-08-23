<?php declare(strict_types=1);

namespace Contena\Core\System\SystemConfig;

use Doctrine\DBAL\Connection;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Field\ConfigJsonField;
use Contena\Core\Framework\Plugin;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Kernel;

class SystemConfigLoader extends AbstractSystemConfigLoader
{
    /**
     * @internal
     */
    public function __construct(
        protected Connection $connection,
        protected Kernel $kernel,
    ) {
    }

    public function getDecorated(): AbstractSystemConfigLoader
    {
        throw new DecorationPatternException(self::class);
    }

    public function load(?string $channelId, ?Context $context = null): array
    {
        $tenantId = $this->resolveTenantId($channelId, $context);
        $query = $this->connection->createQueryBuilder();

        $query->from('system_config');
        $query->select('configuration_key', 'configuration_value');

        if ($channelId === null) {
            $query->andWhere('channel_id IS NULL');
        } else {
            $query->andWhere('channel_id = :channelId OR system_config.channel_id IS NULL');
            $query->setParameter('channelId', Uuid::fromHexToBytes($channelId));
        }

        if ($tenantId === null) {
            $query->andWhere('tenant_id IS NULL');
        } else {
            $query->andWhere('(tenant_id IS NULL OR tenant_id = :tenantId)');
            $query->setParameter('tenantId', Uuid::fromHexToBytes($tenantId));
        }

        $query->addOrderBy('tenant_id', 'ASC');
        $query->addOrderBy('channel_id', 'ASC');

        $result = $query->executeQuery();

        return $this->buildSystemConfigArray($result->fetchAllKeyValue());
    }

    /**
     * @param array<string, mixed> $systemConfigs
     *
     * @return array<string, mixed>
     */
    private function buildSystemConfigArray(array $systemConfigs): array
    {
        $configValues = [];

        foreach ($systemConfigs as $key => $value) {
            $keys = \explode('.', $key);

            if ($value !== null) {
                $value = \json_decode((string) $value, true, 512, \JSON_THROW_ON_ERROR);

                if ($value === false || !isset($value[ConfigJsonField::STORAGE_KEY])) {
                    $value = null;
                } else {
                    $value = $value[ConfigJsonField::STORAGE_KEY];
                }
            }

            $configValues = $this->getSubArray($configValues, $keys, $value);
        }

        return $this->filterNotActivatedPlugins($configValues);
    }

    private function resolveTenantId(?string $channelId, ?Context $context): ?string
    {
        $channelExists = false;
        $channelTenantId = null;
        if ($channelId !== null) {
            $channel = $this->connection->fetchAssociative(
                'SELECT LOWER(HEX(tenant_id)) AS tenant_id FROM channel WHERE id = :id',
                ['id' => Uuid::fromHexToBytes($channelId)],
            );
            $channelExists = $channel !== false;
            $channelTenantId = $channel['tenant_id'] ?? null;
        }

        if ($context?->getTenantId() !== null) {
            if ($channelExists && $channelTenantId !== $context->getTenantId()) {
                throw SystemConfigException::tenantContextMismatch($channelId);
            }

            return $context->getTenantId();
        }

        if ($channelTenantId !== null && $context !== null && !$context->hasGlobalTenantAccess()) {
            throw SystemConfigException::tenantContextMismatch($channelId);
        }

        return $channelTenantId;
    }

    /**
     * @param array<string, mixed> $configValues
     * @param non-empty-array<string> $keys
     * @param array<string, mixed>|bool|float|int|string|null $value
     *
     * @return array<string, mixed>
     */
    private function getSubArray(array $configValues, array $keys, $value): array
    {
        $key = \array_shift($keys);

        if ($keys === []) {
            // Configs can be overwritten with channel_id.
            $inheritedValuePresent = \array_key_exists($key, $configValues);
            $valueConsideredEmpty = \in_array($value, [null, '', '0', 0, 0.0, []], true);

            if ($inheritedValuePresent && $valueConsideredEmpty) {
                return $configValues;
            }

            $configValues[$key] = $value;
        } else {
            if (!\array_key_exists($key, $configValues)) {
                $configValues[$key] = [];
            }

            $configValues[$key] = $this->getSubArray($configValues[$key], $keys, $value);
        }

        return $configValues;
    }

    /**
     * @param array<string, mixed> $configValues
     *
     * @return array<string, mixed>
     */
    private function filterNotActivatedPlugins(array $configValues): array
    {
        $notActivatedPlugins = $this->kernel->getPluginLoader()->getPluginInstances()->filter(static fn (Plugin $plugin) => !$plugin->isActive())->all();

        foreach ($notActivatedPlugins as $plugin) {
            if (isset($configValues[$plugin->getName()])) {
                unset($configValues[$plugin->getName()]);
            }
        }

        return $configValues;
    }
}
