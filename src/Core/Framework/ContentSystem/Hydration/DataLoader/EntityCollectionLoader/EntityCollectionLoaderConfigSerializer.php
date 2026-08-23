<?php declare(strict_types=1);

namespace Contena\Core\Framework\ContentSystem\Hydration\DataLoader\EntityCollectionLoader;

use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfigSerializer;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\EntityLoader\EntityLoaderConfigSerializer;

/**
 * Serializer for entity_collection source delegates to EntityLoaderConfigSerializer
 * as both sources use identical config structure (EntityLoaderConfig).
 *
 * @internal
 *
 * @final
 */
class EntityCollectionLoaderConfigSerializer extends AbstractContentDataLoaderConfigSerializer
{
    public function __construct(
        private readonly EntityLoaderConfigSerializer $delegate
    ) {
    }

    public static function getSource(): string
    {
        return EntityCollectionLoader::SOURCE;
    }

    public function decode(array $data): AbstractContentDataLoaderConfig
    {
        return $this->delegate->decode($data);
    }

    public function encode(AbstractContentDataLoaderConfig $config): array
    {
        return $this->delegate->encode($config);
    }
}
