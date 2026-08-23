<?php declare(strict_types=1);

namespace Contena\Core\Test\Stub\ContentSystem;

use Contena\Core\Framework\ContentSystem\ContentSystemException;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfigSerializer;

/**
 * The config serializer of {@see TestNavigationShapedLoader}, tagged `content_system.config_serializer` in
 * services_test.xml.
 *
 * @internal
 *
 * @final
 */
class TestNavigationShapedLoaderConfigSerializer extends AbstractContentDataLoaderConfigSerializer
{
    public static function getSource(): string
    {
        return TestNavigationShapedLoader::SOURCE;
    }

    public function decode(array $data): AbstractContentDataLoaderConfig
    {
        if (!\array_key_exists('entity', $data) || !\is_string($data['entity']) || $data['entity'] === '') {
            throw ContentSystemException::invalidFieldValueType('entity', 'non-empty string', \gettype($data['entity'] ?? null));
        }

        $activeProperty = $data['activeProperty'] ?? null;
        if ($activeProperty !== null && !\is_string($activeProperty)) {
            throw ContentSystemException::invalidFieldValueType('activeProperty', 'string', \gettype($activeProperty));
        }

        return new TestNavigationShapedLoaderConfig($data['entity'], $activeProperty);
    }

    /**
     * @return array<string, mixed>
     */
    public function encode(AbstractContentDataLoaderConfig $config): array
    {
        if (!$config instanceof TestNavigationShapedLoaderConfig) {
            throw ContentSystemException::invalidFieldValueType('config', TestNavigationShapedLoaderConfig::class, $config::class);
        }

        return $config->jsonSerialize();
    }
}
