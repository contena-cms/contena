<?php declare(strict_types=1);

namespace Contena\Core\System\SystemConfig\Service;

use Psr\Log\LoggerInterface;
use Contena\Core\Framework\Bundle;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Util\UtilException;
use Contena\Core\System\SystemConfig\Exception\BundleConfigNotFoundException;
use Contena\Core\System\SystemConfig\SystemConfigException;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Core\System\SystemConfig\Util\ConfigReader;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;

class ConfigurationService
{
    /**
     * @internal
     *
     * @param BundleInterface[] $bundles
     */
    public function __construct(
        private readonly iterable $bundles,
        private readonly ConfigReader $configReader,
        private readonly SystemConfigService $systemConfigService,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @throws SystemConfigException
     * @throws \InvalidArgumentException
     * @throws BundleConfigNotFoundException
     * @throws UtilException when config.xml exists but contains invalid XML
     *
     * @return array<mixed>
     */
    public function getConfiguration(string $domain, Context $context): array
    {
        $validDomain = preg_match('/^([\w-]+)\.?([\w-]*)$/', $domain, $match);

        if (!$validDomain) {
            throw SystemConfigException::invalidDomain();
        }

        $scope = $match[1];
        $configName = $match[2] !== '' ? $match[2] : null;

        $config = $this->fetchConfiguration($scope === 'core' ? 'System' : $scope, $configName, $context);
        if (!$config) {
            throw SystemConfigException::configurationNotFound($scope);
        }

        $domain = rtrim($domain, '.') . '.';

        foreach ($config as $i => $card) {
            if (\array_key_exists('flag', $card) && !Feature::isActive($card['flag'])) {
                unset($config[$i]);

                continue;
            }

            foreach ($card['elements'] ?? [] as $j => $field) {
                $newField = ['name' => $domain . $field['name']];

                if (\array_key_exists('flag', $field) && !Feature::isActive($field['flag'])) {
                    unset($card['elements'][$j]);

                    continue;
                }

                if (\array_key_exists('type', $field)) {
                    $newField['type'] = $field['type'];
                }

                unset($field['type'], $field['name']);
                $newField['config'] = $field;
                $card['elements'][$j] = $newField;
            }

            if (isset($card['elements']) && \is_array($card['elements'])) {
                $card['elements'] = array_values($card['elements']);
            }

            $config[$i] = $card;
        }

        return array_values($config);
    }

    /**
     * @return array<mixed>
     */
    public function getResolvedConfiguration(string $domain, Context $context, ?string $channelId = null): array
    {
        $config = [];
        if ($this->checkConfiguration($domain, $context)) {
            $config = array_merge(
                $config,
                $this->enrichValues($this->getConfiguration($domain, $context), $channelId, $context)
            );
        }

        return $config;
    }

    public function checkConfiguration(string $domain, Context $context): bool
    {
        try {
            $this->getConfiguration($domain, $context);

            return true;
        } catch (\InvalidArgumentException|SystemConfigException|BundleConfigNotFoundException|UtilException $e) {
            $this->logConfigurationException($domain, $e);

            return false;
        }
    }

    private function logConfigurationException(string $domain, \Throwable $e): void
    {
        $context = [
            'domain' => $domain,
            'message' => $e->getMessage(),
            'exception' => $e,
        ];

        match (true) {
            $e instanceof \InvalidArgumentException => $this->logger->debug(
                'Invalid configuration domain "{domain}": {message}',
                $context
            ),
            $e instanceof BundleConfigNotFoundException => $this->logger->debug(
                'No configuration file found for "{domain}": {message}',
                $context
            ),
            $e instanceof SystemConfigException => $this->logger->debug(
                'Configuration not loaded for "{domain}" (plugin not installed or not activated): {message}',
                $context
            ),
            // UtilException (XML parsing errors) and any other unexpected exceptions
            default => $this->logger->error(
                'Failed to parse configuration for "{domain}": {message}',
                $context
            ),
        };
    }

    /**
     * @return array<mixed>|null
     */
    private function fetchConfiguration(string $scope, ?string $configName, Context $context): ?array
    {
        $technicalName = \array_slice(explode('\\', $scope), -1)[0];

        foreach ($this->bundles as $bundle) {
            if ($bundle->getName() === $technicalName && $bundle instanceof Bundle) {
                return $this->configReader->getConfigFromBundle($bundle, $configName);
            }
        }

        return null;
    }

    /**
     * @param array<mixed> $config
     *
     * @return array<mixed>
     */
    private function enrichValues(array $config, ?string $channelId, Context $context): array
    {
        foreach ($config as &$card) {
            if (!\is_array($card['elements'] ?? false)) {
                continue;
            }

            foreach ($card['elements'] as &$element) {
                $element['value'] = $this->systemConfigService->get($element['name'], $channelId, $context) ?? $element['config']['defaultValue'] ?? '';
            }
        }

        return $config;
    }
}
