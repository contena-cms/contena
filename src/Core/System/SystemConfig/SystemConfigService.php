<?php declare(strict_types=1);

namespace Contena\Core\System\SystemConfig;

use Contena\Core\Defaults;
use Contena\Core\Framework\Adapter\Cache\CacheTagCollector;
use Contena\Core\Framework\Bundle;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Doctrine\MultiInsertQueryQueue;
use Contena\Core\Framework\DataAbstractionLayer\Field\ConfigJsonField;
use Contena\Core\Framework\Util\Json;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\SystemConfig\Event\BeforeSystemConfigChangedEvent;
use Contena\Core\System\SystemConfig\Event\BeforeSystemConfigMultipleChangedEvent;
use Contena\Core\System\SystemConfig\Event\SystemConfigChangedEvent;
use Contena\Core\System\SystemConfig\Event\SystemConfigDomainLoadedEvent;
use Contena\Core\System\SystemConfig\Event\SystemConfigMultipleChangedEvent;
use Contena\Core\System\SystemConfig\Exception\BundleConfigNotFoundException;
use Contena\Core\System\SystemConfig\Util\ConfigReader;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Service\ResetInterface;

class SystemConfigService implements ResetInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly ConfigReader $configReader,
        private readonly AbstractSystemConfigLoader $loader,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly SymfonySystemConfigService $symfonySystemConfigService,
        private readonly CacheTagCollector $cacheTagCollector,
        private readonly ClockInterface $clock,
        private readonly SystemConfigScopeResolver $scopeResolver,
    ) {
    }

    public static function buildName(string $key): string
    {
        return 'config.' . $key;
    }

    /**
     * @return array<mixed>|bool|float|int|string|null
     */
    public function get(string $key, ?string $channelId = null, ?Context $context = null)
    {
        $this->cacheTagCollector->addTag('system.config-' . $channelId);

        $config = $this->loader->load($channelId, $context);

        $parts = explode('.', $key);

        $pointer = $config;

        foreach ($parts as $part) {
            if (!\is_array($pointer)) {
                return null;
            }

            if (\array_key_exists($part, $pointer)) {
                $pointer = $pointer[$part];

                continue;
            }

            return null;
        }

        return $pointer;
    }

    public function getString(string $key, ?string $channelId = null, ?Context $context = null): string
    {
        $value = $this->get($key, $channelId, $context);
        if (!\is_array($value)) {
            return (string) $value;
        }

        throw SystemConfigException::invalidSettingValueException($key, 'string', \gettype($value));
    }

    public function getInt(string $key, ?string $channelId = null, ?Context $context = null): int
    {
        $value = $this->get($key, $channelId, $context);
        if (!\is_array($value)) {
            return (int) $value;
        }

        throw SystemConfigException::invalidSettingValueException($key, 'int', \gettype($value));
    }

    public function getFloat(string $key, ?string $channelId = null, ?Context $context = null): float
    {
        $value = $this->get($key, $channelId, $context);
        if (!\is_array($value)) {
            return (float) $value;
        }

        throw SystemConfigException::invalidSettingValueException($key, 'float', \gettype($value));
    }

    public function getBool(string $key, ?string $channelId = null, ?Context $context = null): bool
    {
        return (bool) $this->get($key, $channelId, $context);
    }

    /**
     * @internal The cache layer caches all accessed config keys and uses them as cache tags.
     *
     * Gets all available system configuration values.
     *
     * @return array<mixed>
     */
    public function all(?string $channelId = null, ?Context $context = null): array
    {
        return $this->loader->load($channelId, $context);
    }

    /**
     * @internal The cache layer caches all accessed config keys and uses them as cache tags.
     *
     * @throws SystemConfigException
     *
     * @return array<mixed>
     */
    public function getDomain(string $domain, ?string $channelId = null, bool $inherit = false, ?Context $context = null): array
    {
        $domain = trim($domain);
        if ($domain === '') {
            throw SystemConfigException::invalidDomain('Empty domain');
        }

        $dataScopeId = $this->scopeResolver->resolve($channelId, $context);
        $queryBuilder = $this->connection->createQueryBuilder()
            ->select('configuration_key', 'configuration_value')
            ->from('system_config');

        if ($inherit) {
            $queryBuilder->where('channel_id IS NULL OR channel_id = :channelId');
        } elseif ($channelId === null) {
            $queryBuilder->where('channel_id IS NULL');
        } else {
            $queryBuilder->where('channel_id = :channelId');
        }

        $domain = rtrim($domain, '.') . '.';
        $escapedDomain = str_replace('%', '\\%', $domain);

        $queryBuilder->andWhere('configuration_key LIKE :prefix')
            ->andWhere('data_scope_id = :dataScopeId')
            ->addOrderBy('channel_id', 'ASC')
            ->setParameter('prefix', $escapedDomain . '%')
            ->setParameter('channelId', $channelId ? Uuid::fromHexToBytes($channelId) : null)
            ->setParameter('dataScopeId', Uuid::fromHexToBytes($dataScopeId));

        $configs = $queryBuilder->executeQuery()->fetchAllNumeric();

        $merged = [];

        foreach ($configs as [$key, $value]) {
            if ($value !== null) {
                $value = \json_decode((string) $value, true, 512, \JSON_THROW_ON_ERROR);

                if ($value === false || !isset($value[ConfigJsonField::STORAGE_KEY])) {
                    $value = null;
                } else {
                    $value = $value[ConfigJsonField::STORAGE_KEY];
                }
            }

            $merged[$key] = $value;
        }

        $merged = $this->symfonySystemConfigService->override($merged, $channelId, $inherit, false);
        $merged = array_filter($merged, static fn (string $key) => str_starts_with($key, $domain), \ARRAY_FILTER_USE_KEY);

        $event = new SystemConfigDomainLoadedEvent($domain, $merged, $inherit, $channelId);
        $this->dispatcher->dispatch($event);

        return $event->getConfig();
    }

    /**
     * @param array<mixed>|bool|float|int|string|null $value
     */
    public function set(string $key, $value, ?string $channelId = null, bool $silent = true, ?Context $context = null): void
    {
        $this->setMultiple([$key => $value], $channelId, $silent, $context);
    }

    /**
     * @param array<string, array<mixed>|bool|float|int|string|null> $values
     */
    public function setMultiple(array $values, ?string $channelId = null, bool $silent = true, ?Context $context = null): void
    {
        $dataScopeId = $this->scopeResolver->resolve($channelId, $context);

        foreach ($values as $key => $value) {
            if ($this->symfonySystemConfigService->has($key)) {
                /**
                 * The administration setting pages are always sending the full configuration.
                 * This means when the user wants to change an allowed configuration, we also get the read-only configuration,
                 *
                 * Therefore, when the value of that field is the same as the statically configured one, we just drop that value and don't throw an exception
                 */
                if ($this->symfonySystemConfigService->get($key, $channelId) === $value) {
                    unset($values[$key]);
                    continue;
                }

                throw SystemConfigException::systemConfigKeyIsManagedBySystems($key);
            }
        }

        $beforeChangedEvent = new BeforeSystemConfigMultipleChangedEvent($values, $channelId);
        $this->dispatcher->dispatch($beforeChangedEvent);

        $values = $beforeChangedEvent->getConfig();

        $where = $channelId ? 'channel_id = :channelId' : 'channel_id IS NULL';
        $where .= ' AND data_scope_id = :dataScopeId';
        $existingIds = $this->connection->fetchAllKeyValue(
            'SELECT configuration_key, id FROM system_config WHERE ' . $where . ' AND configuration_key IN (:configurationKeys)',
            [
                'channelId' => $channelId ? Uuid::fromHexToBytes($channelId) : null,
                'dataScopeId' => Uuid::fromHexToBytes($dataScopeId),
                'configurationKeys' => array_keys($values),
            ],
            ['configurationKeys' => ArrayParameterType::STRING],
        );

        $toBeDeleted = [];
        $insertQueue = new MultiInsertQueryQueue($this->connection, 100, false, true);
        $events = [];

        foreach ($values as $key => $value) {
            $key = trim($key);
            $this->validateKey($key);

            $event = new BeforeSystemConfigChangedEvent($key, $value, $channelId);
            $this->dispatcher->dispatch($event);

            // Use modified value provided by potential event subscribers.
            $value = $event->getValue();

            // On null value, delete the config
            if ($value === null) {
                $toBeDeleted[] = $key;

                $events[] = new SystemConfigChangedEvent($key, $value, $channelId);

                continue;
            }

            if (isset($existingIds[$key])) {
                $this->connection->update(
                    'system_config',
                    [
                        'configuration_value' => Json::encode(['_value' => $value]),
                        'updated_at' => $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                    ],
                    [
                        'id' => $existingIds[$key],
                    ]
                );

                $events[] = new SystemConfigChangedEvent($key, $value, $channelId);

                continue;
            }

            $insertQueue->addInsert(
                'system_config',
                [
                    'id' => Uuid::randomBytes(),
                    'data_scope_id' => Uuid::fromHexToBytes($dataScopeId),
                    'configuration_key' => $key,
                    'configuration_value' => Json::encode(['_value' => $value]),
                    'channel_id' => $channelId ? Uuid::fromHexToBytes($channelId) : null,
                    'created_at' => $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ],
            );

            $events[] = new SystemConfigChangedEvent($key, $value, $channelId);
        }

        // Delete all null values
        if ($toBeDeleted !== []) {
            $qb = $this->connection
                ->createQueryBuilder()
                ->where('configuration_key IN (:keys)')
                ->setParameter('keys', $toBeDeleted, ArrayParameterType::STRING);

            if ($channelId) {
                $qb->andWhere('channel_id = :channelId')
                    ->setParameter('channelId', Uuid::fromHexToBytes($channelId));
            } else {
                $qb->andWhere('channel_id IS NULL');
            }

            $qb->andWhere('data_scope_id = :dataScopeId')
                ->setParameter('dataScopeId', Uuid::fromHexToBytes($dataScopeId));

            $qb->delete('system_config')
                ->executeStatement();
        }

        $insertQueue->execute();

        // Dispatch events that the given values have been changed
        foreach ($events as $event) {
            $this->dispatcher->dispatch($event);
        }

        $this->dispatcher->dispatch(new SystemConfigMultipleChangedEvent($values, $channelId, $silent));
    }

    public function delete(string $key, ?string $channelId = null, bool $silent = true, ?Context $context = null): void
    {
        $this->setMultiple([$key => null], $channelId, $silent, $context);
    }

    /**
     * Fetches default values from bundle configuration and saves it to database
     */
    public function savePluginConfiguration(Bundle $bundle, bool $override = false): void
    {
        try {
            $config = $this->configReader->getConfigFromBundle($bundle);
        } catch (BundleConfigNotFoundException) {
            return;
        }

        $prefix = $bundle->getName() . '.config.';

        $this->saveConfig($config, $prefix, $override);
    }

    /**
     * @param array<mixed> $config
     */
    public function saveConfig(array $config, string $prefix, bool $override): void
    {
        $relevantSettings = $this->getDomain($prefix);

        foreach ($config as $card) {
            foreach ($card['elements'] as $element) {
                $key = $prefix . $element['name'];
                if (!isset($element['defaultValue'])) {
                    continue;
                }

                if ($override || !isset($relevantSettings[$key])) {
                    $this->set($key, $element['defaultValue'], null, false);
                }
            }
        }
    }

    public function deletePluginConfiguration(Bundle $bundle): void
    {
        try {
            $config = $this->configReader->getConfigFromBundle($bundle);
        } catch (BundleConfigNotFoundException) {
            return;
        }

        $this->deleteExtensionConfiguration($bundle->getName(), $config);
    }

    /**
     * @param array<mixed> $config
     */
    public function deleteExtensionConfiguration(string $extensionName, array $config): void
    {
        $prefix = $extensionName . '.config.';

        $configKeys = [];
        foreach ($config as $card) {
            foreach ($card['elements'] as $element) {
                $configKeys[] = $prefix . $element['name'];
            }
        }

        if ($configKeys === []) {
            return;
        }

        $scopes = $this->connection->fetchAllAssociative(
            'SELECT DISTINCT channel_id, data_scope_id FROM system_config WHERE configuration_key IN (:keys)',
            ['keys' => $configKeys],
            ['keys' => ArrayParameterType::STRING],
        );

        $keysForDelete = array_fill_keys($configKeys, null);
        $this->setMultiple($keysForDelete, null, false, Context::createDefaultContext());

        foreach ($scopes as $scope) {
            $channelId = $scope['channel_id'] === null ? null : Uuid::fromBytesToHex((string) $scope['channel_id']);
            $dataScopeId = Uuid::fromBytesToHex((string) $scope['data_scope_id']);
            $scopeContext = $dataScopeId === Defaults::PLATFORM_DATA_SCOPE
                ? Context::createDefaultContext()
                : Context::createTenantContext($dataScopeId);

            $this->setMultiple($keysForDelete, $channelId, false, $scopeContext);
        }
    }

    public function reset(): void
    {
    }

    private function validateKey(string $key): void
    {
        $key = trim($key);
        if ($key === '') {
            throw SystemConfigException::invalidKey('key may not be empty');
        }
    }
}
