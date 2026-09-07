<?php declare(strict_types=1);

namespace Contena\Core\Test\Stub\ContentSystem;

use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfigSerializer;

/**
 * Encodes {@see StubLoaderConfig}, for the tests whose element carries a requirement whose config the render
 * path has to hash. The source is `entity`, the source those fixtures declare.
 *
 * @final
 */
class StubLoaderConfigSerializer extends AbstractContentDataLoaderConfigSerializer
{
    public static function getSource(): string
    {
        return 'entity';
    }

    public function decode(array $data): AbstractContentDataLoaderConfig
    {
        return new StubLoaderConfig();
    }

    /**
     * @return array<string, mixed>
     */
    public function encode(AbstractContentDataLoaderConfig $config): array
    {
        return $config->jsonSerialize();
    }
}
