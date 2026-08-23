<?php declare(strict_types=1);

namespace Contena\Core\Content\Category\ContentSystem\DataLoader;

use Contena\Core\Content\Category\CategoryException;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfigSerializer;

/**
 * @phpstan-import-type ServiceMenuLoaderConfigData from ServiceMenuLoaderConfig
 *
 * @internal
 *
 * @final
 */
class ServiceMenuLoaderConfigSerializer extends AbstractContentDataLoaderConfigSerializer
{
    public static function getSource(): string
    {
        return 'service_menu';
    }

    public function decode(array $data): AbstractContentDataLoaderConfig
    {
        $rootId = null;
        if (\array_key_exists('rootId', $data)) {
            if (!\is_string($data['rootId']) || $data['rootId'] === '') {
                throw CategoryException::invalidFieldValueType('rootId', 'non-empty string', \gettype($data['rootId']));
            }
            $rootId = $data['rootId'];
        }

        return new ServiceMenuLoaderConfig($rootId);
    }

    /**
     * @return ServiceMenuLoaderConfigData
     */
    public function encode(AbstractContentDataLoaderConfig $config): array
    {
        if (!$config instanceof ServiceMenuLoaderConfig) {
            throw CategoryException::invalidFieldValueType('config', ServiceMenuLoaderConfig::class, $config::class);
        }

        return $config->jsonSerialize();
    }
}
